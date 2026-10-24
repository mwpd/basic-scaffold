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

use stdClass;
use MWPD\BasicScaffold\Exception\FailedToLoadView;
use MWPD\BasicScaffold\Exception\InvalidPath;

/**
 * The view interface defines how the rendering system works.
 *
 * When you render a view, you can pass a "context" information to it. This
 * context information is that made available to the scope in which the view
 * template is being rendered.
 *
 * As an example, with a default PHP-based view, the context information will be
 * available as properties of the '$this' variable. There, '$this' is not the
 * view itself but a TemplateScope, which offers escaped properties, isset(),
 * raw() and render_partial(), and nothing of the view's internals.
 *
 * @phpstan-require-extends stdClass
 */
interface View extends Renderable {

	/**
	 * Render the current view with a given context.
	 *
	 * In debug mode, a failure surfaces as an exception. In production, the
	 * view renders nothing instead, so that a broken view does not take the
	 * rest of the page down with it.
	 *
	 * The context holds the values the template prints. A presenter answers
	 * the queries the template makes through method calls on `$this`.
	 *
	 * @param array<string, mixed> $context   Context in which to render.
	 * @param ?Presenter           $presenter Presenter whose declared methods the
	 *                                        template can call. Optional.
	 *
	 * @return string Rendered HTML, or an empty string if rendering failed
	 *                outside of debug mode.
	 * @throws FailedToLoadView If the view could not be loaded (in debug mode).
	 */
	public function render( array $context = [], ?Presenter $presenter = null ): string;

	/**
	 * Render a partial view.
	 *
	 * This can be used from within a currently rendered view, to include
	 * nested partials.
	 *
	 * The passed-in context and presenter are optional, and each falls back to
	 * the parent's if omitted.
	 *
	 * @param string                    $path      Path of the partial to render.
	 * @param array<string, mixed>|null $context   Context in which to render the partial.
	 * @param ?Presenter                $presenter Presenter for the partial.
	 *
	 * @return string Rendered HTML, or an empty string if rendering failed
	 *                outside of debug mode.
	 * @throws InvalidPath If the provided path was not valid (in debug mode).
	 * @throws FailedToLoadView If the view could not be loaded (in debug mode).
	 */
	public function render_partial( string $path, ?array $context = null, ?Presenter $presenter = null ): string;

	/**
	 * Return the raw value of a context property.
	 *
	 * By default, properties are automatically escaped when accessing them
	 * within the view. This method allows direct access to the raw value
	 * instead to bypass this automatic escaping.
	 *
	 * @param string $property Property for which to return the raw value.
	 * @return mixed Raw context property value.
	 */
	public function raw( $property );

	/**
	 * Return the escaped value of a context property.
	 *
	 * Use the raw() method to skip automatic escaping.
	 *
	 * @param string $property Property for which to return the escaped value.
	 * @return string Escaped context property value.
	 */
	public function __get( string $property );
}
