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

use MWPD\BasicScaffold\Exception\InvalidContextProperty;
use MWPD\BasicScaffold\Infrastructure\TemplateScope;
use MWPD\BasicScaffold\Infrastructure\View;

/**
 * The scope a SimpleView renders its template in.
 *
 * Every call is handed on to the view that renders the template, so escaping
 * and the debug-mode policy stay in one place, and a view that overrides them
 * keeps working. The scope itself holds nothing a template could change.
 *
 * The view's own property is private and the template runs without a class
 * scope, so a context key named `view` reaches __get() like any other.
 */
final class SimpleTemplateScope implements TemplateScope {

	/**
	 * View that renders the template.
	 */
	private View $view;

	/**
	 * Instantiate a SimpleTemplateScope object.
	 *
	 * @param View $view View that renders the template.
	 */
	public function __construct( View $view ) {
		$this->view = $view;
	}

	/**
	 * Return the escaped value of a context property.
	 *
	 * @param string $property Property for which to return the escaped value.
	 * @return string Escaped context property value.
	 */
	public function __get( string $property ) {
		return $this->view->__get( $property );
	}

	/**
	 * Check whether a context property is set, for isset() and empty().
	 *
	 * @param string $property Property to check.
	 * @return bool Whether the property is set and not null.
	 */
	public function __isset( string $property ): bool {
		return isset( $this->view->{$property} );
	}

	/**
	 * Refuse an assignment from the template.
	 *
	 * A value assigned to `$this` would skip escaping and outlive the render,
	 * so the template fails instead. What a template needs comes in through
	 * the context.
	 *
	 * @param string $property Property the template tried to assign.
	 * @param mixed  $value    Value the template tried to assign.
	 * @throws InvalidContextProperty Always.
	 */
	public function __set( string $property, $value ): void {
		throw InvalidContextProperty::for_assignment( $property );
	}

	/**
	 * Return the raw value of a context property.
	 *
	 * @param string $property Property for which to return the raw value.
	 * @return mixed Raw context property value.
	 */
	public function raw( $property ) {
		return $this->view->raw( $property );
	}

	/**
	 * Render a partial view.
	 *
	 * @param string                    $path    Path of the partial to render.
	 * @param array<string, mixed>|null $context Context in which to render the partial.
	 * @return string Rendered HTML, or an empty string if rendering failed
	 *                outside of debug mode.
	 */
	public function render_partial( string $path, ?array $context = null ): string {
		return $this->view->render_partial( $path, $context );
	}
}
