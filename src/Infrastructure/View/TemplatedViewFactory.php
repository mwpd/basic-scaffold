<?php declare( strict_types=1 );

/**
 * MWPD Basic Plugin Scaffold.
 *
 * @package   MWPD\BasicScaffold
 * @author    Alain Schlesser <alain.schlesser@gmail.com>
 * @license   MIT
 * @link      https://www.mwpd.io/
 * @copyright 2019 Alain Schlesser
 */

namespace MWPD\BasicScaffold\Infrastructure\View;

use MWPD\BasicScaffold\Infrastructure\Service;
use MWPD\BasicScaffold\Infrastructure\View;
use MWPD\BasicScaffold\Infrastructure\ViewFactory;

/**
 * A factory to create templated views.
 *
 * If you don't provide the optional locations array, it will default to (in
 * this exact order):
 *  1. child theme folder
 *  2. parent theme folder
 *  3. plugin folder
 */
final class TemplatedViewFactory implements Service, ViewFactory {

	/** @var array<string> */
	private $locations;

	/**
	 * Instantiate a TemplatedViewFactory object.
	 *
	 * @param array $locations Array of locations to use.
	 */
	public function __construct( array $locations = [] ) {
		$this->locations = $locations;
	}

	/**
	 * Create a new view object for a given relative path.
	 *
	 * @param string $relative_path Relative path to create the view for.
	 * @return View Instantiated view object.
	 */
	public function create( string $relative_path ): View {
		return new TemplatedView( $relative_path, $this, $this->locations );
	}
}
