<?php
/**
 * Read-only AI service settings.
 *
 * @package PersianAiSummary
 */

declare(strict_types=1);

namespace PersianAiSummary\WordPress\Admin;

/**
 * Reads public configuration separately from the server-side credential.
 */
final class Settings {
	public const OPTION_NAME           = 'persian_ai_summary_settings';
	public const API_KEY_OPTION_NAME   = 'persian_ai_summary_api_key';
	public const OPTION_GROUP          = 'persian-ai-summary';
	public const TITLE_ELEMENTS        = array( 'h2', 'h3', 'h4', 'h5', 'h6' );
	private const DEFAULT_INSTRUCTIONS = 'Summarize the entire post content in fluent, accurate, and natural {{language}}, using {{paragraph_count}} paragraphs and approximately {{word_count}} words. Preserve the main points, essential information, the author\'s intended purpose, and important conclusions, while removing repetitive, redundant, or minor details. The summary must be self-contained and understandable without referring to the original post. Do not add any information, interpretation, assumptions, opinions, or claims that are not explicitly supported by the source content. Maintain the original meaning and context accurately. Return only the summary as plain text with paragraph breaks, without a title, headings, lists, bullet points, Markdown, HTML, commentary, or any additional text.';

	private const DEFAULTS = array(
		'provider'             => 'openai_compatible',
		'endpoint'             => '',
		'model'                => '',
		'instructions'         => self::DEFAULT_INSTRUCTIONS,
		'max_input_characters' => null,
		'frontend_title'       => '',
		'title_element'        => 'h2',
		'frontend_css'         => '',
	);

	/**
	 * Register native settings and ensure both options are non-autoloaded.
	 */
	public function register(): void {
		add_option( self::OPTION_NAME, self::DEFAULTS, '', false );
		add_option( self::API_KEY_OPTION_NAME, '', '', false );

		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'show_in_rest'      => false,
				'default'           => self::DEFAULTS,
				'sanitize_callback' => array( $this, 'sanitize_for_storage' ),
			)
		);
	}

	/**
	 * Get non-secret settings only.
	 *
	 * @return array<string, int|string|null>
	 */
	public function get(): array {
		$settings = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $settings ) ) {
			return self::DEFAULTS;
		}

		$settings = array_merge( self::DEFAULTS, array_intersect_key( $settings, self::DEFAULTS ) );

		return array(
			'provider'             => 'openai_compatible',
			'endpoint'             => is_string( $settings['endpoint'] ) ? $settings['endpoint'] : '',
			'model'                => is_string( $settings['model'] ) ? $settings['model'] : '',
			'instructions'         => is_string( $settings['instructions'] ) && '' !== trim( $settings['instructions'] )
				? $settings['instructions']
				: self::DEFAULT_INSTRUCTIONS,
			'max_input_characters' => is_int( $settings['max_input_characters'] ) && 0 < $settings['max_input_characters']
				? $settings['max_input_characters']
				: null,
			'frontend_title'       => is_string( $settings['frontend_title'] ) ? $settings['frontend_title'] : '',
			'title_element'        => is_string( $settings['title_element'] ) && in_array( $settings['title_element'], self::TITLE_ELEMENTS, true )
				? $settings['title_element']
				: 'h2',
			'frontend_css'         => is_string( $settings['frontend_css'] ) ? $settings['frontend_css'] : '',
		);
	}

	/**
	 * Validate and sanitize non-secret settings.
	 *
	 * @param mixed $input Candidate settings.
	 * @return array<string, int|string|null>|\WP_Error
	 */
	public function sanitize( $input ) {
		if ( ! is_array( $input ) ) {
			return $this->invalid_settings();
		}

		$endpoint     = isset( $input['endpoint'] ) && is_string( $input['endpoint'] )
			? esc_url_raw( trim( $input['endpoint'] ) )
			: '';
		$model        = isset( $input['model'] ) && is_string( $input['model'] )
			? sanitize_text_field( $input['model'] )
			: '';
		$instructions = isset( $input['instructions'] ) && is_string( $input['instructions'] )
			? trim( sanitize_textarea_field( $input['instructions'] ) )
			: '';
		$limit        = $input['max_input_characters'] ?? null;

		if ( 'https' !== wp_parse_url( $endpoint, PHP_URL_SCHEME )
			|| false === wp_http_validate_url( $endpoint )
			|| '' === $model
			|| '' === $instructions
			|| ( null !== $limit && '' !== $limit && false === filter_var( $limit, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) )
		) {
			return $this->invalid_settings();
		}

		return array(
			'provider'             => 'openai_compatible',
			'endpoint'             => $endpoint,
			'model'                => $model,
			'instructions'         => $instructions,
			'max_input_characters' => null === $limit || '' === $limit ? null : (int) $limit,
			'frontend_title'       => isset( $input['frontend_title'] ) && is_string( $input['frontend_title'] )
				? sanitize_text_field( $input['frontend_title'] )
				: '',
			'title_element'        => isset( $input['title_element'] ) && in_array( $input['title_element'], self::TITLE_ELEMENTS, true )
				? $input['title_element']
				: 'h2',
			'frontend_css'         => isset( $input['frontend_css'] ) && is_string( $input['frontend_css'] )
				? safecss_filter_attr( $input['frontend_css'] )
				: '',
		);
	}

	/**
	 * Settings API sanitization callback that preserves prior state on error.
	 *
	 * @param mixed $input Candidate settings.
	 * @return array<string, int|string|null>
	 */
	public function sanitize_for_storage( $input ): array {
		$sanitized = $this->sanitize( $input );

		if ( is_wp_error( $sanitized ) ) {
			add_settings_error(
				self::OPTION_NAME,
				'persian_ai_summary_invalid_settings',
				$sanitized->get_error_message()
			);
			return $this->get();
		}

		return $sanitized;
	}

	/**
	 * Get the credential for server-side provider construction only.
	 */
	public function api_key(): string {
		$api_key = get_option( self::API_KEY_OPTION_NAME, '' );

		return is_string( $api_key ) ? $api_key : '';
	}

	/**
	 * Preserve, replace, or explicitly remove the isolated credential.
	 *
	 * @param string $candidate Candidate replacement; blank preserves.
	 * @param bool   $remove    Whether removal was explicitly requested.
	 */
	public function update_api_key( string $candidate, bool $remove ): void {
		if ( $remove ) {
			update_option( self::API_KEY_OPTION_NAME, '', false );
			return;
		}

		$candidate = trim( sanitize_text_field( $candidate ) );

		if ( '' !== $candidate ) {
			update_option( self::API_KEY_OPTION_NAME, $candidate, false );
		}
	}

	/**
	 * Build a localized validation error.
	 */
	private function invalid_settings(): \WP_Error {
		return new \WP_Error(
			'persian_ai_summary_invalid_settings',
			__( 'Enter a valid HTTPS endpoint, model, instructions, and optional positive input limit.', 'persian-ai-summary' )
		);
	}
}
