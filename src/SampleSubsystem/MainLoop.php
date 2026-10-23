<?php
/**
 * MWPD Basic Plugin Scaffold.
 *
 * @package   MWPD\BasicScaffold
 * @author    Alain Schlesser <alain.schlesser@gmail.com>
 * @license   MIT
 * @link      https://www.mwpd.io/
 * @copyright 2019 Alain Schlesser
 */

declare( strict_types=1 );

namespace MWPD\BasicScaffold\SampleSubsystem;

use MWPD\BasicScaffold\Exception\FailedToFindPost;
use WP_Post;

/**
 * The main loop of a singular view, as a filter callback sees it.
 *
 * Filters such as the_content run for every post WordPress renders: the
 * queried post in the main loop, but also the posts of a related-posts block
 * or a secondary loop, the posts of a feed, and any post WordPress builds an
 * excerpt for. Which one it is changes while the request runs, so a service
 * that only acts on the queried post asks this interface each time its
 * callback runs, instead of reaching into WordPress's globals or keeping a
 * post it was built with.
 */
interface MainLoop {

	/**
	 * Check whether WordPress is rendering the queried post in the main loop
	 * right now, as the content of the page: not in a feed, and not to build
	 * an excerpt.
	 *
	 * @return bool Whether the main loop is rendering the queried post.
	 */
	public function is_rendering_queried_post(): bool;

	/**
	 * Get the post the main query asked for.
	 *
	 * @throws FailedToFindPost If the main query did not ask for a post.
	 *
	 * @return WP_Post Queried post.
	 */
	public function queried_post(): WP_Post;
}
