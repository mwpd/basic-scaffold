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
use PHPStan\Type\FileTypeMapper;

/**
 * @extends RuleTestCase<DependencyDirectionRule>
 */
final class DependencyDirectionRuleTest extends RuleTestCase {

	private const FIXTURES = __DIR__ . '/../Fixture/Layers/';

	private const LAYERS = 'MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers';

	private const TIP = 'Principle 4: SOLID at the top, WordPress at the edges, see '
		. 'docs/design-principles.md#4-solid-at-the-top-wordpress-at-the-edges. '
		. 'Rule: docs/architecture-rules.md#architecturedependencydirection';

	private const EXCEPTION_FIX = ' Exceptions only depend on each other and on PHP; pass what the message needs as a'
		. ' value to the named constructor.';

	private const INFRASTRUCTURE_FIX = ' Generated plugins build on the infrastructure, so it may only depend on itself'
		. ' and the exceptions; add an interface to the infrastructure that the other class implements.';

	private const CONTRACT_FIX = ' Depend on the interface it implements (' . self::LAYERS . '\Infrastructure\Contract)'
		. ' instead, and bind the implementation in the composition root.';

	private const ROOT_FIX = ' Only the composition root (parameters.architecture.dependencyDirection.compositionRoot)'
		. ' knows how the plugin is put together; receive what you need through the constructor.';

	private const TESTS_FIX = ' Production code never depends on the tests; move what it needs into src/.';

	protected function getRule(): Rule {
		return new DependencyDirectionRule(
			self::LAYERS,
			[ self::LAYERS . '\TestDouble' ],
			self::LAYERS . '\Exception',
			self::LAYERS . '\Infrastructure',
			[ self::LAYERS . '\Plugin' ],
			[ (string) realpath( self::FIXTURES . 'views' ) ],
			$this->createReflectionProvider(),
			self::getContainer()->getByType( FileTypeMapper::class )
		);
	}

	public function test_exceptions_only_depend_on_each_other(): void {
		$this->analyse(
			$this->fixtures( 'Exception/LayerException', 'Exception/LeakyException' ),
			[
				$this->error(
					7,
					'Exception\LeakyException',
					'the exceptions',
					'Infrastructure\Contract',
					'the infrastructure',
					self::EXCEPTION_FIX
				),
			]
		);
	}

	public function test_the_infrastructure_only_depends_on_itself_and_the_exceptions(): void {
		$this->analyse(
			$this->fixtures(
				'Infrastructure/Contract',
				'Infrastructure/Concrete',
				'Infrastructure/ImportOnly',
				'Infrastructure/LeakyInfrastructure',
				'Infrastructure/LeakyTrait'
			),
			[
				$this->error(
					7,
					'Infrastructure\ImportOnly',
					'the infrastructure',
					'Feature\Service',
					'the Feature subsystem',
					self::INFRASTRUCTURE_FIX
				),
				$this->error(
					8,
					'Infrastructure\LeakyInfrastructure',
					'the infrastructure',
					'Feature\Service',
					'the Feature subsystem',
					self::INFRASTRUCTURE_FIX
				),
				$this->error(
					9,
					'Infrastructure\LeakyInfrastructure',
					'the infrastructure',
					'Plugin',
					'the composition root',
					self::INFRASTRUCTURE_FIX
				),
				$this->error(
					10,
					'Infrastructure\LeakyTrait',
					'the infrastructure',
					'Feature\Service',
					'the Feature subsystem',
					self::INFRASTRUCTURE_FIX
				),
			]
		);
	}

	public function test_a_subsystem_depends_on_interfaces_exceptions_and_other_subsystems(): void {
		$this->analyse(
			$this->fixtures( 'Feature/Documented', 'Feature/Service', 'Other/Helper' ),
			[
				$this->error(
					7,
					'Feature\Documented',
					'the Feature subsystem',
					'Plugin',
					'the composition root',
					self::ROOT_FIX
				),
				$this->error(
					13,
					'Feature\Documented',
					'the Feature subsystem',
					'Infrastructure\Concrete',
					'the infrastructure',
					self::CONTRACT_FIX
				),
				$this->error(
					8,
					'Feature\Service',
					'the Feature subsystem',
					'Infrastructure\Concrete',
					'the infrastructure',
					self::CONTRACT_FIX
				),
				$this->error(
					11,
					'Feature\Service',
					'the Feature subsystem',
					'Plugin',
					'the composition root',
					self::ROOT_FIX
				),
				$this->error(
					12,
					'Feature\Service',
					'the Feature subsystem',
					'TestDouble\Fake',
					'the tests',
					self::TESTS_FIX
				),
				$this->error(
					49,
					'Feature\class@anonymous',
					'the Feature subsystem',
					'Infrastructure\Concrete',
					'the infrastructure',
					self::CONTRACT_FIX
				),
			]
		);
	}

	public function test_only_the_composition_root_wires_subsystems_and_nothing_depends_on_the_tests(): void {
		$this->analyse(
			$this->fixtures( 'Plugin', 'Stray' ),
			[
				$this->error( 17, 'Plugin', 'the composition root', 'TestDouble\Fake', 'the tests', self::TESTS_FIX ),
				$this->error(
					7,
					'Stray',
					'a top-level class',
					'Feature\Service',
					'the Feature subsystem',
					self::ROOT_FIX
				),
			]
		);
	}

	public function test_view_templates_do_not_depend_on_the_tests(): void {
		$this->analyse(
			$this->fixtures( 'views/notice' ),
			[
				[
					'views/notice.php, in a view template, depends on ' . self::LAYERS . '\TestDouble\Fake, in the'
						. ' tests.' . self::TESTS_FIX,
					3,
					self::TIP,
				],
			]
		);
	}

	/**
	 * @return array<string>
	 */
	private function fixtures( string ...$names ): array {
		return array_map(
			static fn( string $name ): string => (string) realpath( self::FIXTURES . $name . '.php' ),
			$names
		);
	}

	/**
	 * @return array{0: string, 1: int, 2: string}
	 */
	private function error(
		int $line,
		string $name,
		string $from,
		string $dependency,
		string $to,
		string $fix
	): array {
		return [
			sprintf(
				'%1$s\%2$s, in %3$s, depends on %1$s\%4$s, in %5$s.%6$s',
				self::LAYERS,
				$name,
				$from,
				$dependency,
				$to,
				$fix
			),
			$line,
			self::TIP,
		];
	}
}
