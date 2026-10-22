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
 * @extends RuleTestCase<NewOnlyWhereAllowedRule>
 */
final class NewOnlyWhereAllowedRuleTest extends RuleTestCase {

	private const FIXTURES = __DIR__ . '/../Fixture/Creation/';

	private const CREATION = 'MWPD\BasicScaffold\Tests\PHPStan\Fixture\Creation';

	private const TIP = 'Principle 5: One composition root builds the object graph, see '
		. 'docs/design-principles.md#5-one-composition-root-builds-the-object-graph. '
		. 'Rule: docs/architecture-rules.md#architecturenewonlywhereallowed';

	protected function getRule(): Rule {
		return new NewOnlyWhereAllowedRule(
			new PluginNamespace( self::CREATION ),
			[ self::CREATION . '\Builds' ],
			[ self::CREATION . '\ValueObject', 'DateTimeImmutable' ],
			$this->createReflectionProvider()
		);
	}

	public function test_it_reports_collaborators_created_outside_factories(): void {
		$create = self::CREATION . '\Consumer::create()';

		$this->analyse(
			[ self::FIXTURES . 'Consumer.php' ],
			[
				[ $this->collaborator( self::CREATION . '\Service', $create ), 19, self::TIP ],
				[ $this->collaborator( 'ArrayObject', $create ), 20, self::TIP ],
				[
					"new class() in {$create} creates a collaborator. Write it as a class of its own, inject it"
						. ' through the constructor and bind it in the composition root, or create it in a factory.',
					23,
					self::TIP,
				],
				[
					"new with a class chosen at runtime in {$create}. Only factories create objects of a class they"
						. ' are given; ask a factory or the injector for it.',
					25,
					self::TIP,
				],
				[ $this->collaborator( self::CREATION . '\Service', $create ), 26, self::TIP ],
				[ $this->collaborator( 'WP_Query', $create ), 27, self::TIP ],
			]
		);
	}

	public function test_it_reports_defaults_that_are_not_poka_yoke(): void {
		$constructor = self::CREATION . '\BadDefault::__construct()';

		$this->analyse(
			[ self::FIXTURES . 'BadDefault.php' ],
			[
				[ $this->bad_default( self::CREATION . '\DefaultContract', $constructor, 'contract' ), 14, self::TIP ],
				[ $this->bad_default( self::CREATION . '\Service', $constructor, 'service' ), 15, self::TIP ],
			]
		);
	}

	public function test_it_resolves_anonymous_classes_parent_and_closures(): void {
		$this->analyse(
			[ self::FIXTURES . 'AnonymousDefaults.php', self::FIXTURES . 'Moment.php' ],
			[
				[
					$this->collaborator(
						self::CREATION . '\DefaultContract',
						self::CREATION . '\AnonymousDefaults::__construct()'
					),
					25,
					self::TIP,
				],
			]
		);
	}

	public function test_factories_create_whatever_they_produce(): void {
		$this->analyse(
			[ self::FIXTURES . 'WidgetFactory.php', self::FIXTURES . 'Producer.php', self::FIXTURES . 'Builder.php' ],
			[]
		);
	}

	private function collaborator( string $created, string $location ): string {
		return sprintf(
			'new %1$s in %2$s creates a collaborator. Inject it through the constructor, typed to its interface,'
			. ' and bind it in the composition root; if %1$s is a value object, add it to'
			. ' parameters.architecture.newOnlyWhereAllowed.valueObjects.',
			$created,
			$location
		);
	}

	private function bad_default( string $created, string $location, string $parameter ): string {
		return sprintf(
			'new %1$s in %2$s is a default for $%3$s, but $%3$s is not a nullable parameter typed to an'
			. ' interface %1$s implements. Type it as ?SomeInterface with a default of null, so callers can pass'
			. ' their own implementation.',
			$created,
			$location,
			$parameter
		);
	}
}
