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
use MWPD\BasicScaffold\Infrastructure\Presenter;

/**
 * What the post header template asks about the post it introduces.
 *
 * The template calls these methods on `$this`, and only these: the interface
 * extends Presenter, so its methods are what the template scope forwards. The
 * strings they return are escaped for HTML before the template prints them.
 *
 * Templates and theme overrides of them call these methods by name, so
 * renaming one breaks every override that uses it.
 */
interface PostHeader extends Presenter {

	/**
	 * Get the title of the post.
	 *
	 * @throws FailedToFindPost If there is no post to introduce.
	 *
	 * @return string Title of the post, as plain text.
	 */
	public function title(): string;

	/**
	 * Get the date the post was published on.
	 *
	 * @throws FailedToFindPost If there is no post to introduce.
	 *
	 * @return string Publication date of the post.
	 */
	public function date(): string;
}
