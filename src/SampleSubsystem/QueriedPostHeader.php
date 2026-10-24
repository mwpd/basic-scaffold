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

/**
 * The header of the post the main query asked for.
 *
 * It asks the main loop for the post each time the template calls it, like
 * SampleLoopService does, so it answers for the post being rendered at that
 * moment.
 */
final class QueriedPostHeader implements PostHeader {

	/**
	 * Main loop, asked for the queried post.
	 */
	private MainLoop $main_loop;

	/**
	 * Instantiate a QueriedPostHeader object.
	 *
	 * @param MainLoop $main_loop Main loop to ask for the queried post.
	 */
	public function __construct( MainLoop $main_loop ) {
		$this->main_loop = $main_loop;
	}

	/**
	 * Get the title of the queried post.
	 *
	 * @throws FailedToFindPost If the main query did not ask for a post.
	 *
	 * @return string Title of the post, as plain text.
	 */
	public function title(): string {
		return $this->main_loop->queried_post()->post_title;
	}

	/**
	 * Get the date the queried post was published on.
	 *
	 * @throws FailedToFindPost If the main query did not ask for a post.
	 *
	 * @return string Publication date of the post.
	 */
	public function date(): string {
		return $this->main_loop->queried_post()->post_date;
	}
}
