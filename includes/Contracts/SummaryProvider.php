<?php
/**
 * Provider boundary for summary generation.
 *
 * @package PersianAiSummary
 */

declare(strict_types=1);

namespace PersianAiSummary\Contracts;

use WP_Error;

/**
 * Generates plain-text summaries without exposing provider response shapes.
 */
interface SummaryProvider {
	/**
	 * Generate a summary from prepared saved-post text.
	 *
	 * @param string $model        Provider model identifier.
	 * @param string $instructions Plain-text system instructions.
	 * @param string $content      Prepared saved-post text.
	 * @return string|WP_Error Plain-text summary or normalized provider error.
	 */
	public function generate( string $model, string $instructions, string $content );
}
