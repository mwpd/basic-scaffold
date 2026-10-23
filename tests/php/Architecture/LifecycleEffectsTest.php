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
 * Hook state follows WordPress identity rather than Brain Monkey's call log.
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

	public function __invoke( string $content ): string {
		return $content;
	}

	public static function filter_content( string $content = '' ): string {
		return $content;
	}
}
