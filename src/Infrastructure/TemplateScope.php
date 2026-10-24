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

use MWPD\BasicScaffold\Exception\FailedToLoadView;
use MWPD\BasicScaffold\Exception\InvalidPath;

/**
 * What `$this` is inside a view template.
 *
 * A template does not run inside the view object. It runs in a scope that
 * offers exactly the template API below and nothing else, so a context key can
 * never collide with how the view works internally, and `$this` gives no
 * access to the view's state. That keeps templates from depending on the view
 * by accident. It is not a sandbox: a template is trusted PHP code, and
 * reflection or rebinding closures can still get around it. Annotate
 * templates with it:
 *
 *     <?php
 *     use MWPD\BasicScaffold\Infrastructure\TemplateScope;
 *
 *     /** @var TemplateScope $this *\/
 *     ?>
 *     <p><?= $this->title ?></p>
 *
 * Assigning to `$this` inside a template fails the view, like any other
 * template error.
 *
 * When the view is rendered with a Presenter, the template can also call the
 * methods the presenter's interface declares. Name both in the annotation, so
 * static analysis checks those calls as well:
 *
 *     /** @var TemplateScope&PostHeader $this *\/
 */
interface TemplateScope {

	/**
	 * Return the escaped value of a context property.
	 *
	 * Use the raw() method to skip automatic escaping.
	 *
	 * @param string $property Property for which to return the escaped value.
	 * @return string Escaped context property value.
	 */
	public function __get( string $property );

	/**
	 * Check whether a context property is set, for isset() and empty().
	 *
	 * @param string $property Property to check.
	 * @return bool Whether the property is set and not null.
	 */
	public function __isset( string $property ): bool;

	/**
	 * Return the raw value of a context property.
	 *
	 * The value is not escaped, so the template escapes it for the context it
	 * prints it in.
	 *
	 * @param string $property Property for which to return the raw value.
	 * @return mixed Raw context property value.
	 */
	public function raw( $property );

	/**
	 * Render a partial view.
	 *
	 * The passed-in context and presenter are optional, and each falls back to
	 * the one of the template that renders the partial if omitted.
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
}
