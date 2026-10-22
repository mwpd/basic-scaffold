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
use PHPStan\Testing\RuleTestCase;

/**
 * The rules that share the architecture.throwOnlyPluginExceptions
 * identifier, each tested against the same fixtures.
 *
 * @extends RuleTestCase<ThrowOnlyPluginExceptionsRule|CreateOnlyPluginExceptionsRule>
 */
final class ThrowOnlyPluginExceptionsRuleTest extends RuleTestCase {

	private const FIXTURES = __DIR__ . '/../Fixture/Errors/';

	private const FIXTURE_NAMESPACE = 'MWPD\BasicScaffold\Tests\PHPStan\Fixture\Errors';

	private const MARKER = self::FIXTURE_NAMESPACE . '\Exception\PluginException';

	private const EXCEPTIONS = self::FIXTURE_NAMESPACE . '\Exception';

	private const TIP = 'Principle 10: Errors are exceptions, see docs/design-principles.md#10-errors-are-exceptions. '
		. 'Rule: docs/architecture-rules.md#architecturethrowonlypluginexceptions';

	/**
	 * Rule under test.
	 *
	 * @var ThrowOnlyPluginExceptionsRule|CreateOnlyPluginExceptionsRule
	 */
	private Rule $rule;

	protected function getRule(): Rule {
		return $this->rule;
	}

	public function test_it_reports_throwing_anything_but_the_plugins_exceptions(): void {
		$this->rule = new ThrowOnlyPluginExceptionsRule(
			new PluginNamespace( self::FIXTURE_NAMESPACE ),
			self::MARKER,
			self::EXCEPTIONS
		);

		$this->analyse(
			[ self::FIXTURES . 'Service.php' ],
			[
				[
					$this->message( 'Throwing RuntimeException, which does not implement %2$s.', 'throw that' ),
					41,
					self::TIP,
				],
			]
		);
	}

	public function test_it_reports_creating_anything_but_the_plugins_exceptions(): void {
		$this->rule = new CreateOnlyPluginExceptionsRule(
			new PluginNamespace( self::FIXTURE_NAMESPACE ),
			self::MARKER,
			self::EXCEPTIONS
		);

		$this->analyse(
			[ self::FIXTURES . 'Service.php' ],
			[
				[ $this->creation_message( 'RuntimeException' ), 41, self::TIP ],
				[ $this->creation_message( 'LogicException' ), 53, self::TIP ],
				[ $this->creation_message( 'LogicException' ), 94, self::TIP ],
				[ $this->creation_message( 'Throwable' ), 107, self::TIP ],
			]
		);
	}

	private function creation_message( string $created ): string {
		return $this->message(
			'new ' . $created . ' creates an exception that does not implement %2$s.',
			'create that instead'
		);
	}

	private function message( string $problem, string $fix ): string {
		return sprintf(
			$problem . ' Add an exception to %1$s that extends the matching SPL exception, implements %2$s and'
			. ' has a named constructor, and ' . $fix . '.',
			self::EXCEPTIONS,
			self::MARKER
		);
	}
}
