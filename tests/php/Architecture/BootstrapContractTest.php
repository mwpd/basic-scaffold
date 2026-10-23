<?php
/**
 * MWPD Basic Plugin Scaffold.
 *
 * @package MWPD\BasicScaffold
 */

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Architecture;

use Brain\Monkey;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Including a bootstrap must leave extension points for plugins_loaded.
 *
 * The factory's shared plugin, constants and autoloaders must not leak between
 * includes, or another test could hide work done by the real bootstrap.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class BootstrapContractTest extends TestCase {

	protected function set_up() {
		parent::set_up();
		Monkey\setUp();
	}

	protected function tear_down() {
		Monkey\tearDown();
		parent::tear_down();
	}

	public function test_the_bootstrap_only_registers_the_entry_hooks(): void {
		$problems = ( new BootstrapContract( RepositoryLayout::of_plugin()->get_bootstrap() ) )->check();

		$this->assertSame( [], $problems, implode( "\n\n", $problems ) );
	}

	public function test_it_accepts_only_the_three_entry_hooks_without_running_callbacks(): void {
		$problems = ( new BootstrapContract( $this->fixture( 'valid.php' ) ) )->check();

		$this->assertSame( [], $problems, implode( "\n\n", $problems ) );
	}

	public function test_it_reports_a_filter_applied_at_include_time_with_the_fix(): void {
		$bootstrap = dirname( __DIR__ ) . '/Fixture/BootstrapContract/filters-too-early.php';
		$problems  = ( new BootstrapContract( $bootstrap ) )->check();

		$this->assertSame(
			[
				'[architecture.bootstrapContract] Bootstrap "' . $bootstrap
					. '" calls apply_filters("fixture.services") at include time. Move this call into a service\'s'
					. ' register() or into registration at plugins_loaded.'
					. ' Principle 12: Extension points run at a hook,'
					. ' never at include time, see docs/design-principles.md'
					. '#12-extension-points-run-at-a-hook-never-at-include-time.'
					. ' Pattern: docs/patterns.md#i-need-to-run-code-when-the-plugin-file-loads.'
					. ' Rule: docs/architecture-rules.md#architecturebootstrapcontract.',
			],
			$problems
		);
	}

	public function test_it_reports_every_other_hook_api_call_including_reference_array_variants(): void {
		$bootstrap = $this->fixture( 'unexpected-calls.php' );
		$problems  = ( new BootstrapContract( $bootstrap ) )->check();
		$calls     = [
			'add_action("init")',
			'add_filter("fixture.services")',
			'remove_action("init")',
			'remove_filter("fixture.services")',
			'remove_all_actions("init")',
			'remove_all_filters("fixture.services")',
			'do_action("fixture.loaded")',
			'do_action_ref_array("fixture.loaded")',
			'do_action_deprecated("fixture.loaded")',
			'apply_filters("fixture.services")',
			'apply_filters_ref_array("fixture.services")',
			'apply_filters_deprecated("fixture.services")',
			'has_action("init")',
			'has_filter("fixture.services")',
			'did_action("plugins_loaded")',
			'did_filter("fixture.services")',
			'doing_action("plugins_loaded")',
			'doing_filter("fixture.services")',
			'current_action()',
			'current_filter()',
			'register_uninstall_hook("' . $bootstrap . '")',
		];

		$this->assertSame(
			array_map(
				fn( string $call ): string => $this->call_problem( $bootstrap, $call ),
				$calls
			),
			$problems
		);
	}

	public function test_it_reports_duplicate_registrations_even_for_the_same_callback(): void {
		$bootstrap = $this->fixture( 'duplicates.php' );
		$problems  = ( new BootstrapContract( $bootstrap ) )->check();

		$this->assertSame(
			[
				$this->call_problem( $bootstrap, 'add_action("plugins_loaded")', 'more than once at include time' ),
				$this->call_problem(
					$bootstrap,
					'register_activation_hook("' . $bootstrap . '")',
					'more than once at include time'
				),
				$this->call_problem(
					$bootstrap,
					'register_deactivation_hook("' . $bootstrap . '")',
					'more than once at include time'
				),
			],
			$problems
		);
	}

	public function test_it_requires_all_three_registrations(): void {
		$bootstrap = $this->fixture( 'missing.php' );

		$this->assertSame(
			$this->missing_problems( $bootstrap ),
			( new BootstrapContract( $bootstrap ) )->check()
		);
	}

	public function test_it_rejects_non_callable_callbacks_and_lifecycle_hooks_for_another_file(): void {
		$bootstrap = $this->fixture( 'invalid-registrations.php' );

		$this->assertSame(
			array_merge(
				[
					$this->call_problem( $bootstrap, 'add_action("plugins_loaded")' ),
					$this->call_problem( $bootstrap, 'register_activation_hook("another-plugin.php")' ),
					$this->call_problem( $bootstrap, 'register_deactivation_hook("' . $bootstrap . '")' ),
				],
				$this->missing_problems( $bootstrap )
			),
			( new BootstrapContract( $bootstrap ) )->check()
		);
	}

	private function fixture( string $name ): string {
		return dirname( __DIR__ ) . '/Fixture/BootstrapContract/' . $name;
	}

	private function call_problem( string $bootstrap, string $call, string $when = 'at include time' ): string {
		return '[architecture.bootstrapContract] Bootstrap "' . $bootstrap . '" calls ' . $call . ' ' . $when
			. '. Move this call into a service\'s register() or into registration at plugins_loaded. '
			. RuleManifest::get_explanation( RuleManifest::BOOTSTRAP_CONTRACT ) . '.';
	}

	/** @return array<string> Expected missing-registration diagnostics. */
	private function missing_problems( string $bootstrap ): array {
		return array_map(
			static fn( string $call ): string => '[architecture.bootstrapContract] Bootstrap "' . $bootstrap
				. '" does not register ' . $call . '. Register exactly one plugins_loaded action and the activation'
				. ' and deactivation hooks for this bootstrap, with callable callbacks. '
				. RuleManifest::get_explanation( RuleManifest::BOOTSTRAP_CONTRACT ) . '.',
			[ 'add_action("plugins_loaded")', 'register_activation_hook()', 'register_deactivation_hook()' ]
		);
	}
}
