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

use PHPStan\PhpDoc\TypeNodeResolver;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStan\Type\FileTypeMapper;

/**
 * Only declared unions of false and another type signal a failure unambiguously.
 *
 * @extends RuleTestCase<NoFalseOnFailureRule>
 */
final class NoFalseOnFailureRuleTest extends RuleTestCase {

	private const FIXTURES = __DIR__ . '/../Fixture/FalseOnFailure/';

	private const FIXTURE_NAMESPACE = 'MWPD\BasicScaffold\Tests\PHPStan\Fixture\FalseOnFailure';

	private const TIP = 'Principle 10: Errors are exceptions, see docs/design-principles.md#10-errors-are-exceptions. '
		. 'Pattern: docs/patterns.md#i-need-to-report-a-failure. '
		. 'Rule: docs/architecture-rules.md#architecturenofalseonfailure';

	protected function getRule(): Rule {
		return new NoFalseOnFailureRule(
			new PluginNamespace( self::FIXTURE_NAMESPACE, [ self::FIXTURE_NAMESPACE . '\Excluded' ] ),
			self::getContainer()->getByType( FileTypeMapper::class ),
			self::getContainer()->getByType( TypeNodeResolver::class )
		);
	}

	public function test_it_reports_declared_false_unions_and_allows_other_returns(): void {
		$this->analyse(
			[ self::FIXTURES . 'returns.php' ],
			[
				[ $this->message( 'Method ' . self::FIXTURE_NAMESPACE . '\Service::value()' ), 10, self::TIP ],
				[ $this->message( 'Method ' . self::FIXTURE_NAMESPACE . '\Service::count()' ), 15, self::TIP ],
				[ $this->message( 'Method ' . self::FIXTURE_NAMESPACE . '\Service::values()' ), 20, self::TIP ],
				[ $this->message( 'Function ' . self::FIXTURE_NAMESPACE . '\value()' ), 52, self::TIP ],
				[ $this->message( 'A closure' ), 58, self::TIP ],
				[ $this->message( 'A closure' ), 64, self::TIP ],
				[ $this->message( 'A closure' ), 70, self::TIP ],
				[ $this->message( 'An arrow function' ), 76, self::TIP ],
				[ $this->message( 'An arrow function' ), 80, self::TIP ],
				[ $this->message( 'An arrow function' ), 84, self::TIP ],
				[ $this->message( 'Function ' . self::FIXTURE_NAMESPACE . '\overridden()' ), 100, self::TIP ],
				[ $this->message( 'Function ' . self::FIXTURE_NAMESPACE . '\phpstan_value()' ), 105, self::TIP ],
				[ $this->message( 'Function ' . self::FIXTURE_NAMESPACE . '\psalm_value()' ), 110, self::TIP ],
				[ $this->message( 'Function ' . self::FIXTURE_NAMESPACE . '\null_or_false()' ), 115, self::TIP ],
			]
		);
	}

	public function test_it_ignores_other_plugins_and_excluded_namespaces(): void {
		$this->analyse( [ self::FIXTURES . 'outside.php' ], [] );
	}

	private function message( string $declaration ): string {
		return $declaration . ' declares a return type that unions false with another type.'
			. ' Return the value and throw a dedicated exception on failure. Turn a false returned by a WordPress'
			. ' function into an exception where the call is made.';
	}
}
