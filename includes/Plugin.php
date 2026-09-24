<?php
/**
 * Plugin composition root.
 *
 * @package PersianAiSummary
 */

declare(strict_types=1);

namespace PersianAiSummary;

use PersianAiSummary\AI\OpenAICompatibleProvider;
use PersianAiSummary\Application\SummaryService;
use PersianAiSummary\WordPress\Admin\Settings;
use PersianAiSummary\WordPress\Admin\PostListColumn;
use PersianAiSummary\WordPress\Admin\SettingsPage;
use PersianAiSummary\WordPress\Blocks\SummaryBlock;
use PersianAiSummary\WordPress\Editor;
use PersianAiSummary\WordPress\Rest\SummaryController;
use PersianAiSummary\WordPress\Storage\ApiCallLog;
use PersianAiSummary\WordPress\Storage\PostMetaSummaryRepository;

/**
 * Constructs plugin dependencies and registers WordPress hooks.
 */
final class Plugin {
	/**
	 * Boot the plugin.
	 */
	public static function boot(): void {
		add_action(
			'init',
			static function (): void {
				load_plugin_textdomain(
					'persian-ai-summary',
					false,
					dirname( plugin_basename( PERSIAN_AI_SUMMARY_FILE ) ) . '/languages'
				);
			},
			0
		);

		$repository = new PostMetaSummaryRepository();
		$settings   = new Settings();
		$values     = $settings->get();
		$endpoint   = is_string( $values['endpoint'] ) ? $values['endpoint'] : '';
		$model      = is_string( $values['model'] ) ? $values['model'] : '';
		$prompt     = is_string( $values['instructions'] ) ? $values['instructions'] : '';
		$max_input  = is_int( $values['max_input_characters'] ) && 0 < $values['max_input_characters']
			? $values['max_input_characters']
			: null;
		$provider   = new OpenAICompatibleProvider( $endpoint, $settings->api_key() );
		$log        = new ApiCallLog();
		$service    = new SummaryService( $repository, $provider, $model, $prompt, $max_input, $endpoint, $log );
		$controller = new SummaryController( $service );
		$editor     = new Editor();
		$page       = new SettingsPage( $settings, $log );
		$post_list  = new PostListColumn( $repository );
		$block      = new SummaryBlock( $repository, $settings );

		add_action( 'init', array( $repository, 'register' ) );
		add_action( 'init', array( $block, 'register' ) );
		add_action( 'admin_init', array( $settings, 'register' ) );
		add_action( 'rest_api_init', array( $controller, 'register_routes' ) );
		$editor->register();
		$page->register();
		$post_list->register();
	}
}
