<?php
/**
 * Summary application workflows.
 *
 * @package PersianAiSummary
 */

declare(strict_types=1);

namespace PersianAiSummary\Application;

use DateTimeImmutable;
use DateTimeZone;
use PersianAiSummary\Contracts\SummaryProvider;
use PersianAiSummary\Contracts\SummaryRepository;
use PersianAiSummary\Domain\Summary;
use PersianAiSummary\WordPress\Storage\ApiCallLog;
use WP_Error;

/**
 * Coordinates explicit generation against saved post content.
 */
final class SummaryService {
	/**
	 * Summary persistence.
	 *
	 * @var SummaryRepository
	 */
	private SummaryRepository $repository;

	/**
	 * Configured provider.
	 *
	 * @var SummaryProvider
	 */
	private SummaryProvider $provider;

	/**
	 * Provider model.
	 *
	 * @var string
	 */
	private string $model;

	/**
	 * System instructions.
	 *
	 * @var string
	 */
	private string $instructions;

	/**
	 * Optional known input limit.
	 *
	 * @var int|null
	 */
	private ?int $max_input_characters;

	/**
	 * Non-secret endpoint used in generation identity.
	 *
	 * @var string
	 */
	private string $endpoint;

	/**
	 * Optional API call log.
	 *
	 * @var ApiCallLog|null
	 */
	private ?ApiCallLog $log;

	/**
	 * Store generation dependencies and non-secret configuration.
	 *
	 * @param SummaryRepository $repository           Summary persistence.
	 * @param SummaryProvider   $provider             Configured provider.
	 * @param string            $model                Provider model.
	 * @param string            $instructions         System instructions.
	 * @param int|null          $max_input_characters Optional known input limit.
	 * @param string            $endpoint             Non-secret endpoint identity.
	 * @param ApiCallLog|null   $log                  Optional API call log.
	 */
	public function __construct(
		SummaryRepository $repository,
		SummaryProvider $provider,
		string $model,
		string $instructions,
		?int $max_input_characters = null,
		string $endpoint = '',
		?ApiCallLog $log = null
	) {
		$this->repository           = $repository;
		$this->provider             = $provider;
		$this->model                = $model;
		$this->instructions         = $instructions;
		$this->max_input_characters = $max_input_characters;
		$this->endpoint             = $endpoint;
		$this->log                  = $log;
	}

	/**
	 * Read stored state without contacting the provider.
	 *
	 * @param int $post_id Standard post ID.
	 */
	public function find( int $post_id ): ?Summary {
		return $this->repository->find( $post_id );
	}

	/**
	 * Generate from current saved content.
	 *
	 * @param int      $post_id               Standard post ID.
	 * @param int|null $expected_revision      Revision observed by the editor.
	 * @param bool     $confirm_replace_manual Whether manual work may be replaced.
	 * @return Summary|WP_Error
	 */
	public function generate( int $post_id, ?int $expected_revision, bool $confirm_replace_manual = false ) {
		$current = $this->repository->find( $post_id );

		if ( ( null === $current && null !== $expected_revision )
			|| ( null !== $current && $current->revision() !== $expected_revision )
		) {
			return $this->error( 'persian_ai_summary_revision_conflict' );
		}

		if ( null !== $current && 'manual' === $current->origin() && ! $confirm_replace_manual ) {
			return $this->error( 'persian_ai_summary_invalid_request' );
		}

		$saved_content = $this->repository->get_saved_post_content( $post_id );

		if ( null === $saved_content ) {
			return $this->error( 'persian_ai_summary_invalid_request' );
		}

		$prepared_text = $this->prepare_content( $saved_content );

		if ( '' === $prepared_text ) {
			return $this->error( 'persian_ai_summary_invalid_request' );
		}

		if ( null !== $this->max_input_characters
			&& mb_strlen( $prepared_text ) > $this->max_input_characters
		) {
			return $this->error( 'persian_ai_summary_content_too_long' );
		}

		$source_hash     = hash( 'sha256', $saved_content );
		$generation_hash = $this->generation_hash( $source_hash, $prepared_text );

		if ( null !== $current
			&& 'generated' === $current->origin()
			&& $generation_hash === $current->generation_hash()
		) {
			return $current;
		}

		$owner = bin2hex( random_bytes( 16 ) );

		if ( ! $this->repository->acquire_lock( $post_id, $owner, $generation_hash, time() + 60 ) ) {
			return $this->error( 'persian_ai_summary_generation_in_progress' );
		}

		try {
			$result = $this->provider->generate( $this->model, $this->instructions, $prepared_text );
			$this->log?->record( $post_id, $result, $this->model );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$now     = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
			$summary = null === $current
				? Summary::generated( $result, $source_hash, $generation_hash, $now )
				: $current->regenerate( $result, $source_hash, $generation_hash, $now );

			if ( ! $this->repository->save( $post_id, $summary, $current ) ) {
				return $this->error( 'persian_ai_summary_summary_changed' );
			}

			return $summary;
		} finally {
			$this->repository->release_lock( $post_id, $owner );
		}
	}

	/**
	 * Save a manual revision of an existing summary.
	 *
	 * @param int    $post_id           Standard post ID.
	 * @param string $text              Candidate plain text.
	 * @param int    $expected_revision Revision observed by the editor.
	 * @return Summary|WP_Error
	 */
	public function edit( int $post_id, string $text, int $expected_revision ) {
		$current = $this->repository->find( $post_id );

		if ( null === $current ) {
			return $this->error( 'persian_ai_summary_not_found' );
		}

		if ( $current->revision() !== $expected_revision ) {
			return $this->error( 'persian_ai_summary_revision_conflict' );
		}

		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		$text = trim( sanitize_textarea_field( wp_strip_all_tags( $text ) ) );

		if ( '' === $text ) {
			return $this->error( 'persian_ai_summary_invalid_request' );
		}

		$summary = $current->edit( $text, new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) );

		return $this->repository->save( $post_id, $summary, $current )
			? $summary
			: $this->error( 'persian_ai_summary_revision_conflict' );
	}

	/**
	 * Explicitly remove an existing summary.
	 *
	 * @param int  $post_id           Standard post ID.
	 * @param int  $expected_revision Revision observed by the editor.
	 * @param bool $confirm           Whether removal was explicitly confirmed.
	 * @return true|WP_Error
	 */
	public function remove( int $post_id, int $expected_revision, bool $confirm ) {
		if ( ! $confirm ) {
			return $this->error( 'persian_ai_summary_invalid_request' );
		}

		$current = $this->repository->find( $post_id );

		if ( null === $current ) {
			return $this->error( 'persian_ai_summary_not_found' );
		}

		if ( $current->revision() !== $expected_revision ) {
			return $this->error( 'persian_ai_summary_revision_conflict' );
		}

		return $this->repository->delete( $post_id, $current )
			? true
			: $this->error( 'persian_ai_summary_revision_conflict' );
	}

	/**
	 * Prepare plain text without content filters or shortcode execution.
	 *
	 * @param string $content Exact saved post content.
	 */
	private function prepare_content( string $content ): string {
		$content = strip_shortcodes( $content );
		$content = preg_replace( '/<\/(?:p|div|h[1-6]|li)>/i', "\n\n", $content ) ?? $content;
		$content = preg_replace( '/<\s*br\s*\/?>/i', "\n", $content ) ?? $content;
		$content = html_entity_decode( wp_strip_all_tags( $content ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$content = preg_replace( '/[\t ]+/', ' ', $content ) ?? $content;
		$content = preg_replace( "/\n{3,}/", "\n\n", $content ) ?? $content;

		return trim( $content );
	}

	/**
	 * Hash every non-secret input that changes generation output.
	 *
	 * @param string $source_hash   Exact saved-content hash.
	 * @param string $prepared_text Prepared provider input.
	 */
	private function generation_hash( string $source_hash, string $prepared_text ): string {
		return hash(
			'sha256',
			(string) wp_json_encode(
				array(
					'provider'     => 'openai_compatible',
					'endpoint'     => $this->endpoint,
					'model'        => $this->model,
					'instructions' => $this->instructions,
					'source_hash'  => $source_hash,
					'content'      => $prepared_text,
				)
			)
		);
	}

	/**
	 * Build a localized application error.
	 *
	 * @param string $code Stable application error code.
	 */
	private function error( string $code ): WP_Error {
		$messages = array(
			'persian_ai_summary_revision_conflict'      => __( 'The summary changed. Reload it and try again.', 'persian-ai-summary' ),
			'persian_ai_summary_invalid_request'        => __( 'The saved post content cannot be summarized.', 'persian-ai-summary' ),
			'persian_ai_summary_not_found'              => __( 'No stored summary was found.', 'persian-ai-summary' ),
			'persian_ai_summary_content_too_long'       => __( 'The saved post content exceeds the configured input limit.', 'persian-ai-summary' ),
			'persian_ai_summary_generation_in_progress' => __( 'A summary is already being generated for this post.', 'persian-ai-summary' ),
			'persian_ai_summary_summary_changed'        => __( 'The summary changed while generation was in progress. Your newer version was kept.', 'persian-ai-summary' ),
		);

		return new WP_Error( $code, $messages[ $code ] );
	}
}
