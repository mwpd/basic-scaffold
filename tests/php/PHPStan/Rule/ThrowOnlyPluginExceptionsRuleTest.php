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

namespace MWPD\BasicScaffold\Tests\PHPStan\Rule;

use PHPStan\Rules\Rule;

/**
 * @extends ArchitectureRuleTestCase<ThrowOnlyPluginExceptionsRule>
 */
final class ThrowOnlyPluginExceptionsRuleTest extends ArchitectureRuleTestCase {

	private const FIXTURES = __DIR__ . '/../Fixture/Errors/';

	private const FIXTURE_NAMESPACE = 'MWPD\BasicScaffold\Tests\PHPStan\Fixture\Errors';

	private const MARKER = self::FIXTURE_NAMESPACE . '\Exception\PluginException';

	private const TIP = 'Principle 10: Errors are exceptions, see docs/design-principles.md#10-errors-are-exceptions. '
		. 'Rule: docs/architecture-rules.md#architecturethrowonlypluginexceptions';

	protected function getRule(): Rule {
		return new ThrowOnlyPluginExceptionsRule(
			new PluginNamespace( self::FIXTURE_NAMESPACE ),
			self::MARKER,
			self::FIXTURE_NAMESPACE . '\Exception'
		);
	}

	public function test_it_reports_throwing_anything_but_the_plugins_exceptions(): void {
		$this->analyse(
			[ self::FIXTURES . 'Service.php', self::FIXTURES . 'Adapter.php' ],
			[
				[ $this->message( 'RuntimeException' ), 41, self::TIP ],
				[ $this->message( 'LogicException' ), 58, self::TIP ],
				[ $this->message( 'Throwable' ), 82, self::TIP ],
				[ $this->message( 'mixed' ), 85, self::TIP ],
			]
		);
	}

	private function message( string $thrown ): string {
		return sprintf(
			'Throwing %1$s, which does not implement %2$s. Add an exception to %3$s that extends the'
			. ' matching SPL exception, implements %2$s and has a named constructor, and throw that.',
			$thrown,
			self::MARKER,
			self::FIXTURE_NAMESPACE . '\Exception'
		);
	}
}
