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
 * @extends RuleTestCase<NoStaticStateRule>
 */
final class NoStaticStateRuleTest extends RuleTestCase {

	private const FIXTURES = __DIR__ . '/../Fixture/NoStaticState/';

	private const FIXTURE_NAMESPACE = 'MWPD\BasicScaffold\Tests\PHPStan\Fixture\NoStaticState';

	private const FIX = ' Keep the value on an instance and share that instance through the injector with share();'
		. ' only the methods in parameters.architecture.noStaticState.allowed may hold static state.';

	private const TIP = 'Principle 5: One composition root builds the object graph, see '
		. 'docs/design-principles.md#5-one-composition-root-builds-the-object-graph. '
		. 'Rule: docs/architecture-rules.md#architecturenostaticstate';

	protected function getRule(): Rule {
		return new NoStaticStateRule(
			new PluginNamespace( self::FIXTURE_NAMESPACE ),
			[ self::FIXTURE_NAMESPACE . '\PluginFactory::create' ]
		);
	}

	public function test_it_reports_static_properties_and_static_variables(): void {
		$this->analyse(
			[ self::FIXTURES . 'static-state.php' ],
			[
				[ $this->message( 'Static property %s\Registry::$instance' ), 11, self::TIP ],
				[ $this->message( 'Static variable $calls in %s\Registry::cached()' ), 20, self::TIP ],
				[ $this->message( 'Static variable $count in %s\counter()' ), 40, self::TIP ],
			]
		);
	}

	public function test_it_skips_code_outside_the_plugin_namespace(): void {
		$this->analyse( [ self::FIXTURES . 'outside.php' ], [] );
	}

	private function message( string $state ): string {
		return sprintf( $state, self::FIXTURE_NAMESPACE ) . ' keeps state outside any object.' . self::FIX;
	}
}
