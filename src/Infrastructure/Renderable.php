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
 * Something that can be rendered.
 *
 * This can be used for views, obviously, but could just as well be used for
 * other concepts like blocks or shortcodes, value objects, ...
 */
interface Renderable {

	/**
	 * Render the renderable.
	 *
	 * @param array<string, mixed> $context Optional. Contextual information to use while
	 *                                      rendering. Defaults to an empty array.
	 * @return string Rendered result.
	 */
	public function render( array $context = [] ): string;
}
