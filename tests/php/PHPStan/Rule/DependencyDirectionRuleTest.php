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
 * @extends RuleTestCase<DependencyDirectionRule>
 */
final class DependencyDirectionRuleTest extends RuleTestCase {

	private const FIXTURES = __DIR__ . '/../Fixture/Layers/';

	private const LAYERS = 'MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers';

	private const TIP = 'Principle 4: SOLID at the top, WordPress at the edges, see '
		. 'docs/design-principles.md#4-solid-at-the-top-wordpress-at-the-edges. '
		. 'Rule: docs/architecture-rules.md#architecturedependencydirection';

	private const CONTRACT_FIX = ' Depend on the interface it implements (' . self::LAYERS . '\Infrastructure\Contract)'
		. ' instead, and bind the implementation in the composition root.';

	private const ROOT_FIX = ' Only the composition root (parameters.architecture.dependencyDirection.compositionRoot)'
		. ' knows how the plugin is put together; receive what you need through the constructor.';

	protected function getRule(): Rule {
		return new DependencyDirectionRule(
			self::LAYERS,
			[ self::LAYERS . '\TestDouble' ],
			self::LAYERS . '\Exception',
			self::LAYERS . '\Infrastructure',
			[ self::LAYERS . '\Plugin' ],
			$this->createReflectionProvider()
		);
	}

	public function test_exceptions_only_depend_on_each_other(): void {
		$this->analyse(
			[ self::FIXTURES . 'Exception/LayerException.php', self::FIXTURES . 'Exception/LeakyException.php' ],
			[
				[
					$this->problem(
						'Exception\LeakyException',
						'the exceptions',
						'Infrastructure\Contract',
						'the infrastructure'
					)
						. ' Exceptions only depend on each other and on PHP; pass what the message needs as a value'
						. ' to the named constructor.',
					12,
					self::TIP,
				],
			]
		);
	}

	public function test_the_infrastructure_only_depends_on_itself_and_the_exceptions(): void {
		$fix = ' Generated plugins build on the infrastructure, so it may only depend on itself and the'
			. ' exceptions; add an interface to the infrastructure that the other class implements.';

		$this->analyse(
			[
				self::FIXTURES . 'Infrastructure/Contract.php',
				self::FIXTURES . 'Infrastructure/Concrete.php',
				self::FIXTURES . 'Infrastructure/LeakyInfrastructure.php',
			],
			[
				[
					$this->problem(
						'Infrastructure\LeakyInfrastructure',
						'the infrastructure',
						'Plugin',
						'the composition root'
					) . $fix,
					13,
					self::TIP,
				],
				[
					$this->problem(
						'Infrastructure\LeakyInfrastructure',
						'the infrastructure',
						'Feature\Service',
						'the Feature subsystem'
					) . $fix,
					13,
					self::TIP,
				],
			]
		);
	}

	public function test_a_subsystem_depends_on_interfaces_exceptions_and_other_subsystems(): void {
		$this->analyse(
			[ self::FIXTURES . 'Feature/Service.php', self::FIXTURES . 'Other/Helper.php' ],
			[
				[
					$this->problem(
						'Feature\Service',
						'the Feature subsystem',
						'Infrastructure\Concrete',
						'the infrastructure'
					)
						. self::CONTRACT_FIX,
					25,
					self::TIP,
				],
				[
					$this->problem(
						'Feature\Service',
						'the Feature subsystem',
						'Plugin',
						'the composition root'
					)
						. self::ROOT_FIX,
					38,
					self::TIP,
				],
				[
					$this->problem(
						'Feature\Service',
						'the Feature subsystem',
						'TestDouble\Fake',
						'the tests'
					)
						. ' Production code never depends on the tests; move what it needs into src/.',
					42,
					self::TIP,
				],
				[
					$this->problem(
						'Feature\class@anonymous',
						'the Feature subsystem',
						'Infrastructure\Concrete',
						'the infrastructure'
					)
						. self::CONTRACT_FIX,
					49,
					self::TIP,
				],
			]
		);
	}

	public function test_only_the_composition_root_wires_subsystems(): void {
		$this->analyse(
			[ self::FIXTURES . 'Plugin.php', self::FIXTURES . 'Stray.php' ],
			[
				[
					$this->problem(
						'Stray',
						'a top-level class',
						'Feature\Service',
						'the Feature subsystem'
					) . self::ROOT_FIX,
					11,
					self::TIP,
				],
			]
		);
	}

	private function problem( string $name, string $from, string $dependency, string $to ): string {
		return sprintf(
			'%1$s\%2$s, in %3$s, depends on %1$s\%4$s, in %5$s.',
			self::LAYERS,
			$name,
			$from,
			$dependency,
			$to
		);
	}
}
