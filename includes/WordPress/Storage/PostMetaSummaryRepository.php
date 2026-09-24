<?php
/**
 * WordPress post-meta summary repository.
 *
 * @package PersianAiSummary
 */

declare(strict_types=1);

namespace PersianAiSummary\WordPress\Storage;

use InvalidArgumentException;
use PersianAiSummary\Contracts\SummaryRepository;
use PersianAiSummary\Domain\Summary;
use WP_Post;

/**
 * Stores one summary record and one generation lock per standard post.
 */
final class PostMetaSummaryRepository implements SummaryRepository {
	private const META_KEY    = '_persian_ai_summary';
	private const LOCK_PREFIX = '_persian_ai_summary_lock_';

	/**
	 * Register protected metadata for standard posts.
	 */
	public function register(): void {
		register_post_meta(
			'post',
			self::META_KEY,
			array(
				'type'              => 'object',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => array( self::class, 'sanitize_record' ),
				'auth_callback'     => static function ( bool $allowed, string $meta_key, int $post_id ): bool {
					unset( $allowed, $meta_key );
					return current_user_can( 'edit_post', $post_id );
				},
			)
		);
	}

	/**
	 * Sanitize a complete metadata record.
	 *
	 * @param mixed $value Candidate record.
	 * @return array<string, int|string|null> Valid record or an empty rejected value.
	 */
	public static function sanitize_record( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		try {
			return Summary::from_array( $value )->to_array();
		} catch ( InvalidArgumentException $exception ) {
			unset( $exception );
			return array();
		}
	}

	/**
	 * Find the current summary.
	 *
	 * @param int $post_id Standard post ID.
	 */
	public function find( int $post_id ): ?Summary {
		if ( ! $this->is_supported_post( $post_id ) ) {
			return null;
		}

		$record = get_post_meta( $post_id, self::META_KEY, true );

		if ( ! is_array( $record ) || array() === $record ) {
			return null;
		}

		try {
			return Summary::from_array( $record );
		} catch ( InvalidArgumentException $exception ) {
			unset( $exception );
			return null;
		}
	}

	/**
	 * Read exact saved post content without render filters.
	 *
	 * @param int $post_id Standard post ID.
	 */
	public function get_saved_post_content( int $post_id ): ?string {
		if ( ! $this->is_supported_post( $post_id ) ) {
			return null;
		}

		$content = get_post_field( 'post_content', $post_id, 'raw' );
		return is_string( $content ) ? $content : null;
	}

	/**
	 * Store a summary only when the complete prior value still matches.
	 *
	 * @param int          $post_id Standard post ID.
	 * @param Summary      $summary New summary state.
	 * @param Summary|null $expected Complete expected prior state.
	 */
	public function save( int $post_id, Summary $summary, ?Summary $expected ): bool {
		global $wpdb;

		if ( ! $this->is_supported_post( $post_id ) ) {
			return false;
		}

		if ( null === $expected ) {
			return false !== add_post_meta( $post_id, self::META_KEY, $summary->to_array(), true );
		}

		// The Metadata API adds a new row when the expected row was concurrently
		// deleted, so it cannot provide compare-and-swap deletion safety.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Core has no atomic conditional metadata update.
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE post_id = %d AND meta_key = %s AND meta_value = %s",
				maybe_serialize( $summary->to_array() ),
				$post_id,
				self::META_KEY,
				maybe_serialize( $expected->to_array() )
			)
		);

		if ( 1 !== $updated ) {
			return false;
		}

		wp_cache_delete( $post_id, 'post_meta' );
		return true;
	}

	/**
	 * Remove a summary only when the complete prior value still matches.
	 *
	 * @param int     $post_id  Standard post ID.
	 * @param Summary $expected Complete expected prior state.
	 */
	public function delete( int $post_id, Summary $expected ): bool {
		return $this->is_supported_post( $post_id )
			&& delete_post_meta( $post_id, self::META_KEY, $expected->to_array() );
	}

	/**
	 * Acquire the post's generation lock.
	 *
	 * @param int    $post_id         Standard post ID.
	 * @param string $owner           Unpredictable owner token.
	 * @param string $generation_hash Generation input hash.
	 * @param int    $expires_at      UTC expiry epoch.
	 */
	public function acquire_lock(
		int $post_id,
		string $owner,
		string $generation_hash,
		int $expires_at
	): bool {
		if ( ! $this->is_supported_post( $post_id ) ) {
			return false;
		}

		$option_name = $this->lock_name( $post_id );
		$lock        = array(
			'owner'           => $owner,
			'generation_hash' => $generation_hash,
			'expires_at'      => $expires_at,
		);

		if ( add_option( $option_name, $lock, '', false ) ) {
			return true;
		}

		$current = get_option( $option_name );

		if ( ! is_array( $current ) || (int) ( $current['expires_at'] ?? 0 ) >= time() ) {
			return false;
		}

		$this->delete_lock_if_unchanged( $option_name, $current );

		return add_option( $option_name, $lock, '', false );
	}

	/**
	 * Release the lock only when it belongs to the supplied owner.
	 *
	 * @param int    $post_id Standard post ID.
	 * @param string $owner   Unpredictable owner token.
	 */
	public function release_lock( int $post_id, string $owner ): void {
		$option_name = $this->lock_name( $post_id );
		$current     = get_option( $option_name );

		if ( is_array( $current ) && ( $current['owner'] ?? null ) === $owner ) {
			$this->delete_lock_if_unchanged( $option_name, $current );
		}
	}

	/**
	 * Check the storage owner is a standard post.
	 *
	 * @param int $post_id Candidate post ID.
	 */
	private function is_supported_post( int $post_id ): bool {
		$post = get_post( $post_id );
		return $post instanceof WP_Post && 'post' === $post->post_type;
	}

	/**
	 * Build the stable per-post option name.
	 *
	 * @param int $post_id Standard post ID.
	 */
	private function lock_name( int $post_id ): string {
		return self::LOCK_PREFIX . $post_id;
	}

	/**
	 * Delete only the exact lock value observed by the caller.
	 *
	 * @param string               $option_name Lock option name.
	 * @param array<string, mixed> $lock        Complete observed lock value.
	 */
	private function delete_lock_if_unchanged( string $option_name, array $lock ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- The Options API has no atomic compare-and-delete operation.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
				$option_name,
				maybe_serialize( $lock )
			)
		);

		if ( $deleted ) {
			wp_cache_delete( $option_name, 'options' );
		}
	}
}
