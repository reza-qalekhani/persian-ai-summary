<?php
/**
 * Persistence boundary for summary state.
 *
 * @package PersianAiSummary
 */

declare(strict_types=1);

namespace PersianAiSummary\Contracts;

use PersianAiSummary\Domain\Summary;

/**
 * Stores one summary and one active generation lock per standard post.
 */
interface SummaryRepository {
	/**
	 * Find the current summary.
	 *
	 * @param int $post_id Standard post ID.
	 * @return Summary|null Current summary when present.
	 */
	public function find( int $post_id ): ?Summary;

	/**
	 * Read exact saved post content without applying render filters.
	 *
	 * @param int $post_id Standard post ID.
	 * @return string|null Exact saved content when the post is supported.
	 */
	public function get_saved_post_content( int $post_id ): ?string;

	/**
	 * Store a summary only when the complete prior value still matches.
	 *
	 * @param int          $post_id Standard post ID.
	 * @param Summary      $summary New summary state.
	 * @param Summary|null $expected Complete expected prior state.
	 * @return bool Whether the conditional write succeeded.
	 */
	public function save( int $post_id, Summary $summary, ?Summary $expected ): bool;

	/**
	 * Remove a summary only when the complete prior value still matches.
	 *
	 * @param int     $post_id  Standard post ID.
	 * @param Summary $expected Complete expected prior state.
	 * @return bool Whether the conditional removal succeeded.
	 */
	public function delete( int $post_id, Summary $expected ): bool;

	/**
	 * Acquire the post's generation lock.
	 *
	 * @param int    $post_id        Standard post ID.
	 * @param string $owner          Unpredictable owner token.
	 * @param string $generation_hash Generation input hash.
	 * @param int    $expires_at     UTC expiry epoch.
	 * @return bool Whether the lock was acquired.
	 */
	public function acquire_lock(
		int $post_id,
		string $owner,
		string $generation_hash,
		int $expires_at
	): bool;

	/**
	 * Release the lock only when it belongs to the supplied owner.
	 *
	 * @param int    $post_id Standard post ID.
	 * @param string $owner   Unpredictable owner token.
	 */
	public function release_lock( int $post_id, string $owner ): void;
}
