<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Unit;

use Brain\Monkey\Actions;
use MWPD\BasicScaffold\Exception\InvalidArgument;
use MWPD\BasicScaffold\Infrastructure\Service\DebugMode;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\ConditionalScopedService;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\LifecycleRecorder;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\Multisite;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\SiteEventsService;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\ThrowingScopedService;
use MWPD\BasicScaffold\Tests\Fixture\TestLifecyclePlugin;
use RuntimeException;
use WP_Site;

/**
 * A new site and a site being deleted, through SiteInitializable and
 * SiteUninitializable.
 *
 * WordPress only runs these callbacks in a request where the plugin is
 * loaded. The tests register the plugin's services, as plugins_loaded would,
 * and then fire the site hooks.
 *
 * @covers \MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin
 */
final class SiteLifecycleHooksTest extends TestCase {

	private LifecycleRecorder $recorder;

	/**
	 * Site lifecycle callbacks the plugin added, by hook.
	 *
	 * @var array<string,callable>
	 */
	private array $hooks = [];

	protected function set_up() {
		parent::set_up();

		$this->recorder = new LifecycleRecorder();
		$this->hooks    = [];
	}

	/**
	 * Expect the site hooks to be added at the priorities that place them
	 * after core sets a new site up and before it drops a deleted site's
	 * tables, and keep their callbacks.
	 */
	private function expect_site_hooks(): void {
		foreach ( [
			'wp_initialize_site'   => 11,
			'wp_uninitialize_site' => 5,
		] as $hook => $priority ) {
			Actions\expectAdded( $hook )
				->once()
				->with( \Mockery::type( 'Closure' ), $priority, 1 )
				->whenHappen(
					function ( callable $callback ) use ( $hook ): void {
						$this->hooks[ $hook ] = $callback;
					}
				);
		}

		// Site removal waits for core's validation instead (flaw 2).
		Actions\expectAdded( 'wp_validate_site_deletion' )->never();
	}

	/**
	 * @param array<string,class-string> $services   Services, in configuration order.
	 * @param DebugMode|null             $debug_mode Optional. Debug mode the composition root binds.
	 */
	private function registered_plugin( array $services, ?DebugMode $debug_mode = null ): TestLifecyclePlugin {
		$plugin = new TestLifecyclePlugin( $services, $this->recorder );

		if ( null !== $debug_mode ) {
			$plugin->with_debug_mode( $debug_mode );
		}

		$plugin->register_services();

		return $plugin;
	}

	private static function site( int $site, int $network ): WP_Site {
		return new WP_Site(
			(object) [
				'blog_id' => (string) $site,
				'site_id' => (string) $network,
			]
		);
	}

	public function test_a_new_site_on_a_network_where_the_plugin_is_network_active_is_set_up(): void {
		$installation = Multisite::with_networks(
			[
				1 => [ 1, 2 ],
				2 => [ 3, 4 ],
			],
			1
		);
		$installation->install();
		$installation->activate_for_network( 2 );
		$this->expect_site_hooks();
		$this->registered_plugin( [ 'site-events' => SiteEventsService::class ] );

		( $this->hooks['wp_initialize_site'] )( self::site( 4, 2 ) );

		$this->assertSame( [ 'construct:site-events@4', 'initialize_site:site-events@4' ], $this->recorder->events() );
		$this->assertSame( 1, $installation->current_site() );
		$this->assertSame( 0, $installation->stack_depth() );
	}

	/**
	 * Flaw 1: the new-site handler must not run when the plugin is not
	 * network-active, including when it is only network-active on another
	 * network, which switching sites does not tell.
	 */
	public function test_a_new_site_is_not_set_up_unless_the_plugin_is_network_active_on_its_network(): void {
		$installation = Multisite::with_networks(
			[
				1 => [ 1, 2 ],
				2 => [ 3, 4 ],
			],
			1
		);
		$installation->install();
		$installation->activate_for_network( 1 );
		$installation->activate_on_site( 4 );
		$this->expect_site_hooks();
		$this->registered_plugin( [ 'site-events' => SiteEventsService::class ] );

		( $this->hooks['wp_initialize_site'] )( self::site( 4, 2 ) );

		$this->assertSame( [], $this->recorder->events() );
		$this->assertSame( [], $installation->switches() );
	}

	public function test_a_new_site_that_core_failed_to_set_up_is_left_alone(): void {
		$installation = Multisite::with_networks( [ 1 => [ 1, 2 ] ], 1 );
		$installation->install();
		$installation->activate_for_network( 1 );
		$installation->fail_initialization( 2 );
		$this->expect_site_hooks();
		$this->registered_plugin( [ 'site-events' => SiteEventsService::class ] );

		( $this->hooks['wp_initialize_site'] )( self::site( 2, 1 ) );

		$this->assertSame( [], $this->recorder->events() );
	}

	/**
	 * Site removal cleans up whether or not the plugin is still active on the
	 * site: data from an earlier activation may still be there.
	 */
	public function test_a_deleted_site_is_cleaned_up_even_where_the_plugin_is_inactive(): void {
		$installation = Multisite::with_networks( [ 1 => [ 1, 2, 3 ] ], 1 );
		$installation->install();
		$this->expect_site_hooks();
		$this->registered_plugin( [ 'site-events' => SiteEventsService::class ] );

		( $this->hooks['wp_uninitialize_site'] )( self::site( 3, 1 ) );

		$this->assertSame(
			[ 'construct:site-events@3', 'uninitialize_site:site-events@3' ],
			$this->recorder->events()
		);
		$this->assertSame( [ 3 ], $installation->switches() );
		$this->assertSame( 1, $installation->current_site() );
	}

	/**
	 * Flaw 4: the condition is asked in the site the callback is for, not in
	 * the site the request runs in.
	 */
	public function test_the_condition_is_asked_in_the_new_or_deleted_site(): void {
		$installation = Multisite::with_networks( [ 1 => [ 1, 2, 3 ] ], 1 );
		$installation->install();
		$installation->activate_for_network( 1 );
		$installation->set_option( 1, ConditionalScopedService::NEEDED_OPTION, 'yes' );
		$installation->set_option( 3, ConditionalScopedService::NEEDED_OPTION, 'yes' );
		$this->expect_site_hooks();
		$this->registered_plugin( [ 'conditional' => ConditionalScopedService::class ] );

		( $this->hooks['wp_initialize_site'] )( self::site( 2, 1 ) );
		( $this->hooks['wp_uninitialize_site'] )( self::site( 2, 1 ) );
		( $this->hooks['wp_initialize_site'] )( self::site( 3, 1 ) );
		( $this->hooks['wp_uninitialize_site'] )( self::site( 3, 1 ) );

		$this->assertSame(
			[
				'construct:conditional@3',
				'initialize_site:conditional@3',
				'construct:conditional@3',
				'uninitialize_site:conditional@3',
			],
			$this->recorder->events()
		);
	}

	/**
	 * @return array<string,array{bool}>
	 */
	public static function data_debug_modes(): array {
		return [
			'debug mode on'  => [ true ],
			'debug mode off' => [ false ],
		];
	}

	/**
	 * Flaw 3: a failure in the switched site propagates, in debug mode and
	 * in production alike, and the original site is current again.
	 *
	 * @dataProvider data_debug_modes
	 */
	public function test_a_failing_site_callback_propagates_after_restoring_the_site( bool $is_debug_mode ): void {
		$installation = Multisite::with_networks( [ 1 => [ 1, 2 ] ], 1 );
		$installation->install();
		$installation->activate_for_network( 1 );
		$installation->set_option( 2, ThrowingScopedService::FAIL_OPTION, 'yes' );
		$this->expect_site_hooks();
		$this->registered_plugin( [ 'throwing' => ThrowingScopedService::class ], $this->debug_mode( $is_debug_mode ) );

		foreach ( [ 'wp_initialize_site', 'wp_uninitialize_site' ] as $hook ) {
			try {
				( $this->hooks[ $hook ] )( self::site( 2, 1 ) );
				$this->fail( 'The failure was not propagated.' );
			} catch ( RuntimeException $failure ) {
				$this->assertSame( 'Failed in site 2.', $failure->getMessage() );
			}

			$this->assertSame( 1, $installation->current_site() );
			$this->assertSame( 0, $installation->stack_depth() );
		}
	}

	public function test_the_site_hooks_are_not_added_on_a_single_site(): void {
		Multisite::single_site()->install();
		Actions\expectAdded( 'wp_initialize_site' )->never();
		Actions\expectAdded( 'wp_uninitialize_site' )->never();

		$this->registered_plugin( [ 'site-events' => SiteEventsService::class ] );
	}

	public function test_a_site_hook_rejects_anything_but_a_site(): void {
		Multisite::with_networks( [ 1 => [ 1 ] ], 1 )->install();
		$this->expect_site_hooks();
		$this->registered_plugin( [ 'site-events' => SiteEventsService::class ] );

		$this->expectException( InvalidArgument::class );
		$this->expectExceptionMessage( 'The site "2" is not a WP_Site object.' );

		( $this->hooks['wp_uninitialize_site'] )( 2 );
	}
}
