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

namespace MWPD\BasicScaffold\Tests\Architecture;

use Brain\Monkey;
use Brain\Monkey\Functions;
use MWPD\BasicScaffold\Tests\Fixture\CompositionRoot\BrokenPlugin;
use MWPD\BasicScaffold\Tests\Fixture\CompositionRoot\CallbackPlugin;
use MWPD\BasicScaffold\Tests\Fixture\CompositionRoot\GoodService;
use MWPD\BasicScaffold\Tests\Fixture\CompositionRoot\NotAService;
use stdClass;
use WP_Post;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The composition root keeps the promises it makes about its services.
 */
final class CompositionRootContractTest extends TestCase {

	protected function set_up() {
		parent::set_up();
		Monkey\setUp();

		// WordPress functions the wiring calls, standing in for a request.
		Functions\when( 'get_post' )->justReturn( new WP_Post( new stdClass() ) );
		Functions\when( 'get_stylesheet_directory' )->justReturn( '/nonexistent/child-theme' );
		Functions\when( 'get_template_directory' )->justReturn( '/nonexistent/parent-theme' );
	}

	protected function tear_down() {
		Monkey\tearDown();
		parent::tear_down();
	}

	public function test_the_plugin_keeps_the_promises_of_its_composition_root(): void {
		/**
		 * The composition root of this plugin.
		 *
		 * @var class-string<\MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin> $plugin
		 */
		$plugin   = RepositoryLayout::COMPOSITION_ROOT;
		$problems = ( new CompositionRootContract( $plugin ) )->check();

		$this->assertSame( [], $problems, implode( "\n\n", $problems ) );
	}

	public function test_it_reports_the_promises_a_composition_root_breaks(): void {
		$problems = ( new CompositionRootContract( BrokenPlugin::class ) )->check();
		$prefix   = '[architecture.compositionRootContracts] The service "%s" (%s) of ' . BrokenPlugin::class . ' ';
		$fixtures = 'MWPD\\BasicScaffold\\Tests\\Fixture\\CompositionRoot\\';
		$expected = [
			[ 'hooking', 'HookingService', 'changes hooks while it is being built, directly or through' ],
			[ 'unbound', 'NeedsContract', 'cannot be built from the composition root: ' ],
			[ 'dependent', 'DependentService', 'depends on "missing", which the composition root does not declare.' ],
			[ 'delayed', 'EmptyDelayedService', 'is Delayed, but get_registration_action() returns no action.' ],
			[ 'not-a-service', 'NotAService', 'is not a class implementing Service.' ],
			[ 'removing', 'RemovingService', 'changes hooks while it is being built, directly or through' ],
			[ 'rebound', 'BoundToSomethingElse', 'is built as ' . NotAService::class . ', which is not a Service.' ],
		];

		$this->assertCount( count( $expected ), $problems, implode( "\n\n", $problems ) );

		foreach ( $expected as $index => [ $id, $class_name, $problem ] ) {
			$this->assertStringStartsWith(
				sprintf( $prefix, $id, $fixtures . $class_name ) . $problem,
				$problems[ $index ]
			);
			$this->assertStringContainsString(
				'Rule: docs/architecture-rules.md#architecturecompositionrootcontracts',
				$problems[ $index ]
			);
		}

		$this->assertStringContainsString( 'UnboundContract', $problems[1] );
	}

	public function test_it_watches_what_callable_declarations_do(): void {
		$problems = ( new CompositionRootContract( CallbackPlugin::class ) )->check();
		$prefix   = '[architecture.compositionRootContracts] The service "%s" (%s) of ' . CallbackPlugin::class . ' ';

		$this->assertCount( 2, $problems, implode( "\n\n", $problems ) );
		$this->assertStringStartsWith(
			sprintf( $prefix, 'hooking-callback', GoodService::class ) . 'changes hooks while it is being built',
			$problems[0]
		);
		$this->assertStringStartsWith(
			sprintf( $prefix, 'failing-callback', 'callable' ) . 'cannot be resolved: ',
			$problems[1]
		);
	}
}
