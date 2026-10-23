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

/**
 * Markup that a presenter vouches for.
 *
 * A template escapes what a presenter returns, unless the presenter returns
 * it as SafeHtml. Whoever creates it is responsible for every part of the
 * markup already being escaped for HTML. It is for HTML only: a value printed
 * into a URL, an attribute or JavaScript still needs the escaping of that
 * context.
 */
final class SafeHtml {

	/**
	 * Markup, escaped already.
	 */
	private string $html;

	/**
	 * Instantiate a SafeHtml object.
	 *
	 * @param string $html Markup, every part of it escaped already.
	 */
	public function __construct( string $html ) {
		$this->html = $html;
	}

	/**
	 * Return the markup.
	 *
	 * @return string Markup, escaped already.
	 */
	public function __toString(): string {
		return $this->html;
	}
}
