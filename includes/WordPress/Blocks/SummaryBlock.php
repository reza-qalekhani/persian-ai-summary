<?php
/**
 * Dynamic stored summary block.
 *
 * @package PersianAiSummary
 */

declare(strict_types=1);

namespace PersianAiSummary\WordPress\Blocks;

use PersianAiSummary\Contracts\SummaryRepository;
use PersianAiSummary\WordPress\Admin\Settings;
use WP_Block;

/**
 * Renders the current post's stored summary without provider work.
 */
final class SummaryBlock {
	public const NAME = 'persian-ai-summary/summary';

	/**
	 * Summary persistence.
	 *
	 * @var SummaryRepository
	 */
	private SummaryRepository $repository;

	/**
	 * Frontend presentation settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Store block dependencies.
	 *
	 * @param SummaryRepository $repository Summary persistence.
	 * @param Settings          $settings   Frontend presentation settings.
	 */
	public function __construct( SummaryRepository $repository, Settings $settings ) {
		$this->repository = $repository;
		$this->settings   = $settings;
	}

	/** Register the compiled dynamic block and scoped administrator styles. */
	public function register(): void {
		$type = \WP_Block_Type_Registry::get_instance()->get_registered( self::NAME );

		if ( null === $type ) {
			$type = register_block_type(
				PERSIAN_AI_SUMMARY_DIR . '/build/blocks/summary',
				array( 'render_callback' => array( $this, 'render' ) )
			);
		}

		if ( false === $type ) {
			return;
		}

		foreach ( $type->editor_script_handles as $handle ) {
			wp_set_script_translations(
				$handle,
				'persian-ai-summary',
				PERSIAN_AI_SUMMARY_DIR . '/languages'
			);
		}

		$css    = $this->settings->get()['frontend_css'];
		$handle = $type->style_handles[0] ?? '';

		if ( is_string( $css ) && '' !== $css && '' !== $handle ) {
			if ( ! wp_style_is( $handle, 'registered' ) ) {
				wp_register_style(
					$handle,
					plugins_url( 'build/blocks/summary/style-index.css', PERSIAN_AI_SUMMARY_FILE ),
					array(),
					PERSIAN_AI_SUMMARY_VERSION
				);
			}

			$rule   = '.wp-block-persian-ai-summary-summary{' . $css . '}';
			$inline = wp_styles()->get_data( $handle, 'after' );

			if ( ! is_array( $inline ) || ! in_array( $rule, $inline, true ) ) {
				wp_add_inline_style( $handle, $rule );
			}
		}
	}

	/**
	 * Render stored plain text for the current standard post.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Saved content; intentionally unused.
	 * @param WP_Block             $block      Runtime block and post context.
	 */
	public function render( array $attributes, string $content, WP_Block $block ): string {
		unset( $attributes, $content );

		$post_id   = absint( $block->context['postId'] ?? get_the_ID() );
		$post_type = $block->context['postType'] ?? get_post_type( $post_id );

		if ( 'post' !== $post_type || 0 === $post_id ) {
			return '';
		}

		$summary = $this->repository->find( $post_id );

		if ( null === $summary || '' === trim( $summary->text() ) ) {
			return '';
		}

		$wrapper  = get_block_wrapper_attributes(
			array( 'class' => 'wp-block-persian-ai-summary-summary' )
		);
		$settings = $this->settings->get();
		$title    = '' === $settings['frontend_title']
			? ''
			: sprintf(
				'<%1$s class="wp-block-persian-ai-summary-summary__title">%2$s</%1$s>',
				$settings['title_element'],
				esc_html( $settings['frontend_title'] )
			);

		return sprintf(
			'<div %1$s>%2$s%3$s</div>',
			$wrapper,
			$title,
			wpautop( esc_html( $summary->text() ) )
		);
	}
}
