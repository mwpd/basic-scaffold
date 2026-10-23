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
	 * The result is always a View. When the view cannot be created, for
	 * example because its template is missing, debug mode surfaces the
	 * failure as an exception. In production, the built-in factories return a
	 * view that renders nothing instead (EmptyView), so the code that renders
	 * it does not change.
	 *
	 * @param string $relative_path Relative path to create the view for.
	 * @return View Instantiated view object.
	 */
	public function create( string $relative_path ): View;
}
