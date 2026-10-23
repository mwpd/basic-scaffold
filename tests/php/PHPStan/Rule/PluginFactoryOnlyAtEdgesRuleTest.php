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
 * @extends RuleTestCase<PluginFactoryOnlyAtEdgesRule>
 */
final class PluginFactoryOnlyAtEdgesRuleTest extends RuleTestCase {

	private const FIXTURES = __DIR__ . '/../Fixture/PluginFactoryOnlyAtEdges/';

	private const FIXTURE_NAMESPACE = 'MWPD\BasicScaffold\Tests\PHPStan\Fixture\PluginFactoryOnlyAtEdges';

	private const FIX = ' Receive what you need through the constructor,'
		. ' or receive the injector for on-demand creation.'
		. ' A static WordPress entry point belongs in a method listed in'
		. ' parameters.architecture.pluginFactoryOnlyAtEdges.bridges; get the graph and delegate immediately'
		. ' to instance services.';

	private const TIP = 'Principle 5: One composition root builds the object graph, see '
		. 'docs/design-principles.md#5-one-composition-root-builds-the-object-graph. '
		. 'Pattern: docs/patterns.md#i-need-to-reach-the-plugin-from-a-static-wordpress-callback. '
		. 'Rule: docs/architecture-rules.md#architecturepluginfactoryonlyatedges';

	protected function getRule(): Rule {
		return new PluginFactoryOnlyAtEdgesRule(
			new PluginNamespace( self::FIXTURE_NAMESPACE, [ self::FIXTURE_NAMESPACE . '\Excluded' ] ),
			[ (string) realpath( self::FIXTURES . 'views' ) . '/' ],
			[
				'\\' . strtoupper( self::FIXTURE_NAMESPACE . '\PluginFactory' ),
				self::FIXTURE_NAMESPACE . '\OtherFactory',
				'MWPD\BasicScaffold\BasicScaffoldPluginFactory',
			],
			[ '\\' . strtoupper( self::FIXTURE_NAMESPACE . '\Bridge::dispatch' ) ]
		);
	}

	public function test_it_reports_factory_calls_and_leaves_unrelated_static_calls_alone(): void {
		$this->analyse(
			[ self::FIXTURES . 'Service.php' ],
			[
				$this->error( 'PluginFactory', 'Service::run()', 11 ),
				$this->error( 'pluginfactory', 'Service::run()', 12 ),
				$this->error( 'PluginFactory', 'Service::run()', 13 ),
				$this->error( 'OtherFactory', 'Service::run()', 14 ),
			]
		);
	}

	public function test_only_the_listed_bridge_method_is_allowed(): void {
		$this->analyse(
			[ self::FIXTURES . 'Bridge.php' ],
			[
				$this->error(
					'PluginFactory',
					'a closure in ' . self::FIXTURE_NAMESPACE . '\Bridge::dispatch()',
					10,
					false
				),
				$this->error( 'PluginFactory', 'Bridge::other()', 14 ),
			]
		);
	}

	public function test_it_resolves_self_static_and_parent_without_exempting_factories(): void {
		$this->analyse(
			[ self::FIXTURES . 'PluginFactory.php', self::FIXTURES . 'ChildFactory.php' ],
			[
				$this->error( 'PluginFactory', 'PluginFactory::again()', 11 ),
				$this->error( 'PluginFactory', 'PluginFactory::again()', 12 ),
				$this->error( 'PluginFactory', 'ChildFactory::again()', 9 ),
			]
		);
	}

	public function test_it_checks_file_scope_functions_and_closures(): void {
		$this->analyse(
			[ self::FIXTURES . 'functions.php' ],
			[
				$this->error( 'PluginFactory', 'the file', 7, false ),
				$this->error( 'PluginFactory', self::FIXTURE_NAMESPACE . '\run()', 9, false ),
				$this->error( 'PluginFactory', 'a closure in the file', 11, false ),
			]
		);
	}

	public function test_it_checks_production_views_and_nested_directories(): void {
		$this->analyse(
			[ self::FIXTURES . 'views/factory.php', self::FIXTURES . 'views/nested/factory.php' ],
			[
				$this->error( 'PluginFactory', 'the file', 3, false ),
				$this->error( 'PluginFactory', 'the file', 3, false ),
			]
		);
	}

	public function test_it_skips_other_namespaces_tests_and_sibling_view_directories(): void {
		$this->analyse(
			[
				self::FIXTURES . 'outside.php',
				self::FIXTURES . 'excluded.php',
				self::FIXTURES . 'views-other/factory.php',
			],
			[]
		);
	}

	public function test_it_allows_the_actual_bootstrap_but_not_files_with_the_same_basename(): void {
		$this->analyse( [ __DIR__ . '/../../../../basic-scaffold.php' ], [] );
		$this->analyse(
			[ self::FIXTURES . 'basic-scaffold.php' ],
			[ $this->error( 'PluginFactory', 'the file', 7, false ) ]
		);
	}

	/**
	 * @return array{string, int, string}
	 */
	private function error( string $factory, string $location, int $line, bool $method = true ): array {
		return [
			'Static call to ' . self::FIXTURE_NAMESPACE . '\\' . $factory . ' in '
				. ( $method ? self::FIXTURE_NAMESPACE . '\\' . $location : $location )
				. ' uses the plugin factory as a service locator.' . self::FIX,
			$line,
			self::TIP,
		];
	}
}
