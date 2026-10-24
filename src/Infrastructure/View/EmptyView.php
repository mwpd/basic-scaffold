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

use MWPD\BasicScaffold\Infrastructure\Presenter;
use MWPD\BasicScaffold\Infrastructure\View;
use stdClass;

/**
 * A view that renders nothing.
 *
 * The built-in view factories hand it out in production when the view that
 * was asked for cannot be created, for example because its template is
 * missing. Failing to create a view then has the same outcome as failing to
 * render one: the view's place on the page stays empty, and the rest of the
 * page keeps working. In debug mode, the factories throw instead.
 *
 * This extends stdClass because the View interface requires it.
 */
final class EmptyView extends stdClass implements View {

	/**
	 * Render the view, which produces no output.
	 *
	 * @param array<string, mixed> $context   Context in which to render. Ignored.
	 * @param ?Presenter           $presenter Presenter for the template. Ignored.
	 *
	 * @return string An empty string.
	 */
	public function render( array $context = [], ?Presenter $presenter = null ): string {
		return '';
	}

	/**
	 * Render a partial view, which produces no output either.
	 *
	 * @param string                    $path      Path of the partial to render. Ignored.
	 * @param array<string, mixed>|null $context   Context in which to render the partial. Ignored.
	 * @param ?Presenter                $presenter Presenter for the partial. Ignored.
	 *
	 * @return string An empty string.
	 */
	public function render_partial( string $path, ?array $context = null, ?Presenter $presenter = null ): string {
		return '';
	}

	/**
	 * Return the raw value of a context property, of which there are none.
	 *
	 * @param string $property Property for which to return the raw value.
	 * @return null No value.
	 */
	public function raw( $property ) {
		return null;
	}

	/**
	 * Return the escaped value of a context property, of which there are none.
	 *
	 * @param string $property Property for which to return the escaped value.
	 * @return string An empty string.
	 */
	public function __get( string $property ) {
		return '';
	}
}
