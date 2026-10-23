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

namespace MWPD\BasicScaffold\Infrastructure\View;

use MWPD\BasicScaffold\Infrastructure\Service;
use MWPD\BasicScaffold\Infrastructure\Service\DebugMode;
use MWPD\BasicScaffold\Infrastructure\Service\WordPressDebugMode;
use MWPD\BasicScaffold\Infrastructure\View;
use MWPD\BasicScaffold\Infrastructure\ViewFactory;
use Throwable;

/**
 * Factory to create the simplified view objects.
 */
final class SimpleViewFactory implements Service, ViewFactory {

	/**
	 * Debug mode instance to use.
	 */
	private DebugMode $debug_mode;

	/**
	 * Instantiate a SimpleViewFactory object.
	 *
	 * @param ?DebugMode $debug_mode Debug mode instance to use, for the factory
	 *                               and the views it creates. Optional, defaults
	 *                               to WordPressDebugMode.
	 */
	public function __construct( ?DebugMode $debug_mode = null ) {
		$this->debug_mode = $debug_mode ?? new WordPressDebugMode();
	}

	/**
	 * Create a new view object for a given relative path.
	 *
	 * @param string $relative_path Relative path to create the view for.
	 *
	 * @return View Instantiated view object, or a view that renders nothing if
	 *              the view could not be created outside of debug mode.
	 * @throws Throwable If the view could not be created (in debug mode).
	 */
	public function create( string $relative_path ): View {
		try {
			return new SimpleView( $relative_path, $this, $this->debug_mode );
		} catch ( Throwable $throwable ) {
			/*
			 * A view that cannot be created, for example because its template
			 * is missing, fails like one that cannot be rendered: loudly in
			 * debug mode, with the original exception, and with an empty
			 * place on the page in production. The factory returns a View
			 * either way, so the code that renders it is the same in both.
			 */
			if ( $this->debug_mode->is_debug_mode() ) {
				throw $throwable;
			}

			return new EmptyView();
		}
	}
}
