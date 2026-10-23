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
use MWPD\BasicScaffold\Tests\Fixture\CompositionRoot\BrokenLifecyclePlugin;
use MWPD\BasicScaffold\Tests\Fixture\CompositionRoot\IdempotentLifecyclePlugin;
use MWPD\BasicScaffold\Tests\Fixture\CompositionRoot\CallbackPlugin;
use MWPD\BasicScaffold\Tests\Fixture\CompositionRoot\GoodService;
use MWPD\BasicScaffold\Tests\Fixture\CompositionRoot\IncompatibleBindingPlugin;
use MWPD\BasicScaffold\Tests\Fixture\CompositionRoot\BoundToSomethingElse;
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
			[
				'rebound',
				'BoundToSomethingElse',
				'cannot be built from the composition root: The delegate for "' . BoundToSomethingElse::class
					. '" returned ' . NotAService::class . ', not an instance of that class.',
			],
		];

		$this->assertCount( count( $expected ), $problems, implode( "\n\n", $problems ) );

		foreach ( $expected as $index => [ $id, $class_name, $problem ] ) {
			$this->assertStringStartsWith(
				sprintf( $prefix, $id, $fixtures . $class_name ) . $problem,
				$problems[ $index ]
			);
			$this->assertStringContainsString(
				'Pattern: docs/patterns.md#i-need-to-add-a-feature. '
					. 'Rule: docs/architecture-rules.md#architecturecompositionrootcontracts',
				$problems[ $index ]
			);
		}

		$this->assertStringContainsString( 'UnboundContract', $problems[1] );
	}

	public function test_it_reports_an_injector_configuration_the_plugin_rejects(): void {
		$problems = ( new CompositionRootContract( IncompatibleBindingPlugin::class ) )->check();

		$this->assertCount( 1, $problems, implode( "\n\n", $problems ) );
		$this->assertStringStartsWith(
			'[architecture.compositionRootContracts] The injector configuration of ' . IncompatibleBindingPlugin::class
				. ' is invalid: "' . BoundToSomethingElse::class . '" cannot be bound to "' . NotAService::class . '"',
			$problems[0]
		);
		$this->assertStringContainsString(
			'Rule: docs/architecture-rules.md#architecturecompositionrootcontracts',
			$problems[0]
		);
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

	public function test_it_reports_non_idempotent_lifecycle_steps(): void {
		$problems = ( new CompositionRootContract( BrokenLifecyclePlugin::class ) )->check();
		$expected = [
			[ 'RepeatingRegistration', 'register', 'changes the callbacks on the action "init"' ],
			[ 'RepeatingRegistration', 'register', 'changes the callbacks on the filter "the_content"' ],
			[ 'ChangingPriorityRegistration', 'register', 'changes the callbacks on the action "init"' ],
			[ 'AppendingActivation', 'activate', 'changes the post meta "fixture_runs" for object 42 again' ],
			[ 'AppendingActivation', 'activate', 'changes the user meta "fixture_runs" for object 42 again' ],
			[ 'AppendingActivation', 'activate', 'changes the term meta "fixture_runs" for object 42 again' ],
			[ 'AppendingActivation', 'activate', 'changes the comment meta "fixture_runs" for object 42 again' ],
			[ 'ChangingDeactivation', 'deactivate', 'changes the option "fixture_runs" again' ],
			[ 'ChangingTransient', 'activate', 'changes the transient "fixture_runs" again' ],
			[ 'ChangingTransient', 'activate', 'changes the user meta "fixture_runs" for object 42 again' ],
		];
		$messages = [];

		foreach ( $expected as [ $service, $method, $effect ] ) {
			$messages[] = $this->lifecycle_problem(
				$service,
				'changes the plugin on the second call of ' . $method . '(): it ' . $effect
				. '. Guard the step so a second run does nothing, or make it set rather than add.'
			);
		}

		$messages[] = $this->lifecycle_problem(
			'ThrowingLifecycle',
			'throws on the first call of register(): registration failed. Make the lifecycle step safe to run twice.'
		);
		$messages[] = $this->lifecycle_problem(
			'ThrowingLifecycle',
			'throws on the second call of activate(): activation failed. Make the lifecycle step safe to run twice.'
		);
		$messages[] = $this->lifecycle_problem(
			'ThrowingLifecycle',
			'throws on the first call of deactivate(): deactivation failed. Make the lifecycle step safe to run twice.'
		);

		$this->assertSame( $messages, $problems );
	}

	public function test_it_accepts_guarded_and_setting_lifecycle_steps(): void {
		$problems = ( new CompositionRootContract( IdempotentLifecyclePlugin::class ) )->check();

		$this->assertSame( [], $problems, implode( "\n\n", $problems ) );
	}

	private function lifecycle_problem( string $service, string $problem ): string {
		return '[architecture.compositionRootContracts] The service "' . $service . '" ('
			. 'MWPD\\BasicScaffold\\Tests\\Fixture\\CompositionRoot\\' . $service . ') of '
			. BrokenLifecyclePlugin::class . ' ' . $problem . ' '
			. RuleManifest::get_explanation( RuleManifest::COMPOSITION_ROOT_CONTRACTS ) . '.';
	}
}
