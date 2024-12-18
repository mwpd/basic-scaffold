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

namespace MWPD\BasicScaffold\Infrastructure;

/**
 * The ViewFactory interface is the main access point to the rendering system.
 *
 * The way this works is that you declare a dependency on the ViewFactory
 * interface in the constructor of a class that needs to render something. If
 * this class is instantiated through the dependency injector, it will receive
 * whatever concrete ViewFactory has been configured and can just use that one
 * to create one or more view and subsequently render them.
 */
interface ViewFactory {

	/**
	 * Create a new view object for a given relative path.
	 *
	 * @param string $relative_path Relative path to create the view for.
	 * @return View Instantiated view object.
	 */
	public function create( string $relative_path ): View;
}
