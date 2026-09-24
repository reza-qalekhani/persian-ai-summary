<?php
/**
 * Block editor integration.
 *
 * @package PersianAiSummary
 */

declare(strict_types=1);

namespace PersianAiSummary\WordPress;

/**
 * Loads the summary panel only for standard post editing.
 */
final class Editor {
	private const HANDLE = 'persian-ai-summary-editor';

	/**
	 * Register editor hooks.
	 */
	public function register(): void {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue the compiled editor panel when its asset exists.
	 */
	public function enqueue(): void {
		$screen = get_current_screen();

		if ( null === $screen || 'post' !== $screen->base || 'post' !== $screen->post_type ) {
			return;
		}

		$asset_file  = PERSIAN_AI_SUMMARY_DIR . '/build/editor/index.asset.php';
		$script_file = PERSIAN_AI_SUMMARY_DIR . '/build/editor/index.js';

		if ( ! is_readable( $asset_file ) || ! is_readable( $script_file ) ) {
			return;
		}

		$asset = require $asset_file;

		if ( ! is_array( $asset ) || ! isset( $asset['dependencies'], $asset['version'] ) ) {
			return;
		}

		wp_enqueue_script(
			self::HANDLE,
			plugins_url( 'build/editor/index.js', PERSIAN_AI_SUMMARY_FILE ),
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_set_script_translations(
			self::HANDLE,
			'persian-ai-summary',
			PERSIAN_AI_SUMMARY_DIR . '/languages'
		);
	}
}
