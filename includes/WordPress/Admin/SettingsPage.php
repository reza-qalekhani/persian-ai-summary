<?php
/**
 * Administrator settings page.
 *
 * @package PersianAiSummary
 */

declare(strict_types=1);

namespace PersianAiSummary\WordPress\Admin;

use PersianAiSummary\AI\OpenAICompatibleProvider;
use PersianAiSummary\WordPress\Storage\ApiCallLog;

/**
 * Renders native settings and isolated credential forms.
 */
final class SettingsPage {
	private const SLUG              = 'persian-ai-summary';
	private const HELP_SLUG         = 'persian-ai-summary-help';
	private const LOG_SLUG          = 'persian-ai-summary-api-logs';
	private const CREDENTIAL_ACTION = 'persian_ai_summary_update_api_key';
	private const TEST_ACTION       = 'persian_ai_summary_test_connection';

	/**
	 * Settings storage.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * API call log storage.
	 *
	 * @var ApiCallLog
	 */
	private ApiCallLog $log;

	/**
	 * Store settings access.
	 *
	 * @param Settings        $settings Settings storage.
	 * @param ApiCallLog|null $log      API call log storage.
	 */
	public function __construct( Settings $settings, ?ApiCallLog $log = null ) {
		$this->settings = $settings;
		$this->log      = $log ?? new ApiCallLog();
	}

	/**
	 * Register the page and credential action.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ), PHP_INT_MAX );
		add_action( 'admin_post_' . self::CREDENTIAL_ACTION, array( $this, 'save_api_key' ) );
		add_action( 'admin_post_' . self::TEST_ACTION, array( $this, 'test_connection' ) );
	}

	/**
	 * Add the settings page as the last top-level admin menu item.
	 */
	public function add_page(): void {
		add_menu_page(
			__( 'Persian AI Summary', 'persian-ai-summary' ),
			__( 'AI Summary', 'persian-ai-summary' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-superhero'
		);

		add_submenu_page(
			self::SLUG,
			__( 'Persian AI Summary', 'persian-ai-summary' ),
			__( 'Settings', 'persian-ai-summary' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'API Call Logs', 'persian-ai-summary' ),
			__( 'API Call Logs', 'persian-ai-summary' ),
			'manage_options',
			self::LOG_SLUG,
			array( $this, 'render_logs' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'AI Summary Help & Guide', 'persian-ai-summary' ),
			__( 'Help & Guide', 'persian-ai-summary' ),
			'manage_options',
			self::HELP_SLUG,
			array( $this, 'render_help' )
		);
	}

	/** Render the recent API call log. */
	public function render_logs(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot manage AI summary settings.', 'persian-ai-summary' ) );
		}

		$logs = $this->log->all();
		?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'API Call Logs', 'persian-ai-summary' ); ?></h1>
		<table class="widefat striped">
		<thead>
			<tr>
			<th scope="col"><?php echo esc_html__( 'Post title', 'persian-ai-summary' ); ?></th>
			<th scope="col"><?php echo esc_html__( 'User', 'persian-ai-summary' ); ?></th>
			<th scope="col"><?php echo esc_html__( 'Date and time', 'persian-ai-summary' ); ?></th>
			<th scope="col"><?php echo esc_html__( 'Request status', 'persian-ai-summary' ); ?></th>
			<th scope="col"><?php echo esc_html__( 'Response code', 'persian-ai-summary' ); ?></th>
			<th scope="col"><?php echo esc_html__( 'Failure reason', 'persian-ai-summary' ); ?></th>
			<th scope="col"><?php echo esc_html__( 'Model', 'persian-ai-summary' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( array() === $logs ) : ?>
			<tr>
				<td colspan="7"><?php echo esc_html__( 'No API calls have been logged yet.', 'persian-ai-summary' ); ?></td>
			</tr>
			<?php else : ?>
				<?php foreach ( $logs as $log ) : ?>
					<?php
					$post_id        = (int) ( $log['post_id'] ?? 0 );
					$user           = get_userdata( (int) ( $log['user_id'] ?? 0 ) );
					$title          = get_the_title( $post_id );
					$link           = get_edit_post_link( $post_id );
					$status         = 'success' === ( $log['status'] ?? '' )
					? __( 'Successful', 'persian-ai-summary' )
					: __( 'Failed', 'persian-ai-summary' );
					$response_code  = isset( $log['response_code'] ) && is_int( $log['response_code'] )
					? (string) $log['response_code']
					: '—';
					$failure_reason = isset( $log['failure_reason'] ) && is_string( $log['failure_reason'] ) && '' !== $log['failure_reason']
					? $log['failure_reason']
					: '—';

					if ( '' === $title ) {
						$title = __( '(Untitled)', 'persian-ai-summary' );
					}
					?>
				<tr>
				<td>
					<?php if ( 0 === $post_id ) : ?>
						<?php echo esc_html__( 'Connection test', 'persian-ai-summary' ); ?>
					<?php elseif ( false === $link ) : ?>
						<?php echo esc_html( $title ); ?>
					<?php else : ?>
					<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( false === $user ? __( 'Unknown user', 'persian-ai-summary' ) : $user->display_name ); ?></td>
				<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) ( $log['created_at'] ?? 0 ) ) ); ?></td>
				<td><?php echo esc_html( $status ); ?></td>
				<td><?php echo esc_html( $response_code ); ?></td>
				<td><?php echo esc_html( $failure_reason ); ?></td>
				<td><code><?php echo esc_html( (string) ( $log['model'] ?? '' ) ); ?></code></td>
				</tr>
			<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
		</table>
	</div>
		<?php
	}

	/** Render the administrator usage guide. */
	public function render_help(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot manage AI summary settings.', 'persian-ai-summary' ) );
		}
		?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'AI Summary Help & Guide', 'persian-ai-summary' ); ?></h1>
		<p><?php echo esc_html__( 'Use this guide to configure the AI service, create summaries, and display them in posts.', 'persian-ai-summary' ); ?></p>

		<h2><?php echo esc_html__( '1. Configure the AI service', 'persian-ai-summary' ); ?></h2>
		<ol>
		<li><?php echo esc_html__( 'Open AI Summary > Settings.', 'persian-ai-summary' ); ?></li>
		<li><?php echo esc_html__( 'Enter an HTTPS Chat Completions endpoint and model, then save the settings.', 'persian-ai-summary' ); ?></li>
		<li><?php echo esc_html__( 'Enter your API key and click Update credential.', 'persian-ai-summary' ); ?></li>
		<li><?php echo esc_html__( 'Click Test API connection and confirm that a success notice appears.', 'persian-ai-summary' ); ?></li>
		</ol>

		<h2><?php echo esc_html__( '2. Generate and edit a summary', 'persian-ai-summary' ); ?></h2>
		<ol>
		<li><?php echo esc_html__( 'Open a post in the block editor and save its latest content.', 'persian-ai-summary' ); ?></li>
		<li><?php echo esc_html__( 'Open the AI Summary panel in the post settings sidebar.', 'persian-ai-summary' ); ?></li>
		<li><?php echo esc_html__( 'Click Generate summary. You can then edit and save, regenerate, or remove the stored summary.', 'persian-ai-summary' ); ?></li>
		</ol>
		<p class="description"><?php echo esc_html__( 'Generation uses saved post content, so save the post first to include recent edits.', 'persian-ai-summary' ); ?></p>

		<h2><?php echo esc_html__( '3. Display the summary', 'persian-ai-summary' ); ?></h2>
		<ol>
		<li><?php echo esc_html__( 'Insert the Stored Post Summary block where the summary should appear.', 'persian-ai-summary' ); ?></li>
		<li><?php echo esc_html__( 'Use the frontend title, title element, and CSS settings to control its presentation.', 'persian-ai-summary' ); ?></li>
		</ol>
		<p class="description"><?php echo esc_html__( 'The block displays only the stored summary for the current standard post and does not call the AI service.', 'persian-ai-summary' ); ?></p>
	</div>
		<?php
	}

	/**
	 * Render native non-secret and credential forms.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot manage AI summary settings.', 'persian-ai-summary' ) );
		}

		$values          = $this->settings->get();
		$api_key         = $this->settings->api_key();
		$api_key_preview = $this->credential_preview( $api_key );
		?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'Persian AI Summary', 'persian-ai-summary' ); ?></h1>
		<?php settings_errors( Settings::OPTION_NAME ); ?>
		<form action="options.php" method="post">
		<?php settings_fields( Settings::OPTION_GROUP ); ?>
		<table class="form-table" role="presentation">
			<tr>
			<th scope="row"><?php echo esc_html__( 'Provider', 'persian-ai-summary' ); ?></th>
			<td>
				<code>openai_compatible</code>
				<p class="description"><?php echo esc_html__( 'This plugin currently supports OpenAI-compatible Chat Completions providers.', 'persian-ai-summary' ); ?></p>
			</td>
			</tr>
			<?php $this->text_field( 'endpoint', __( 'HTTPS Chat Completions endpoint', 'persian-ai-summary' ), $values['endpoint'], 'url', true, __( 'Enter the complete HTTPS URL for the provider Chat Completions endpoint.', 'persian-ai-summary' ) ); ?>
			<?php $this->text_field( 'model', __( 'Model', 'persian-ai-summary' ), $values['model'], 'text', true, __( 'Enter the exact model identifier accepted by the provider.', 'persian-ai-summary' ) ); ?>
			<tr>
			<th scope="row"><label for="persian-ai-summary-instructions"><?php echo esc_html__( 'Default instructions', 'persian-ai-summary' ); ?></label></th>
			<td>
				<textarea class="large-text" rows="6" id="persian-ai-summary-instructions" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[instructions]" required><?php echo esc_textarea( (string) $values['instructions'] ); ?></textarea>
				<p class="description"><?php echo esc_html__( 'These instructions are sent with the saved post content. Available placeholders: {{paragraph_count}}, {{word_count}}, and {{language}}.', 'persian-ai-summary' ); ?></p>
			</td>
			</tr>
			<tr>
			<th scope="row"><label for="persian-ai-summary-limit"><?php echo esc_html__( 'Maximum input characters', 'persian-ai-summary' ); ?></label></th>
			<td>
				<input type="number" min="1" step="1" id="persian-ai-summary-limit" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[max_input_characters]" value="<?php echo esc_attr( null === $values['max_input_characters'] ? '' : (string) $values['max_input_characters'] ); ?>">
				<p class="description"><?php echo esc_html__( 'Optionally limit the post content sent to the provider. Leave blank for no plugin-defined limit.', 'persian-ai-summary' ); ?></p>
			</td>
			</tr>
			<?php $this->text_field( 'frontend_title', __( 'Frontend title', 'persian-ai-summary' ), $values['frontend_title'], 'text', false, __( 'Optional heading displayed above the summary block. Leave blank to hide it.', 'persian-ai-summary' ) ); ?>
			<tr>
			<th scope="row"><label for="persian-ai-summary-title-element"><?php echo esc_html__( 'Title HTML element', 'persian-ai-summary' ); ?></label></th>
			<td>
				<select id="persian-ai-summary-title-element" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[title_element]">
				<?php foreach ( Settings::TITLE_ELEMENTS as $element ) : ?>
					<option value="<?php echo esc_attr( $element ); ?>" <?php selected( $values['title_element'], $element ); ?>><?php echo esc_html( $element ); ?></option>
				<?php endforeach; ?>
				</select>
				<p class="description"><?php echo esc_html__( 'Choose the heading level used when a frontend title is set.', 'persian-ai-summary' ); ?></p>
			</td>
			</tr>
			<tr>
			<th scope="row"><label for="persian-ai-summary-css"><?php echo esc_html__( 'Frontend CSS declarations', 'persian-ai-summary' ); ?></label></th>
			<td>
				<textarea class="large-text code" rows="6" id="persian-ai-summary-css" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[frontend_css]"><?php echo esc_textarea( (string) $values['frontend_css'] ); ?></textarea>
				<p class="description"><?php echo esc_html__( 'Enter declarations only, such as color: #222; padding: 1rem;. Selectors and at-rules are not accepted.', 'persian-ai-summary' ); ?></p>
			</td>
			</tr>
		</table>
		<?php submit_button( __( 'Save settings', 'persian-ai-summary' ) ); ?>
		</form>

		<h2><?php echo esc_html__( 'API credential', 'persian-ai-summary' ); ?></h2>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<input type="hidden" name="action" value="<?php echo esc_attr( self::CREDENTIAL_ACTION ); ?>">
		<?php wp_nonce_field( self::CREDENTIAL_ACTION ); ?>
		<table class="form-table" role="presentation">
			<tr>
			<th scope="row"><label for="persian-ai-summary-api-key"><?php echo esc_html__( 'API key', 'persian-ai-summary' ); ?></label></th>
			<td>
				<input class="regular-text" type="password" id="persian-ai-summary-api-key" name="api_key" value="" autocomplete="new-password">
				<p class="description"><?php echo esc_html__( 'Leave blank to preserve the stored credential.', 'persian-ai-summary' ); ?></p>
			</td>
			</tr>
			<tr>
			<th scope="row"><?php echo esc_html__( 'Stored API key', 'persian-ai-summary' ); ?></th>
			<td>
				<?php if ( '' === $api_key ) : ?>
				<p class="description"><?php echo esc_html__( 'No API key is currently stored.', 'persian-ai-summary' ); ?></p>
				<?php else : ?>
				<code><?php echo esc_html( $api_key_preview ); ?></code>
				<p><label><input type="checkbox" name="remove_api_key" value="1"> <?php echo esc_html__( 'Remove the stored credential', 'persian-ai-summary' ); ?></label></p>
				<?php endif; ?>
			</td>
			</tr>
		</table>
		<?php submit_button( __( 'Update credential', 'persian-ai-summary' ) ); ?>
		</form>

		<h2><?php echo esc_html__( 'API connection', 'persian-ai-summary' ); ?></h2>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<input type="hidden" name="action" value="<?php echo esc_attr( self::TEST_ACTION ); ?>">
		<?php wp_nonce_field( self::TEST_ACTION ); ?>
		<p><?php echo esc_html__( 'Save the endpoint, model, and API key before testing.', 'persian-ai-summary' ); ?></p>
		<?php submit_button( __( 'Test API connection', 'persian-ai-summary' ), 'secondary' ); ?>
		</form>
	</div>
		<?php
	}

	/**
	 * Save the isolated credential after capability and nonce checks.
	 */
	public function save_api_key(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot manage AI summary settings.', 'persian-ai-summary' ) );
		}

		check_admin_referer( self::CREDENTIAL_ACTION );

		$api_key = isset( $_POST['api_key'] ) && is_string( $_POST['api_key'] )
		? sanitize_text_field( wp_unslash( $_POST['api_key'] ) )
		: '';
		$remove  = isset( $_POST['remove_api_key'] ) && '1' === $_POST['remove_api_key'];

		$this->settings->update_api_key( $api_key, $remove );
		wp_safe_redirect( add_query_arg( 'settings-updated', 'true', admin_url( 'admin.php?page=' . self::SLUG ) ) );
		exit;
	}

	/** Test the saved API configuration and return a native settings notice. */
	public function test_connection(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot manage AI summary settings.', 'persian-ai-summary' ) );
		}

		check_admin_referer( self::TEST_ACTION );

		$values  = $this->settings->get();
		$api_key = $this->settings->api_key();

		if ( '' === $values['endpoint'] || '' === $values['model'] || '' === $api_key ) {
			$message = __( 'Save the endpoint, model, and API key before testing.', 'persian-ai-summary' );
			$type    = 'error';
		} else {
			$result = ( new OpenAICompatibleProvider( $values['endpoint'], $api_key ) )->test_connection( $values['model'] );
			$this->log->record( 0, $result, $values['model'] );
			$message = is_wp_error( $result )
			? $result->get_error_message()
			: __( 'The API connection was successful.', 'persian-ai-summary' );
			$type    = is_wp_error( $result ) ? 'error' : 'success';
		}

		add_settings_error( Settings::OPTION_NAME, 'persian_ai_summary_connection_test', $message, $type );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( 'settings-updated', 'true', admin_url( 'admin.php?page=' . self::SLUG ) ) );
		exit;
	}

	/**
	 * Render one escaped single-line option field.
	 *
	 * @param string          $key   Option field key.
	 * @param string          $label Field label.
	 * @param int|string|null $value Stored value.
	 * @param string          $type     Input type.
	 * @param bool            $required    Whether the field is required.
	 * @param string          $description Optional field guidance.
	 */
	private function text_field( string $key, string $label, $value, string $type = 'text', bool $required = true, string $description = '' ): void {
		$id = 'persian-ai-summary-' . $key;
		?>
	<tr>
		<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
		<td>
		<input class="regular-text" type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $value ); ?>" <?php echo $required ? ' required' : ''; ?>>
		<?php if ( '' !== $description ) : ?>
			<p class="description"><?php echo esc_html( $description ); ?></p>
		<?php endif; ?>
		</td>
	</tr>
		<?php
	}

	/**
	 * Mask a stored credential for its administrator-only status display.
	 *
	 * @param string $api_key Stored API key.
	 */
	private function credential_preview( string $api_key ): string {
		if ( 10 >= strlen( $api_key ) ) {
			return str_repeat( '*', strlen( $api_key ) );
		}

		return substr( $api_key, 0, 5 ) . '...' . substr( $api_key, -5 );
	}
}
