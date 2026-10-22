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
 * @extends RuleTestCase<FinalByDefaultRule>
 */
final class FinalByDefaultRuleTest extends RuleTestCase {

	private const FIXTURES = __DIR__ . '/../Fixture/FinalByDefault/';

	private const FIXTURE_NAMESPACE = 'MWPD\BasicScaffold\Tests\PHPStan\Fixture\FinalByDefault';

	private const TIP = 'Principle 6: Final by default, see docs/design-principles.md#6-final-by-default. '
		. 'Rule: docs/architecture-rules.md#architecturefinalbydefault';

	private string $namespace = self::FIXTURE_NAMESPACE;

	protected function getRule(): Rule {
		return new FinalByDefaultRule(
			new PluginNamespace( $this->namespace, [ self::FIXTURE_NAMESPACE . '\Excluded' ] ),
			[ self::FIXTURE_NAMESPACE . '\DesignedBaseClass' ]
		);
	}

	public function test_it_reports_concrete_classes_that_are_not_final(): void {
		$this->analyse(
			[ self::FIXTURES . 'classes.php' ],
			[
				[ $this->message( self::FIXTURE_NAMESPACE . '\OnlyFinalInDocumentation' ), 25, self::TIP ],
				[ $this->message( self::FIXTURE_NAMESPACE . '\OpenClass' ), 28, self::TIP ],
			]
		);
	}

	public function test_it_skips_excluded_namespaces_and_classes_outside_the_namespace(): void {
		$this->analyse(
			[ self::FIXTURES . 'excluded.php', self::FIXTURES . 'renamed.php' ],
			[]
		);
	}

	public function test_it_matches_namespaces_regardless_of_case(): void {
		$this->analyse(
			[ self::FIXTURES . 'mixed-case.php', self::FIXTURES . 'mixed-case-excluded.php' ],
			[
				[
					$this->message( strtolower( self::FIXTURE_NAMESPACE ) . '\OpenClassInLowercaseNamespace' ),
					7,
					self::TIP,
				],
			]
		);
	}

	public function test_it_follows_a_renamed_namespace(): void {
		$this->namespace = 'Acme\RenamedPlugin';

		$this->analyse(
			[ self::FIXTURES . 'renamed.php', self::FIXTURES . 'classes.php' ],
			[
				[ $this->message( 'Acme\RenamedPlugin\OpenClassInRenamedPlugin' ), 7, self::TIP ],
			]
		);
	}

	private function message( string $class_name ): string {
		return sprintf(
			'Class %s is not final. Declare it final and vary its behaviour through interfaces and injected '
			. 'collaborators; if it is designed as a base class, add it to '
			. 'parameters.architecture.finalByDefault.baseClasses.',
			$class_name
		);
	}
}
