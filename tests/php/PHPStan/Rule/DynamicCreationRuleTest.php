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
 * @extends RuleTestCase<DynamicCreationRule>
 */
final class DynamicCreationRuleTest extends RuleTestCase {

	private const FIXTURES = __DIR__ . '/../Fixture/Creation/';

	private const CREATION = 'MWPD\BasicScaffold\Tests\PHPStan\Fixture\Creation';

	private const TIP = 'Principle 5: One composition root builds the object graph, see '
		. 'docs/design-principles.md#5-one-composition-root-builds-the-object-graph. '
		. 'Pattern: docs/patterns.md#i-need-to-create-objects-on-demand. '
		. 'Rule: docs/architecture-rules.md#architecturenewonlywhereallowed';

	protected function getRule(): Rule {
		return new DynamicCreationRule(
			new PluginNamespace( self::CREATION ),
			NewOnlyWhereAllowedRuleTest::allowances( $this->createReflectionProvider() )
		);
	}

	public function test_it_reports_classes_chosen_at_runtime_unless_they_are_values(): void {
		$this->analyse(
			[ self::FIXTURES . 'Consumer.php', self::FIXTURES . 'Dynamic.php', self::FIXTURES . 'Builder.php' ],
			[
				[ $this->message( self::CREATION . '\Consumer::create()' ), 25, self::TIP ],
				[ $this->message( self::CREATION . '\Dynamic::create()' ), 18, self::TIP ],
			]
		);
	}

	private function message( string $location ): string {
		return "new with a class chosen at runtime in {$location}. Only factories create objects of a class they"
			. ' are given; ask a factory or the injector for it.';
	}
}
