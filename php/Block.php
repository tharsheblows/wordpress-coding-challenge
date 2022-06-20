<?php
/**
 * Block class.
 *
 * @package SiteCounts
 */

namespace XWP\SiteCounts;

use WP_Block;
use WP_Query;

/**
 * The Site Counts dynamic block.
 *
 * Registers and renders the dynamic block.
 */
class Block {

	/**
	 * The Plugin instance.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Instantiates the class.
	 *
	 * @param Plugin $plugin The plugin object.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Adds the action to register the block.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', [ $this, 'register_block' ] );
	}

	/**
	 * Registers the block.
	 */
	public function register_block() {
		register_block_type_from_metadata(
			$this->plugin->dir(),
			[
				'render_callback' => [ $this, 'render_callback' ],
			]
		);
	}

	/**
	 * Renders the block.
	 *
	 * @param array    $attributes The attributes for the block.
	 * @param string   $content    The block content, if any.
	 * @param WP_Block $block      The instance of this block.
	 * @return string The markup of the block.
	 */
	public function render_callback( $attributes, $content, $block ) {

		$category_name = 'baz';
		$tag_name      = 'foo';

		$current_post_id           = get_the_ID();
		$current_post_has_category = has_category( $category_name );
		$current_post_has_tag      = has_tag( $tag_name );
		$current_post_time         = get_the_time( 'H' );
		$current_post_type         = get_post_type();

		// Could the current post be returned in the query below.
		$current_post_maybe_in_query = (
			$current_post_has_category &&
			$current_post_has_tag &&
			(int) $current_post_time >= 9 &&
			(int) $current_post_time <= 17 &&
			'post' === $current_post_type
		);

		// If the current post could be in the query below, cache the results separately.
		if ( $current_post_maybe_in_query ) {
			$cache_key = "xwp_site_counts_block_post{$current_post_id}";
		} else {
			$cache_key = 'xwp_site_counts_block';
		}

		$cached_post_list = wp_cache_get( $cache_key, 'site-counts' );

		if ( $cached_post_list ) {
			return $cached_post_list;
		}

		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		$class_name = $attributes['className'] ?? '';

		ob_start();

		?>
		<div class="<?php echo esc_attr( $class_name ); ?>">
			<h2><?php _e( 'Post Counts', 'site-counts' ); ?></h2>
			<ul>
			<?php
			foreach ( $post_types as $post_type => $post_type_details ) :

				// There is an assumption here that we only want the number of published posts for a given post type.
				$post_count      = wp_count_posts( $post_type );
				$published_count = $post_count->publish;

				// Only show the post types which have posts.
				if ( $published_count > 0 ) :
					?>
					<li>
					<?php

					$post_count_string = sprintf(
						/* translators: 1: Number of published posts 2: Singular name of post type 3: Plural name of post type */
						_n( 'There is %1$s %2$s.', 'There are %1$s %3$s.', $published_count, 'site-counts' ), // phpcs:ignore
						number_format_i18n( $published_count ),
						$post_type_details->labels->singular_name,
						$post_type_details->labels->name
					);

					echo esc_html( $post_count_string );
					?>
					</li>
					<?php
				endif;
			endforeach;
			?>

			</ul>

			<p>
			<?php

			/* translators: %s: current post ID */
			$current_post_string = sprintf( __( 'The current post ID is %s.', 'site-counts' ), $current_post_id );
			echo esc_html( $current_post_string );
			?>
			</p>

			<?php
			// If the current post could be in the list returned, get an extra post to make up for it.
			$per_page = ( $current_post_maybe_in_query ) ? 6 : 5;
			// Get the posts of post type 'post' with the category 'baz' and the tag 'foo' which were posted between 9am and 5pm.
			$query = new WP_Query(
				[
					// List only public posts.
					'post_status'            => 'public',
					// If the time of the post doesn't matter, remove the date query.
					'date_query'             => [
						[
							'hour'    => 9,
							'compare' => '>=',
						],
						[
							'hour'    => 17,
							'compare' => '<=',
						],
					],
					'tag'                    => $tag_name,
					'category_name'          => $category_name,
					'posts_per_page'         => $per_page,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				]
			);

			$current_post_is_only_post = ( 1 === $query->found_posts && $current_post_maybe_in_query );

			if ( $query->have_posts() && ! $current_post_is_only_post ) :
				?>


			<h2>%s</h2>
				<ul>
				<?php
				$count = 0;
				while ( $query->have_posts() ) :

					// If five posts have already been listed, don't do any more.
					if ( $count > 5 ) {
						break;
					}

					$query->the_post();

					// Don't list the current post.
					if ( get_the_ID() === $current_post_id ) {
						continue;
					}
					?>
					<li><?php echo esc_html( get_the_title() ); ?></li>
					<?php

					$count++;

				endwhile;
			endif;
			?>
			</ul>
		</div>
		<?php
		wp_reset_postdata();

		$post_list_without_heading = ob_get_clean();

		/* translators: %s: number of posts in list */
		$correct_heading = sprintf( _n( '%s post with the tag of foo and the category of baz', '%s posts with the tag of foo and the category of baz', $count, 'site-counts' ), $count );
		$post_list       = sprintf( $post_list_without_heading, esc_html( $correct_heading ) );

		wp_cache_set( $cache_key, $post_list, 'site-counts', 5 * MINUTE_IN_SECONDS );

		return $post_list;
	}
}
