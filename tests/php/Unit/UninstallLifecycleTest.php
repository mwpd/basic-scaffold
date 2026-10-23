<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Unit;

use Brain\Monkey\Functions;
use MWPD\BasicScaffold\Exception\InvalidConfiguration;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\LifecycleScope;
use MWPD\BasicScaffold\Infrastructure\SiteDeactivateable;
use MWPD\BasicScaffold\Infrastructure\SiteInitializable;
use MWPD\BasicScaffold\Infrastructure\SiteUninstallable;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\ConditionalScopedService;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\DelayedLifecycleService;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\LazyLifecycleService;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\LifecycleRecorder;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\Multisite;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\SiteEventsService;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\SiteLifecycleService;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\ThrowingScopedService;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\UnconditionalCleanupService;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\UninstallBridge;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\UninstallService;
use MWPD\BasicScaffold\Tests\Fixture\TestLifecyclePlugin;
use RuntimeException;

/**
 * Registering the uninstall callback on activation, and uninstalling through
 * SiteUninstallable and NetworkUninstallable.
 *
 * @covers \MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin
 */
final class UninstallLifecycleTest extends TestCase {

	private const CALLBACK = [ UninstallBridge::class, 'uninstall' ];

	private LifecycleRecorder $recorder;

	protected function set_up() {
		parent::set_up();

		$this->recorder = new LifecycleRecorder();
		Functions\when( 'flush_rewrite_rules' )->justReturn( null );
	}

	/**
	 * @param array<string,class-string> $services Services, in configuration order.
	 */
	private function plugin( array $services ): TestLifecyclePlugin {
		return new TestLifecyclePlugin( $services, $this->recorder );
	}

	/**
	 * Two networks: sites 1 to 3, with main site 1, and sites 4 and 5, with
	 * main site 4. The request runs in site 1, as the network admin does.
	 */
	private function two_networks( int $current = 1 ): Multisite {
		$installation = Multisite::with_networks(
			[
				1 => [ 1, 2, 3 ],
				2 => [ 4, 5 ],
			],
			$current
		);
		$installation->install();

		return $installation;
	}

	/**
	 * Store the uninstall callbacks activation would, then remove the one of
	 * the site the plugin is deleted in, as core does before it uninstalls.
	 */
	private function prepare_uninstall( Multisite $installation, int $deleted_in ): void {
		foreach ( [ 1, 4, $deleted_in ] as $site ) {
			$installation->set_option( $site, 'uninstall_plugins', [ Multisite::BASENAME => self::CALLBACK ] );
		}

		$installation->remove_uninstall_callback( $deleted_in );
	}

	public function test_activation_registers_the_callback_before_any_service_runs(): void {
		$installation = Multisite::single_site();
		$installation->install();
		$installation->record_writes_into( $this->recorder );

		$this->plugin(
			[
				'lazy' => LazyLifecycleService::class,
				'site' => SiteLifecycleService::class,
			]
		)->activate();

		$this->assertSame(
			[
				'write:uninstall_plugins@1',
				'construct:lazy',
				'activate:lazy',
				'construct:site@1',
				'activate_site:site@1',
			],
			$this->recorder->events()
		);
		$this->assertSame( self::CALLBACK, $installation->uninstall_callback( 1 ) );
	}

	/**
	 * A plugin activated on one sub-site registers its callback on that site
	 * and on the main site of every network, so that deleting it from any
	 * network admin uninstalls it. Activating it again writes nothing new.
	 */
	public function test_activation_registers_the_callback_on_every_network_main_site(): void {
		$installation = $this->two_networks( 2 );

		$plugin = $this->plugin( [] );
		$plugin->activate_in( LifecycleScope::current_site() );

		foreach ( [ 1, 2, 4 ] as $site ) {
			$this->assertSame( self::CALLBACK, $installation->uninstall_callback( $site ), "Site {$site}" );
		}
		foreach ( [ 3, 5 ] as $site ) {
			$this->assertNull( $installation->uninstall_callback( $site ), "Site {$site}" );
		}
		$this->assertSame(
			[ '2:uninstall_plugins', '1:uninstall_plugins', '4:uninstall_plugins' ],
			$installation->writes()
		);
		$this->assertSame( 2, $installation->current_site() );
		$this->assertSame( 0, $installation->stack_depth() );

		$plugin->activate_in( LifecycleScope::network_wide() );

		$this->assertCount( 3, $installation->writes() );
	}

	/**
	 * When the activating site is a network's main site, its callback is
	 * written once.
	 */
	public function test_activation_on_a_main_site_writes_its_callback_once(): void {
		$installation = $this->two_networks( 1 );

		$this->plugin( [] )->activate_in( LifecycleScope::network_wide() );

		$this->assertSame( [ '1:uninstall_plugins', '4:uninstall_plugins' ], $installation->writes() );
		$this->assertSame( 1, $installation->current_site() );
	}

	public function test_a_single_site_uninstall_cleans_up_the_current_site_only(): void {
		$installation = Multisite::single_site();
		$installation->install();
		$installation->set_option( 1, UnconditionalCleanupService::SITE_OPTION, [ 'kept' => true ] );
		$plugin = $this->plugin(
			[
				'uninstall' => UninstallService::class,
				'cleanup'   => UnconditionalCleanupService::class,
			]
		);

		$plugin->uninstall();

		$this->assertSame( [ 'construct:uninstall@1', 'uninstall_site:uninstall@1' ], $this->recorder->events() );
		$this->assertFalse( $installation->has_option( 1, UnconditionalCleanupService::SITE_OPTION ) );
		$this->assertSame( [], $installation->switches() );
	}

	/**
	 * Every site of every network is cleaned up in its own context, then each
	 * network once, in its main site. The copies of the uninstall callback on
	 * the main sites are removed, and cleanup tolerates data that is not
	 * there.
	 */
	public function test_a_multisite_uninstall_cleans_up_every_site_and_network(): void {
		$installation = $this->two_networks();
		$this->prepare_uninstall( $installation, 1 );
		foreach ( [ 1, 3, 5 ] as $site ) {
			$installation->set_option( $site, UnconditionalCleanupService::SITE_OPTION, 'data' );
		}
		$installation->set_network_option( 2, UnconditionalCleanupService::NETWORK_OPTION, 'data' );
		$plugin = $this->plugin(
			[
				'uninstall' => UninstallService::class,
				'cleanup'   => UnconditionalCleanupService::class,
			]
		);

		$plugin->uninstall();

		$this->assertSame(
			[
				'construct:uninstall@1',
				'uninstall_site:uninstall@1',
				'construct:uninstall@2',
				'uninstall_site:uninstall@2',
				'construct:uninstall@3',
				'uninstall_site:uninstall@3',
				'construct:uninstall@1',
				'uninstall_network:uninstall#1',
				'construct:uninstall@4',
				'uninstall_site:uninstall@4',
				'construct:uninstall@5',
				'uninstall_site:uninstall@5',
				'construct:uninstall@4',
				'uninstall_network:uninstall#2',
			],
			$this->recorder->events()
		);
		foreach ( [ 1, 2, 3, 4, 5 ] as $site ) {
			$this->assertFalse( $installation->has_option( $site, UnconditionalCleanupService::SITE_OPTION ) );
			$this->assertNull( $installation->uninstall_callback( $site ) );
		}
		$this->assertFalse( $installation->has_network_option( 2, UnconditionalCleanupService::NETWORK_OPTION ) );
		$this->assertSame( 1, $installation->current_site() );
		$this->assertSame( 0, $installation->stack_depth() );
	}

	/**
	 * A Conditional service is asked in each site, and neither built nor
	 * called where it is not needed. Unconditional services always are.
	 */
	public function test_uninstall_asks_each_site_whether_a_conditional_service_is_needed(): void {
		$installation = $this->two_networks();
		$installation->set_option( 3, ConditionalScopedService::NEEDED_OPTION, 'yes' );
		$installation->set_option( 4, ConditionalScopedService::NEEDED_OPTION, 'yes' );

		$this->plugin( [ 'conditional' => ConditionalScopedService::class ] )->uninstall();

		$this->assertSame(
			[
				'construct:conditional@3',
				'uninstall_site:conditional@3',
				'construct:conditional@4',
				'uninstall_site:conditional@4',
			],
			$this->recorder->events()
		);
	}

	/**
	 * Uninstalling builds nothing that does not take part, registers nothing,
	 * and keeps registration from starting even if plugins_loaded fires after
	 * the uninstall in the same request.
	 */
	public function test_uninstall_starts_no_normal_service(): void {
		Multisite::single_site()->install();
		$plugin = $this->plugin(
			[
				'delayed'     => DelayedLifecycleService::class,
				'lazy'        => LazyLifecycleService::class,
				'site-events' => SiteEventsService::class,
				'uninstall'   => UninstallService::class,
			]
		);

		$plugin->uninstall();
		$plugin->register_services();

		$this->assertSame( [ 'construct:uninstall@1', 'uninstall_site:uninstall@1' ], $this->recorder->events() );
		$this->assertFalse( $plugin->get_container()->has( 'injector' ) );
	}

	/**
	 * The first failure stops the uninstall: it propagates unchanged, later
	 * sites and networks are not visited, what was cleaned up stays cleaned
	 * up, and the site context is restored. The callback is registered again
	 * on the site the plugin was deleted in, so deleting it again retries;
	 * the retry cleans up again and removes the copies.
	 */
	public function test_a_failed_uninstall_can_be_retried(): void {
		$installation = $this->two_networks();
		$this->prepare_uninstall( $installation, 1 );
		$installation->set_option( 4, ThrowingScopedService::FAIL_OPTION, 'yes' );
		$plugin = $this->plugin( [ 'throwing' => ThrowingScopedService::class ] );

		try {
			$plugin->uninstall();
			$this->fail( 'The failure was not propagated.' );
		} catch ( RuntimeException $failure ) {
			$this->assertSame( 'Failed in site 4.', $failure->getMessage() );
			$this->assertNull( $failure->getPrevious() );
		}

		$this->assertSame(
			[
				'construct:throwing@1',
				'uninstall_site:throwing@1',
				'construct:throwing@2',
				'uninstall_site:throwing@2',
				'construct:throwing@3',
				'uninstall_site:throwing@3',
				'construct:throwing@1',
				'uninstall_network:throwing#1',
				'construct:throwing@4',
				'uninstall_site:throwing@4',
			],
			$this->recorder->events()
		);
		$this->assertSame( self::CALLBACK, $installation->uninstall_callback( 1 ) );
		$this->assertSame( self::CALLBACK, $installation->uninstall_callback( 4 ) );
		$this->assertSame( 1, $installation->current_site() );
		$this->assertSame( 0, $installation->stack_depth() );

		// The problem is fixed, and the plugin is deleted again.
		$installation->set_option( 4, ThrowingScopedService::FAIL_OPTION, 'no' );
		$installation->remove_uninstall_callback( 1 );
		$retry = new LifecycleRecorder();
		( new TestLifecyclePlugin( [ 'throwing' => ThrowingScopedService::class ], $retry ) )->uninstall();

		$this->assertCount( 14, $retry->events() );
		foreach ( [ 1, 2, 3, 4, 5 ] as $site ) {
			$this->assertNull( $installation->uninstall_callback( $site ), "Site {$site}" );
		}
	}

	/**
	 * A failure to register the callback again does not hide the failure of
	 * the uninstall: that one still propagates, with the other at the end of
	 * its chain of previous exceptions.
	 */
	public function test_a_failure_to_register_again_does_not_mask_the_uninstall_failure(): void {
		$installation = Multisite::single_site();
		$installation->install();
		$installation->set_option( 1, ThrowingScopedService::FAIL_OPTION, 'yes' );
		$recovery = new RuntimeException( 'Could not register again.' );
		Functions\when( 'register_uninstall_hook' )->alias(
			static function () use ( $recovery ): void {
				throw $recovery;
			}
		);

		try {
			$this->plugin( [ 'throwing' => ThrowingScopedService::class ] )->uninstall();
			$this->fail( 'The failure was not propagated.' );
		} catch ( RuntimeException $failure ) {
			$this->assertSame( 'Failed in site 1.', $failure->getMessage() );
			$this->assertSame( $recovery, $failure->getPrevious() );
		}
	}

	/**
	 * @return array<string,array{array<string,class-string>, string}>
	 */
	public static function data_services_needing_a_main_file(): array {
		return [
			'site deactivation' => [ [ 'site' => SiteLifecycleService::class ], SiteDeactivateable::class ],
			'new sites'         => [ [ 'site-events' => SiteEventsService::class ], SiteInitializable::class ],
		];
	}

	/**
	 * @dataProvider data_services_needing_a_main_file
	 *
	 * @param array<string,class-string> $services Services that need the main file.
	 */
	public function test_a_service_that_needs_the_main_file_requires_one( array $services, string $needed_by ): void {
		Multisite::single_site()->install();

		$this->expectException( InvalidConfiguration::class );
		$this->expectExceptionMessage( $needed_by . ' needs to know which plugin it belongs to' );

		$this->plugin( $services )->without_main_file()->with_uninstall_callback( null )->register_services();
	}

	public function test_an_uninstall_service_requires_a_callback(): void {
		Multisite::single_site()->install();

		$this->expectException( InvalidConfiguration::class );
		$this->expectExceptionMessage(
			'A service implements "' . SiteUninstallable::class . '", but the composition root names no uninstall'
		);

		$this->plugin( [ 'uninstall' => UninstallService::class ] )
			->with_uninstall_callback( null )
			->register_services();
	}

	public function test_an_uninstall_callback_requires_a_main_file(): void {
		Multisite::single_site()->install();

		$this->expectException( InvalidConfiguration::class );
		$this->expectExceptionMessage( 'The uninstall callback needs to know which plugin it belongs to' );

		$this->plugin( [] )->without_main_file()->activate();
	}

	/**
	 * @return array<string,array{array<mixed>}>
	 */
	public static function data_invalid_callbacks(): array {
		return [
			'instance method' => [ [ UninstallBridge::class, 'not_static' ] ],
			'private method'  => [ [ UninstallBridge::class, 'clean_up' ] ],
			'missing method'  => [ [ UninstallBridge::class, 'missing' ] ],
			'missing class'   => [ [ 'MWPD\BasicScaffold\Tests\Fixture\Lifecycle\Missing', 'uninstall' ] ],
			'object'          => [ [ new UninstallBridge(), 'uninstall' ] ],
			'one string'      => [ [ UninstallBridge::class . '::uninstall' ] ],
			'three strings'   => [ [ UninstallBridge::class, 'uninstall', 'extra' ] ],
		];
	}

	/**
	 * WordPress stores the callback in the database, so it has to be a public
	 * static method named by two strings. Activation checks that before it
	 * registers the callback.
	 *
	 * @dataProvider data_invalid_callbacks
	 *
	 * @param array<mixed> $callback Invalid callback.
	 */
	public function test_an_invalid_uninstall_callback_is_rejected( array $callback ): void {
		if ( 'not_static' === ( $callback[1] ?? null ) && PHP_VERSION_ID < 80000 ) {
			$this->markTestSkipped( 'PHP 7 still lets a method that is not static be called statically.' );
		}

		Multisite::single_site()->install();

		$this->expectException( InvalidConfiguration::class );
		$this->expectExceptionMessage( 'is not a public static method given as [ class name, method name ]' );

		$this->plugin( [] )->with_uninstall_callback( $callback )->activate();
	}

	/**
	 * A generated plugin from before these settings existed configures
	 * neither, and keeps working while it has no scoped services.
	 */
	public function test_a_plugin_without_scoped_services_needs_neither_setting(): void {
		$installation = Multisite::single_site();
		$installation->install();

		$this->plugin( [ 'lazy' => LazyLifecycleService::class ] )
			->without_main_file()
			->with_uninstall_callback( null )
			->activate();

		$this->assertSame( [ 'construct:lazy', 'activate:lazy' ], $this->recorder->events() );
		$this->assertSame( [], $installation->writes() );
	}
}
