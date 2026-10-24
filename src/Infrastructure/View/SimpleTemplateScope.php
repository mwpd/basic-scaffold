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

use Closure;
use MWPD\BasicScaffold\Exception\InvalidContextProperty;
use MWPD\BasicScaffold\Exception\InvalidPresenterMethod;
use MWPD\BasicScaffold\Infrastructure\Presenter;
use MWPD\BasicScaffold\Infrastructure\TemplateScope;
use MWPD\BasicScaffold\Infrastructure\View;

/**
 * The scope a SimpleView renders its template in.
 *
 * Every property read is handed on to the view that renders the template, so
 * escaping and the debug-mode policy stay in one place, and a view that
 * overrides them keeps working. A method call is handed on to the presenter,
 * if its interface declares the method. The scope itself holds nothing a
 * template could change.
 *
 * The scope's own properties are private and the template runs without a
 * class scope, so a context key named `view` reaches __get() like any other.
 */
final class SimpleTemplateScope implements TemplateScope {

	/**
	 * View that renders the template.
	 */
	private View $view;

	/**
	 * Escaping function of the view, for the results of presenter methods.
	 *
	 * @var Closure(mixed): string
	 */
	private Closure $escape;

	/**
	 * Presenter the view was rendered with, if any.
	 */
	private ?Presenter $presenter;

	/**
	 * Methods the presenter's interfaces declare, keyed by their lowercase
	 * name, as PHP method names are case-insensitive.
	 *
	 * @var array<string, string>
	 */
	private array $methods;

	/**
	 * Instantiate a SimpleTemplateScope object.
	 *
	 * @param View                  $view      View that renders the template.
	 * @param Closure               $escape    Escaping function of the view.
	 * @param ?Presenter            $presenter Presenter the view was rendered with, if any.
	 * @param array<string, string> $methods   Methods the presenter's interfaces declare,
	 *                                         keyed by their lowercase name.
	 *
	 * @phpstan-param Closure(mixed): string $escape
	 */
	public function __construct( View $view, Closure $escape, ?Presenter $presenter = null, array $methods = [] ) {
		$this->view      = $view;
		$this->escape    = $escape;
		$this->presenter = $presenter;
		$this->methods   = $methods;
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
	 * Call a method the presenter declares.
	 *
	 * The result is escaped the way a context property is, unless it is
	 * SafeHtml, which the presenter vouches for. A result that is neither a
	 * string nor stringable, such as a boolean, is returned as it is.
	 *
	 * @param string       $method    Method the template called.
	 * @param array<mixed> $arguments Arguments the template passed, by position.
	 * @return mixed Result of the presenter method, escaped if it is printable.
	 * @throws InvalidPresenterMethod If no presenter was passed, or its interfaces
	 *                                do not declare the method.
	 */
	public function __call( string $method, array $arguments ) {
		if ( null === $this->presenter ) {
			throw InvalidPresenterMethod::for_missing_presenter( $method );
		}

		$declared = $this->methods[ \strtolower( $method ) ] ?? null;

		if ( null === $declared ) {
			throw InvalidPresenterMethod::from_method( $method, \get_class( $this->presenter ) );
		}

		$result = $this->presenter->{$declared}( ...$arguments );

		if ( $result instanceof SafeHtml ) {
			return $result;
		}

		if ( \is_string( $result ) || ( \is_object( $result ) && \method_exists( $result, '__toString' ) ) ) {
			return ( $this->escape )( $result );
		}

		return $result;
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
	 * @param string                    $path      Path of the partial to render.
	 * @param array<string, mixed>|null $context   Context in which to render the partial.
	 * @param ?Presenter                $presenter Presenter for the partial.
	 * @return string Rendered HTML, or an empty string if rendering failed
	 *                outside of debug mode.
	 */
	public function render_partial( string $path, ?array $context = null, ?Presenter $presenter = null ): string {
		return $this->view->render_partial( $path, $context, $presenter );
	}
}
