<?php
/**
 * Summary REST routes.
 *
 * @package PersianAiSummary
 */

declare(strict_types=1);

namespace PersianAiSummary\WordPress\Rest;

use PersianAiSummary\Application\SummaryService;
use PersianAiSummary\Domain\Summary;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Exposes authenticated summary reads and explicit generation.
 */
final class SummaryController {
	private const NAMESPACE = 'persian-ai-summary/v1';

	/**
	 * Summary application service.
	 *
	 * @var SummaryService
	 */
	private SummaryService $service;

	/**
	 * Store the application service.
	 *
	 * @param SummaryService $service Summary workflows.
	 */
	public function __construct( SummaryService $service ) {
		$this->service = $service;
	}

	/**
	 * Register summary routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<post_id>\d+)/summary',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_summary' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_summary' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_summary' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<post_id>\d+)/summary/generate',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'generate_summary' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	/**
	 * Return current stored state without generation.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_summary( WP_REST_Request $request ) {
		$post_id = (int) $request['post_id'];
		$valid   = $this->validate_post( $post_id );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$summary = $this->service->find( $post_id );

		return new WP_REST_Response(
			array( 'summary' => null === $summary ? null : $this->serialize( $summary ) ),
			200
		);
	}

	/**
	 * Generate from latest saved post content.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function generate_summary( WP_REST_Request $request ) {
		$post_id = (int) $request['post_id'];
		$valid   = $this->validate_post( $post_id );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$params = $request->get_params();

		if ( ! array_key_exists( 'expectedRevision', $params )
			|| ( null !== $params['expectedRevision']
				&& ( ! is_int( $params['expectedRevision'] ) || 1 > $params['expectedRevision'] )
			)
		) {
			return $this->rest_error( 'persian_ai_summary_invalid_request' );
		}

		if ( isset( $params['confirmReplaceManual'] ) && ! is_bool( $params['confirmReplaceManual'] ) ) {
			return $this->rest_error( 'persian_ai_summary_invalid_request' );
		}

		$result = $this->service->generate(
			$post_id,
			$params['expectedRevision'],
			$params['confirmReplaceManual'] ?? false
		);

		if ( is_wp_error( $result ) ) {
			return $this->rest_error( $result->get_error_code() );
		}

		return new WP_REST_Response( array( 'summary' => $this->serialize( $result ) ), 200 );
	}

	/**
	 * Save a manual summary revision.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_summary( WP_REST_Request $request ) {
		$post_id = (int) $request['post_id'];
		$valid   = $this->validate_post( $post_id );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$params = $request->get_params();

		if ( ! isset( $params['text'], $params['expectedRevision'] )
			|| ! is_string( $params['text'] )
			|| ! is_int( $params['expectedRevision'] )
			|| 1 > $params['expectedRevision']
		) {
			return $this->rest_error( 'persian_ai_summary_invalid_request' );
		}

		$result = $this->service->edit( $post_id, $params['text'], $params['expectedRevision'] );

		return is_wp_error( $result )
			? $this->rest_error( $result->get_error_code() )
			: new WP_REST_Response( array( 'summary' => $this->serialize( $result ) ), 200 );
	}

	/**
	 * Remove a summary after explicit confirmation.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_summary( WP_REST_Request $request ) {
		$post_id = (int) $request['post_id'];
		$valid   = $this->validate_post( $post_id );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$params = $request->get_params();

		if ( ! isset( $params['expectedRevision'], $params['confirm'] )
			|| ! is_int( $params['expectedRevision'] )
			|| 1 > $params['expectedRevision']
			|| ! is_bool( $params['confirm'] )
		) {
			return $this->rest_error( 'persian_ai_summary_invalid_request' );
		}

		$result = $this->service->remove( $post_id, $params['expectedRevision'], $params['confirm'] );

		return is_wp_error( $result )
			? $this->rest_error( $result->get_error_code() )
			: new WP_REST_Response( null, 204 );
	}

	/**
	 * Require authentication and exact-post edit capability.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return true|WP_Error
	 */
	public function check_permission( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in to manage post summaries.', 'persian-ai-summary' ),
				array( 'status' => 401 )
			);
		}

		if ( ! current_user_can( 'edit_post', (int) $request['post_id'] ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You cannot edit the summary for this post.', 'persian-ai-summary' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Require an existing standard post.
	 *
	 * @param int $post_id Candidate post ID.
	 * @return true|WP_Error
	 */
	private function validate_post( int $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return $this->rest_error( 'persian_ai_summary_not_found' );
		}

		return 'post' === $post->post_type
			? true
			: $this->rest_error( 'persian_ai_summary_invalid_request' );
	}

	/**
	 * Serialize only editor-safe summary fields.
	 *
	 * @param Summary $summary Stored summary.
	 * @return array<string, int|string|null>
	 */
	private function serialize( Summary $summary ): array {
		return array(
			'text'        => $summary->text(),
			'origin'      => $summary->origin(),
			'revision'    => $summary->revision(),
			'sourceHash'  => $summary->source_hash(),
			'generatedAt' => $summary->generated_at(),
			'updatedAt'   => $summary->updated_at(),
		);
	}

	/**
	 * Replace internal errors with fixed safe REST responses.
	 *
	 * @param string $code Provider or application error code.
	 */
	private function rest_error( string $code ): WP_Error {
		$errors = array(
			'persian_ai_summary_invalid_request'        => array( 400, __( 'The summary request is invalid.', 'persian-ai-summary' ) ),
			'persian_ai_summary_not_found'              => array( 404, __( 'The requested post or summary was not found.', 'persian-ai-summary' ) ),
			'persian_ai_summary_revision_conflict'      => array( 409, __( 'The summary changed. Reload it and try again.', 'persian-ai-summary' ) ),
			'persian_ai_summary_generation_in_progress' => array( 409, __( 'A summary is already being generated for this post.', 'persian-ai-summary' ) ),
			'persian_ai_summary_summary_changed'        => array( 409, __( 'The summary changed while generation was in progress. Your newer version was kept.', 'persian-ai-summary' ) ),
			'persian_ai_summary_content_too_long'       => array( 422, __( 'The saved post content exceeds the configured input limit.', 'persian-ai-summary' ) ),
			'rate_limited'                              => array( 429, __( 'The AI service rate limit was reached. Please try again later.', 'persian-ai-summary' ) ),
			'invalid_request'                           => array( 400, __( 'The summary request is invalid.', 'persian-ai-summary' ) ),
			'invalid_response'                          => array( 502, __( 'The AI service returned an invalid or incomplete response.', 'persian-ai-summary' ) ),
			'invalid_credentials'                       => array( 503, __( 'The AI service rejected the configured credentials.', 'persian-ai-summary' ) ),
			'service_unavailable'                       => array( 503, __( 'The AI service is unavailable. Please try again later.', 'persian-ai-summary' ) ),
			'service_timeout'                           => array( 504, __( 'The AI service timed out. Please try again.', 'persian-ai-summary' ) ),
		);

		if ( ! isset( $errors[ $code ] ) ) {
			$code = 'service_unavailable';
		}

		list( $status, $message ) = $errors[ $code ];
		$public_code              = str_starts_with( $code, 'persian_ai_summary_' )
			? $code
			: 'persian_ai_summary_' . $code;

		return new WP_Error( $public_code, $message, array( 'status' => $status ) );
	}
}
