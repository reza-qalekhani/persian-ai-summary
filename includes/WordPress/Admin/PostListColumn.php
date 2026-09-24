<?php
/**
 * Posts-list summary column.
 *
 * @package PersianAiSummary
 */

declare(strict_types=1);

namespace PersianAiSummary\WordPress\Admin;

use PersianAiSummary\Contracts\SummaryRepository;

/**
 * Shows whether each standard post has a stored summary.
 */
final class PostListColumn {
	private const COLUMN = 'persian_ai_summary';

	/**
	 * Summary storage.
	 *
	 * @var SummaryRepository
	 */
	private SummaryRepository $repository;

	/**
	 * Store summary access.
	 *
	 * @param SummaryRepository $repository Summary storage.
	 */
	public function __construct( SummaryRepository $repository ) {
		$this->repository = $repository;
	}

	/** Register standard-post list hooks. */
	public function register(): void {
		add_filter( 'manage_post_posts_columns', array( $this, 'add_column' ) );
		add_action( 'manage_post_posts_custom_column', array( $this, 'render' ), 10, 2 );
	}

	/**
	 * Append the summary-state column.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function add_column( array $columns ): array {
		$columns[ self::COLUMN ] = __( 'AI Summary', 'persian-ai-summary' );

		return $columns;
	}

	/**
	 * Render one summary-state cell.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function render( string $column, int $post_id ): void {
		if ( self::COLUMN !== $column ) {
			return;
		}

		echo null === $this->repository->find( $post_id )
			? esc_html__( 'No', 'persian-ai-summary' )
			: esc_html__( 'Yes', 'persian-ai-summary' );
	}
}
