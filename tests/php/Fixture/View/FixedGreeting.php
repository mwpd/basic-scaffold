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

use LogicException;
use MWPD\BasicScaffold\Infrastructure\View\SafeHtml;

/**
 * A greeting with fixed answers, and a public method no interface declares.
 */
final class FixedGreeting implements Greeting {

	private string $salutation;

	public function __construct( string $salutation = 'Hello' ) {
		$this->salutation = $salutation;
	}

	public function greeting( string $name, string $punctuation = '!' ): string {
		return $this->salutation . ', ' . $name . $punctuation;
	}

	public function is_formal(): bool {
		return true;
	}

	public function emphasis(): SafeHtml {
		return new SafeHtml( '<em>welcome</em>' );
	}

	public function label(): Label {
		return new Label( '<label>' );
	}

	public function count_guests(): int {
		return 3;
	}

	/**
	 * @return list<string>
	 */
	public function guest_list(): array {
		return [ '<b>Ada</b>', 'Grace' ];
	}

	public function note(): ?string {
		return null;
	}

	public function failing(): string {
		throw new LogicException( 'The presenter failed.' );
	}

	public function undeclared(): string {
		return 'reached';
	}
}
