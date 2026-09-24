<?php
/**
 * Plugin Name: Persian AI Summary
 * Plugin URI: https://byreza.net/wordpress/persian-ai-summary-plugin/
 * Description: Generate, edit, store, and display AI summaries for WordPress posts.
 * Version: 1.0.0
 * Requires at least: 6.8
 * Requires PHP: 8.3
 * Author: Reza Qalekhani
 * Author URI: https://byreza.net
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: persian-ai-summary
 * Domain Path: /languages
 *
 * @package PersianAiSummary
 */

declare(strict_types=1);

use PersianAiSummary\Plugin;

defined( 'ABSPATH' ) || exit;

define( 'PERSIAN_AI_SUMMARY_VERSION', '1.0.0' );
define( 'PERSIAN_AI_SUMMARY_FILE', __FILE__ );
define( 'PERSIAN_AI_SUMMARY_DIR', __DIR__ );

$persian_ai_summary_autoload = __DIR__ . '/vendor/autoload.php';

if ( ! is_readable( $persian_ai_summary_autoload ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Persian AI Summary dependencies are missing. Run Composer install or reinstall the packaged plugin.', 'persian-ai-summary' );
			echo '</p></div>';
		}
	);

	return;
}

require_once $persian_ai_summary_autoload;

add_action(
	'plugins_loaded',
	static function (): void {
		Plugin::boot();
	}
);
