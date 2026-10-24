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

namespace MWPD\BasicScaffold\Tests\Fixture\View;

/**
 * A stringable presenter result.
 */
final class Label {

	private string $text;

	public function __construct( string $text ) {
		$this->text = $text;
	}

	public function __toString(): string {
		return $this->text;
	}
}
