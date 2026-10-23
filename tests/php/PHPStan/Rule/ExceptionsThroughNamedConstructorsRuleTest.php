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
 * @extends RuleTestCase<ExceptionsThroughNamedConstructorsRule>
 */
final class ExceptionsThroughNamedConstructorsRuleTest extends RuleTestCase {

	private const FIXTURES = __DIR__ . '/../Fixture/Errors/';

	private const FIXTURE_NAMESPACE = 'MWPD\BasicScaffold\Tests\PHPStan\Fixture\Errors';

	private const TIP = 'Principle 10: Errors are exceptions, see docs/design-principles.md#10-errors-are-exceptions. '
		. 'Pattern: docs/patterns.md#i-need-to-report-a-failure. '
		. 'Rule: docs/architecture-rules.md#architectureexceptionsthroughnamedconstructors';

	protected function getRule(): Rule {
		return new ExceptionsThroughNamedConstructorsRule(
			new PluginNamespace( self::FIXTURE_NAMESPACE ),
			self::FIXTURE_NAMESPACE . '\Exception\PluginException'
		);
	}

	public function test_it_reports_exceptions_created_outside_their_named_constructors(): void {
		$exception = self::FIXTURE_NAMESPACE . '\Exception\GoodException';

		$this->analyse(
			[ self::FIXTURES . 'Service.php', self::FIXTURES . 'Adapter.php' ],
			[
				[
					sprintf(
						'new %1$s creates an exception outside its named constructors. Use %1$s::from_value() or'
						. ' %1$s::from_other() instead, or add a named constructor to %1$s that states the cause.',
						$exception
					),
					63,
					self::TIP,
				],
			]
		);
	}
}
