<?php
/**
 * OpenAI-compatible summary provider.
 *
 * @package PersianAiSummary
 */

declare(strict_types=1);

namespace PersianAiSummary\AI;

use PersianAiSummary\Contracts\SummaryProvider;
use WP_Error;

/**
 * Sends one bounded synchronous Chat Completions request.
 */
final class OpenAICompatibleProvider implements SummaryProvider {
	private const RESPONSE_LIMIT = 65536;

	/**
	 * Full HTTPS Chat Completions endpoint.
	 *
	 * @var string
	 */
	private string $endpoint;

	/**
	 * Server-side provider credential.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Store server-side connection details.
	 *
	 * @param string $endpoint Full Chat Completions endpoint.
	 * @param string $api_key  Provider credential.
	 */
	public function __construct( string $endpoint, string $api_key ) {
		$this->endpoint = $endpoint;
		$this->api_key  = $api_key;
	}

	/**
	 * Generate a plain-text summary.
	 *
	 * @param string $model        Model identifier.
	 * @param string $instructions System instructions.
	 * @param string $content      Prepared saved-post content.
	 * @return string|WP_Error
	 */
	public function generate( string $model, string $instructions, string $content ) {
		$response = wp_safe_remote_post(
			$this->endpoint,
			array(
				'timeout'             => 45,
				'redirection'         => 0,
				'sslverify'           => true,
				'limit_response_size' => self::RESPONSE_LIMIT,
				'headers'             => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'                => wp_json_encode(
					array(
						'model'    => $model,
						'messages' => array(
							array(
								'role'    => 'system',
								'content' => $instructions,
							),
							array(
								'role'    => 'user',
								'content' => $content,
							),
						),
						'stream'   => false,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$message = strtolower( $response->get_error_message() );

			return str_contains( $message, 'timed out' ) || str_contains( $message, 'timeout' )
				? $this->error( 'service_timeout', null )
				: $this->error( 'service_unavailable', null );
		}

		$status = wp_remote_retrieve_response_code( $response );

		if ( 200 > $status || 300 <= $status ) {
			return $this->http_error( $status );
		}

		$document = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $document ) ) {
			return $this->error( 'invalid_response', $status );
		}

		$content = $document['choices'][0]['message']['content'] ?? null;
		$finish  = $document['choices'][0]['finish_reason'] ?? null;

		if ( ! is_string( $content ) || 'stop' !== $finish ) {
			return $this->error( 'invalid_response', $status );
		}

		$content = $this->plain_text( $content );

		return '' === $content ? $this->error( 'invalid_response', $status ) : $content;
	}

	/**
	 * Verify the saved endpoint, credential, and model with a minimal request.
	 *
	 * @param string $model Model identifier.
	 * @return true|WP_Error
	 */
	public function test_connection( string $model ) {
		$result = $this->generate( $model, 'Reply with OK only.', 'Connection test.' );

		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * Normalize provider markup while preserving paragraph breaks.
	 *
	 * @param string $content Provider content.
	 */
	private function plain_text( string $content ): string {
		$content = preg_replace( '/<\s*br\s*\/?>/i', "\n", $content ) ?? $content;
		$content = preg_replace( '/<\/(?:p|div|h[1-6]|li)>/i', "\n\n", $content ) ?? $content;
		$content = wp_specialchars_decode( wp_strip_all_tags( $content ), ENT_QUOTES );
		$content = sanitize_textarea_field( $content );
		$content = preg_replace( "/[\t ]+\n/", "\n", $content ) ?? $content;
		$content = preg_replace( "/\n{3,}/", "\n\n", $content ) ?? $content;

		return trim( $content );
	}

	/**
	 * Map an HTTP failure without retaining its response body.
	 *
	 * @param int $status HTTP status.
	 */
	private function http_error( int $status ): WP_Error {
		if ( 401 === $status || 403 === $status ) {
			return $this->error( 'invalid_credentials', $status );
		}

		if ( 429 === $status ) {
			return $this->error( 'rate_limited', $status );
		}

		if ( 400 <= $status && 500 > $status ) {
			return $this->error( 'invalid_request', $status );
		}

		return $this->error( 'service_unavailable', $status );
	}

	/**
	 * Build a localized provider-neutral error.
	 *
	 * @param string   $code          Stable provider error code.
	 * @param int|null $response_code HTTP response code, if available.
	 */
	private function error( string $code, ?int $response_code ): WP_Error {
		$messages = array(
			'service_timeout'     => __( 'The AI service timed out. Please try again.', 'persian-ai-summary' ),
			'service_unavailable' => __( 'The AI service is unavailable. Please try again later.', 'persian-ai-summary' ),
			'invalid_credentials' => __( 'The AI service rejected the configured credentials.', 'persian-ai-summary' ),
			'rate_limited'        => __( 'The AI service rate limit was reached. Please try again later.', 'persian-ai-summary' ),
			'invalid_request'     => __( 'The AI service rejected the summary request.', 'persian-ai-summary' ),
			'invalid_response'    => __( 'The AI service returned an invalid or incomplete response.', 'persian-ai-summary' ),
		);

		return new WP_Error( $code, $messages[ $code ], array( 'response_code' => $response_code ) );
	}
}
