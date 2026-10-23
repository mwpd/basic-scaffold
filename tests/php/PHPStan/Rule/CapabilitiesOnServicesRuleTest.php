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
 * @extends RuleTestCase<CapabilitiesOnServicesRule>
 */
final class CapabilitiesOnServicesRuleTest extends RuleTestCase {

	private const FIXTURES = __DIR__ . '/../Fixture/Capabilities/';

	private const NAMESPACE_ROOT = 'MWPD\BasicScaffold\Tests\PHPStan\Fixture\Capabilities';

	private const TIP = 'Principle 5: One composition root builds the object graph, see '
		. 'docs/design-principles.md#5-one-composition-root-builds-the-object-graph. '
		. 'Pattern: docs/patterns.md#i-need-to-add-a-feature. '
		. 'Rule: docs/architecture-rules.md#architecturecapabilitiesonservices';

	private string $root = self::NAMESPACE_ROOT;

	private string $service = 'MWPD\BasicScaffold\Infrastructure\Service';

	private string $registerable = 'MWPD\BasicScaffold\Infrastructure\Registerable';

	private string $delayed = 'MWPD\BasicScaffold\Infrastructure\Delayed';

	private string $has_assets = 'MWPD\BasicScaffold\Infrastructure\HasAssets';

	/** @var array<string> */
	private array $capabilities = [
		'MWPD\BasicScaffold\Infrastructure\Registerable',
		'MWPD\BasicScaffold\Infrastructure\Conditional',
		'MWPD\BasicScaffold\Infrastructure\Delayed',
		'MWPD\BasicScaffold\Infrastructure\HasDependencies',
		'MWPD\BasicScaffold\Infrastructure\Activateable',
		'MWPD\BasicScaffold\Infrastructure\Deactivateable',
	];

	/** @var array<string> */
	private array $excluded = [];

	private string $infrastructure = 'MWPD\BasicScaffold\Infrastructure';

	/** @var array<string> */
	private array $composition_root = [];

	protected function getRule(): Rule {
		return new CapabilitiesOnServicesRule(
			new PluginNamespace( $this->root, $this->excluded ),
			$this->service,
			$this->capabilities,
			$this->delayed,
			$this->registerable,
			$this->has_assets,
			$this->infrastructure,
			$this->composition_root
		);
	}

	public function test_every_capability_requires_service(): void {
		$capabilities = [
			'Registerable',
			'Conditional',
			'Delayed',
			'HasDependencies',
			'Activateable',
			'Deactivateable',
		];
		foreach ( $capabilities as $name ) {
			$errors = [ [ $this->service_message( self::NAMESPACE_ROOT . '\\' . $name . 'Only' ), 9, self::TIP ] ];
			if ( $name === 'Delayed' ) {
				$errors[] = [ $this->delayed_message( self::NAMESPACE_ROOT . '\DelayedOnly' ), 9, self::TIP ];
			}
			$this->analyse( [ self::FIXTURES . $name . 'Only.php' ], $errors );
		}
	}

	public function test_delayed_services_require_registerable(): void {
		$this->analyse(
			[ self::FIXTURES . 'DelayedService.php' ],
			[ [ $this->delayed_message( self::NAMESPACE_ROOT . '\DelayedService' ), 10, self::TIP ] ]
		);
	}

	public function test_delayed_services_with_assets_have_work_to_run(): void {
		$this->analyse( [ self::FIXTURES . 'DelayedAssetService.php' ], [] );
	}

	public function test_capabilities_inherited_from_a_base_class_are_checked(): void {
		$this->analyse(
			[ self::FIXTURES . 'BaseRegistration.php', self::FIXTURES . 'InheritedRegistration.php' ],
			[
				[ $this->service_message( self::NAMESPACE_ROOT . '\BaseRegistration' ), 9, self::TIP ],
				[ $this->service_message( self::NAMESPACE_ROOT . '\InheritedRegistration' ), 7, self::TIP ],
			]
		);
	}

	public function test_services_and_non_class_declarations_pass(): void {
		$this->analyse(
			array_map(
				static fn( string $name ): string => self::FIXTURES . $name . '.php',
				[ 'Capability', 'Helper', 'OrdinaryClass', 'PlainService', 'BaseService', 'FullService' ]
			),
			[]
		);
	}

	public function test_excluded_and_outside_namespaces_pass(): void {
		$this->excluded = [ self::NAMESPACE_ROOT ];
		$this->analyse(
			[ self::FIXTURES . 'RegisterableOnly.php', self::FIXTURES . 'RenamedPlugin/Feature/MissingTask.php' ],
			[]
		);
	}

	public function test_interfaces_and_namespaces_come_from_configuration(): void {
		$this->root         = self::NAMESPACE_ROOT . '\RenamedPlugin';
		$this->service      = $this->root . '\Lifecycle\Task';
		$this->registerable = $this->root . '\Lifecycle\Hooks';
		$this->delayed      = $this->root . '\Lifecycle\Later';
		$this->capabilities = [ $this->registerable, $this->delayed ];

		$this->analyse(
			[
				self::FIXTURES . 'RenamedPlugin/Feature/MissingTask.php',
				self::FIXTURES . 'RenamedPlugin/Feature/MissingHooks.php',
				self::FIXTURES . 'RenamedPlugin/Feature/FullTask.php',
			],
			[
				[ $this->service_message( $this->root . '\Feature\MissingTask' ), 11, self::TIP ],
				[ $this->delayed_message( $this->root . '\Feature\MissingHooks' ), 11, self::TIP ],
			]
		);
	}

	public function test_the_sample_services_pass(): void {
		$this->root     = 'MWPD\BasicScaffold';
		$this->excluded = [ 'MWPD\BasicScaffold\Tests' ];
		$this->analyse(
			[
				dirname( __DIR__, 4 ) . '/src/SampleSubsystem/SampleBackendService.php',
				dirname( __DIR__, 4 ) . '/src/SampleSubsystem/SampleLoopService.php',
			],
			[]
		);
	}

	public function test_the_infrastructure_and_composition_root_orchestrate_services(): void {
		$this->root             = 'MWPD\BasicScaffold';
		$this->composition_root = [ $this->root . '\BasicScaffoldPlugin' ];
		$this->analyse(
			[
				dirname( __DIR__, 4 ) . '/src/Infrastructure/ServiceBasedPlugin.php',
				dirname( __DIR__, 4 ) . '/src/BasicScaffoldPlugin.php',
			],
			[]
		);
	}

	public function test_composition_root_allowances_only_match_the_configured_classes(): void {
		$this->composition_root = [ self::NAMESPACE_ROOT . '\RegisterableOnly' ];
		$this->analyse(
			[ self::FIXTURES . 'RegisterableOnly.php', self::FIXTURES . 'ConditionalOnly.php' ],
			[ [ $this->service_message( self::NAMESPACE_ROOT . '\ConditionalOnly' ), 9, self::TIP ] ]
		);
	}

	private function service_message( string $class_name ): string {
		return sprintf(
			'Class %s implements a lifecycle capability without %s. Implement %s so the plugin can orchestrate it.',
			$class_name,
			$this->service,
			$this->service
		);
	}

	private function delayed_message( string $class_name ): string {
		return sprintf(
			'Class %s implements %s without %s. Implement %s and add its hooks in register(),'
			. ' or %s to declare its assets, so delayed registration has work to run.',
			$class_name,
			$this->delayed,
			$this->registerable,
			$this->registerable,
			$this->has_assets
		);
	}
}
