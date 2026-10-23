<?php
/**
 * MWPD Basic Plugin Scaffold.
 *
 * @package MWPD\BasicScaffold
 */

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Architecture;

use Brain\Monkey;
use MWPD\BasicScaffold\Tests\Fixture\CompositionRoot\IdenticalRegistration;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Lifecycle state follows WordPress identity rather than Brain Monkey's call log.
 */
final class LifecycleEffectsTest extends TestCase {

	protected function set_up() {
		parent::set_up();
		Monkey\setUp();
	}

	protected function tear_down() {
		Monkey\tearDown();
		parent::tear_down();
	}

	public function test_it_replaces_identical_callbacks_in_every_callable_form(): void {
		$effects = new LifecycleEffects();
		$effects->watch();
		$callbacks = [
			'__return_empty_string',
			[ $this, '__invoke' ],
			[ self::class, 'filter_content' ],
			static fn( string $content ): string => $content,
			$this,
		];

		foreach ( $callbacks as $callback ) {
			add_filter( 'the_content', $callback );
			$before = $effects->snapshot();
			add_filter( 'the_content', $callback );

			$this->assertSame( [], $effects->changes_since( $before ) );
		}

		$before = $effects->snapshot();
		add_filter( 'the_content', self::class . '::filter_content' );
		$this->assertSame( [], $effects->changes_since( $before ) );
	}

	public function test_it_distinguishes_objects_methods_priorities_and_argument_counts(): void {
		$effects = new LifecycleEffects();
		$effects->watch();
		$first  = new IdenticalRegistration();
		$second = new IdenticalRegistration();
		add_filter( 'the_content', [ $first, 'filter' ] );

		foreach ( [ [ $second, 'filter' ], [ $this, '__invoke' ], [ $this, 'filter_content' ] ] as $callback ) {
			$before = $effects->snapshot();
			add_filter( 'the_content', $callback );
			$this->assertSame(
				[ 'changes the callbacks on the filter "the_content"' ],
				$effects->changes_since( $before )
			);
		}

		$before = $effects->snapshot();
		add_filter( 'the_content', [ $first, 'filter' ], 11 );
		$this->assertSame(
			[ 'changes the callbacks on the filter "the_content"' ],
			$effects->changes_since( $before )
		);

		$before = $effects->snapshot();
		add_filter( 'the_content', [ $this, 'filter_content' ], 10, 0 );
		$this->assertSame(
			[ 'changes the callbacks on the filter "the_content"' ],
			$effects->changes_since( $before )
		);
	}

	public function test_removal_changes_state_only_when_the_entry_exists(): void {
		$effects = new LifecycleEffects();
		$effects->watch();
		add_filter( 'the_content', [ $this, '__invoke' ] );
		add_filter( 'the_content', [ $this, '__invoke' ], 11 );
		$before = $effects->snapshot();

		$this->assertFalse( remove_filter( 'the_content', [ $this, '__invoke' ], 12 ) );
		$this->assertSame( [], $effects->changes_since( $before ) );
		$this->assertTrue( remove_filter( 'the_content', [ $this, '__invoke' ] ) );
		$this->assertSame(
			[ 'changes the callbacks on the filter "the_content"' ],
			$effects->changes_since( $before )
		);

		$before = $effects->snapshot();
		$this->assertFalse( remove_filter( 'the_content', [ $this, '__invoke' ] ) );
		$this->assertSame( [], $effects->changes_since( $before ) );
		$this->assertTrue( remove_filter( 'the_content', [ $this, '__invoke' ], 11 ) );
		$this->assertSame(
			[ 'changes the callbacks on the filter "the_content"' ],
			$effects->changes_since( $before )
		);
		$before = $effects->snapshot();
		$this->assertFalse( remove_filter( 'the_content', [ $this, '__invoke' ], 11 ) );
		$this->assertSame( [], $effects->changes_since( $before ) );
	}

	public function test_actions_and_filters_share_the_same_hook_entries(): void {
		$effects = new LifecycleEffects();
		$effects->watch();
		$service = new IdenticalRegistration();
		add_action( 'init', [ $service, 'run' ], 10, 0 );
		$before = $effects->snapshot();
		$this->assertTrue( remove_filter( 'init', [ $service, 'run' ] ) );
		$this->assertSame(
			[ 'changes the callbacks on the action "init"' ],
			$effects->changes_since( $before )
		);
		$before = $effects->snapshot();
		$this->assertFalse( remove_action( 'init', [ $service, 'run' ] ) );
		$this->assertSame( [], $effects->changes_since( $before ) );

		add_filter( 'the_content', [ $this, '__invoke' ] );
		$before = $effects->snapshot();
		$this->assertTrue( remove_action( 'the_content', [ $this, '__invoke' ] ) );
		$this->assertSame(
			[ 'changes the callbacks on the filter "the_content"' ],
			$effects->changes_since( $before )
		);
	}

	public function test_it_still_records_fired_actions_and_filters(): void {
		$effects = new LifecycleEffects();
		$effects->watch();
		do_action( 'init' );
		apply_filters( 'the_content', 'content' );
		$before = $effects->snapshot();
		do_action( 'init' );
		apply_filters( 'the_content', 'content' );

		$this->assertSame(
			[ 'fires the action "init" again', 'fires the filter "the_content" again' ],
			$effects->changes_since( $before )
		);
	}

	public function test_cron_scheduling_replaces_identical_events_and_retains_recurrence(): void {
		$effects = new LifecycleEffects();
		$effects->watch();
		$this->assertTrue( wp_schedule_event( 100, 'daily', 'fixture_event', [ 42 ] ) );
		$this->assertTrue( wp_schedule_single_event( 200, 'fixture_event', [ 42 ] ) );
		$before = $effects->snapshot();

		$this->assertSame(
			[
				100 => [ md5( 'a:1:{i:0;i:42;}' ) => 'daily' ],
				200 => [ md5( 'a:1:{i:0;i:42;}' ) => false ],
			],
			$before['changes the cron events of "fixture_event" again']
		);
		$this->assertTrue( wp_schedule_event( 100, 'daily', 'fixture_event', [ 42 ] ) );
		$this->assertTrue( wp_schedule_single_event( 200, 'fixture_event', [ 42 ] ) );
		$this->assertSame( [], $effects->changes_since( $before ) );

		wp_schedule_event( 100, 'hourly', 'fixture_event', [ 42 ] );
		$this->assertSame(
			[ 'changes the cron events of "fixture_event" again' ],
			$effects->changes_since( $before )
		);
	}

	public function test_cron_events_distinguish_timestamps_arguments_and_hooks(): void {
		$effects = new LifecycleEffects();
		$effects->watch();
		wp_schedule_event( 100, 'daily', 'fixture_event', [ 42 ] );
		$before = $effects->snapshot();
		wp_schedule_event( 200, 'daily', 'fixture_event', [ 42 ] );
		$this->assertSame(
			[ 'changes the cron events of "fixture_event" again' ],
			$effects->changes_since( $before )
		);

		$before = $effects->snapshot();
		wp_schedule_event( 100, 'daily', 'fixture_event', [ '42' ] );
		$this->assertSame(
			[ 'changes the cron events of "fixture_event" again' ],
			$effects->changes_since( $before )
		);

		$before = $effects->snapshot();
		wp_schedule_single_event( 100, 'fixture_other', [ 42 ] );
		$this->assertSame(
			[ 'changes the cron events of "fixture_other" again' ],
			$effects->changes_since( $before )
		);
	}

	public function test_next_scheduled_returns_the_earliest_timestamp_for_the_hook_and_arguments(): void {
		$effects = new LifecycleEffects();
		$effects->watch();
		$this->assertFalse( wp_next_scheduled( 'fixture_event' ) );
		wp_schedule_event( 200, 'daily', 'fixture_event', [ 42 ] );
		wp_schedule_single_event( 100, 'fixture_event', [ 42 ] );
		wp_schedule_single_event( 50, 'fixture_event', [ 43 ] );
		wp_schedule_single_event( 25, 'fixture_other', [ 42 ] );

		$this->assertSame( 100, wp_next_scheduled( 'fixture_event', [ 42 ] ) );
		$this->assertSame( 50, wp_next_scheduled( 'fixture_event', [ 43 ] ) );
		$this->assertSame( 25, wp_next_scheduled( 'fixture_other', [ 42 ] ) );
		$this->assertFalse( wp_next_scheduled( 'fixture_event', [ '42' ] ) );
		$this->assertFalse( wp_next_scheduled( 'fixture_event' ) );
	}

	public function test_clearing_cron_events_matches_arguments_and_counts_removals(): void {
		$effects = new LifecycleEffects();
		$effects->watch();
		wp_schedule_event( 100, 'daily', 'fixture_event', [ 42 ] );
		wp_schedule_single_event( 200, 'fixture_event', [ 42 ] );
		wp_schedule_single_event( 100, 'fixture_event', [ 43 ] );
		wp_schedule_single_event( 100, 'fixture_other', [ 42 ] );
		$before = $effects->snapshot();

		$this->assertSame( 0, wp_clear_scheduled_hook( 'fixture_event' ) );
		$this->assertSame( [], $effects->changes_since( $before ) );
		$this->assertSame( 2, wp_clear_scheduled_hook( 'fixture_event', [ 42 ] ) );
		$this->assertSame(
			[ 'changes the cron events of "fixture_event" again' ],
			$effects->changes_since( $before )
		);
		$this->assertFalse( wp_next_scheduled( 'fixture_event', [ 42 ] ) );
		$this->assertSame( 100, wp_next_scheduled( 'fixture_event', [ 43 ] ) );
		$this->assertSame( 100, wp_next_scheduled( 'fixture_other', [ 42 ] ) );
		$before = $effects->snapshot();
		$this->assertSame( 0, wp_clear_scheduled_hook( 'fixture_event', [ 42 ] ) );
		$this->assertSame( [], $effects->changes_since( $before ) );
		$this->assertSame( 1, wp_clear_scheduled_hook( 'fixture_event', [ 43 ] ) );
		$this->assertFalse( wp_next_scheduled( 'fixture_event', [ 43 ] ) );
	}

	public function test_unscheduling_matches_timestamp_hook_and_arguments_even_when_missing(): void {
		$effects = new LifecycleEffects();
		$effects->watch();
		wp_schedule_event( 100, 'daily', 'fixture_event', [ 42 ] );
		wp_schedule_single_event( 200, 'fixture_event', [ 42 ] );
		wp_schedule_single_event( 100, 'fixture_event', [ 43 ] );
		wp_schedule_single_event( 100, 'fixture_other', [ 42 ] );
		$before = $effects->snapshot();

		$this->assertTrue( wp_unschedule_event( 100, 'fixture_event' ) );
		$this->assertTrue( wp_unschedule_event( 300, 'fixture_event', [ 42 ] ) );
		$this->assertTrue( wp_unschedule_event( 100, 'fixture_missing', [ 42 ] ) );
		$this->assertSame( [], $effects->changes_since( $before ) );
		$this->assertTrue( wp_unschedule_event( 100, 'fixture_event', [ 42 ] ) );
		$this->assertSame(
			[ 'changes the cron events of "fixture_event" again' ],
			$effects->changes_since( $before )
		);
		$this->assertSame( 200, wp_next_scheduled( 'fixture_event', [ 42 ] ) );
		$this->assertSame( 100, wp_next_scheduled( 'fixture_event', [ 43 ] ) );
		$this->assertSame( 100, wp_next_scheduled( 'fixture_other', [ 42 ] ) );
		$before = $effects->snapshot();
		$this->assertTrue( wp_unschedule_event( 100, 'fixture_event', [ 42 ] ) );
		$this->assertSame( [], $effects->changes_since( $before ) );
		$this->assertTrue( wp_unschedule_event( 200, 'fixture_event', [ 42 ] ) );
		$this->assertTrue( wp_unschedule_event( 100, 'fixture_event', [ 43 ] ) );
		$this->assertFalse( wp_next_scheduled( 'fixture_event', [ 43 ] ) );
	}

	public function test_cron_snapshots_compare_events_independently_of_insertion_order(): void {
		$effects = new LifecycleEffects();
		$effects->watch();
		$empty = $effects->snapshot();
		wp_schedule_event( 200, 'daily', 'fixture_event', [ 42 ] );
		wp_schedule_single_event( 100, 'fixture_event', [ 43 ] );
		wp_schedule_single_event( 100, 'fixture_event', [ 42 ] );
		$before = $effects->snapshot();

		wp_clear_scheduled_hook( 'fixture_event', [ 42 ] );
		wp_clear_scheduled_hook( 'fixture_event', [ 43 ] );
		$this->assertSame( [], $effects->changes_since( $empty ) );
		wp_schedule_single_event( 100, 'fixture_event', [ 42 ] );
		wp_schedule_single_event( 100, 'fixture_event', [ 43 ] );
		wp_schedule_event( 200, 'daily', 'fixture_event', [ 42 ] );
		$this->assertSame( [], $effects->changes_since( $before ) );
	}

	public function __invoke( string $content ): string {
		return $content;
	}

	public static function filter_content( string $content = '' ): string {
		return $content;
	}
}
