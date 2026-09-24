<?php
/**
 * API call log storage.
 *
 * @package PersianAiSummary
 */

declare(strict_types=1);

namespace PersianAiSummary\WordPress\Storage;

/**
 * Stores a small, recent audit trail without adding a custom database table.
 */
final class ApiCallLog {
	public const OPTION_NAME = 'persian_ai_summary_api_call_logs';
	private const LIMIT      = 100;

	/**
	 * Record one provider call.
	 *
	 * @param int    $post_id Post ID.
	 * @param mixed  $result  Provider result.
	 * @param string $model   Provider model.
	 */
	public function record( int $post_id, $result, string $model ): void {
		$logs          = $this->all();
		$failed        = is_wp_error( $result );
		$error_data    = $failed ? $result->get_error_data() : null;
		$response_code = is_array( $error_data ) && isset( $error_data['response_code'] ) && is_int( $error_data['response_code'] )
			? $error_data['response_code']
			: ( $failed ? null : 200 );

		array_unshift(
			$logs,
			array(
				'post_id'        => $post_id,
				'user_id'        => get_current_user_id(),
				'created_at'     => time(),
				'status'         => $failed ? 'failed' : 'success',
				'response_code'  => $response_code,
				'failure_reason' => $failed ? $result->get_error_message() : '',
				'model'          => $model,
			)
		);

		// ponytail: A capped option suits low-volume logs; use a table if concurrent or longer retention is needed.
		update_option( self::OPTION_NAME, array_slice( $logs, 0, self::LIMIT ), false );
	}

	/**
	 * Return stored calls, newest first.
	 *
	 * @return array<int, array<string, int|string>>
	 */
	public function all(): array {
		$logs = get_option( self::OPTION_NAME, array() );

		return is_array( $logs ) ? $logs : array();
	}
}
