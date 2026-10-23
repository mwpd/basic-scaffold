<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Unit;

use MWPD\BasicScaffold\Exception\FailedToRestoreSite;
use MWPD\BasicScaffold\Exception\InvalidArgument;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\NetworkId;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\NetworkSelection;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\SiteId;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\SiteSelection;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\SynchronousSiteRunner;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\Multisite;
use RuntimeException;

/**
 * @covers \MWPD\BasicScaffold\Infrastructure\Lifecycle\SynchronousSiteRunner
 */
final class SynchronousSiteRunnerTest extends TestCase {

	/**
	 * Visited sites, as "site:network:current site".
	 *
	 * @var list<string>
	 */
	private array $visits = [];

	private function record_sites( Multisite $installation ): callable {
		return function ( SiteId $site, NetworkId $network ) use ( $installation ): void {
			$this->visits[] = $site->to_int() . ':' . $network->to_int() . ':' . $installation->current_site();
		};
	}

	private function record_networks( Multisite $installation ): callable {
		return function ( NetworkId $network ) use ( $installation ): void {
			$this->visits[] = $network->to_int() . ':' . $installation->current_site();
		};
	}

	public function test_it_pages_through_every_site_of_a_network(): void {
		$installation = Multisite::with_networks(
			[
				1 => [ 1, 2, 3, 4, 5 ],
				2 => [ 6 ],
			],
			3
		);
		$installation->install();

		( new SynchronousSiteRunner( 2 ) )->run_on_sites(
			SiteSelection::network( NetworkId::from_int( 1 ) ),
			$this->record_sites( $installation )
		);

		$this->assertSame( [ '1:1:1', '2:1:2', '3:1:3', '4:1:4', '5:1:5' ], $this->visits );
		$this->assertSame( [ 'sites:1:0', 'sites:1:2', 'sites:1:4' ], $installation->queries() );
		$this->assertSame( 3, $installation->current_site() );
		$this->assertSame( 0, $installation->stack_depth() );
	}

	public function test_a_full_last_page_is_followed_by_an_empty_one(): void {
		$installation = Multisite::with_networks( [ 1 => [ 1, 2, 3, 4 ] ], 1 );
		$installation->install();

		( new SynchronousSiteRunner( 2 ) )->run_on_sites(
			SiteSelection::network( NetworkId::from_int( 1 ) ),
			$this->record_sites( $installation )
		);

		$this->assertSame( [ '1:1:1', '2:1:2', '3:1:3', '4:1:4' ], $this->visits );
		$this->assertSame( [ 'sites:1:0', 'sites:1:2', 'sites:1:4' ], $installation->queries() );
	}

	public function test_it_switches_to_a_single_selected_site(): void {
		$installation = Multisite::with_networks(
			[
				1 => [ 1, 2 ],
				2 => [ 3, 4 ],
			],
			2
		);
		$installation->install();

		( new SynchronousSiteRunner() )->run_on_sites(
			SiteSelection::site( SiteId::from_int( 4 ), NetworkId::from_int( 2 ) ),
			$this->record_sites( $installation )
		);

		$this->assertSame( [ '4:2:4' ], $this->visits );
		$this->assertSame( 2, $installation->current_site() );
		$this->assertSame( 0, $installation->stack_depth() );
	}

	public function test_the_current_site_is_used_without_switching(): void {
		$installation = Multisite::with_networks( [ 1 => [ 1, 2 ] ], 2 );
		$installation->install();

		( new SynchronousSiteRunner() )->run_on_sites( SiteSelection::current(), $this->record_sites( $installation ) );

		$this->assertSame( [ '2:1:2' ], $this->visits );
		$this->assertSame( [], $installation->switches() );
	}

	/**
	 * On a single site, switch_to_blog() does not exist, so every selection is
	 * the current site.
	 */
	public function test_a_single_site_is_never_switched(): void {
		$installation = Multisite::single_site();
		$installation->install();
		$runner = new SynchronousSiteRunner();

		$runner->run_on_sites(
			SiteSelection::network( NetworkId::from_int( 1 ) ),
			$this->record_sites( $installation )
		);
		$runner->run_on_sites(
			SiteSelection::site( SiteId::from_int( 1 ), NetworkId::from_int( 1 ) ),
			$this->record_sites( $installation )
		);
		$runner->run_on_networks( NetworkSelection::all(), $this->record_networks( $installation ) );

		$this->assertSame( [ '1:1:1', '1:1:1', '1:1' ], $this->visits );
		$this->assertSame( [], $installation->switches() );
		$this->assertSame( [], $installation->queries() );
	}

	public function test_it_visits_every_network_in_its_main_site(): void {
		$installation = Multisite::with_networks(
			[
				1 => [ 1, 2 ],
				2 => [ 3 ],
				3 => [ 5, 4 ],
			],
			2
		);
		$installation->install();

		( new SynchronousSiteRunner( 2 ) )->run_on_networks(
			NetworkSelection::all(),
			$this->record_networks( $installation )
		);

		$this->assertSame( [ '1:1', '2:3', '3:5' ], $this->visits );
		$this->assertSame( [ 'networks:0', 'networks:2' ], $installation->queries() );
		$this->assertSame( 2, $installation->current_site() );
		$this->assertSame( 0, $installation->stack_depth() );
	}

	public function test_the_current_network_is_visited_in_its_main_site(): void {
		$installation = Multisite::with_networks(
			[
				1 => [ 1, 2 ],
				2 => [ 3, 4 ],
			],
			4
		);
		$installation->install();

		( new SynchronousSiteRunner() )->run_on_networks(
			NetworkSelection::current(),
			$this->record_networks( $installation )
		);

		$this->assertSame( [ '2:3' ], $this->visits );
		$this->assertSame( 4, $installation->current_site() );
	}

	/**
	 * The first failure stops the run: the original site is current again,
	 * the exception is the task's own, and no later site is visited.
	 */
	public function test_a_failing_site_stops_the_run_after_restoring_the_site(): void {
		$installation = Multisite::with_networks( [ 1 => [ 1, 2, 3, 4 ] ], 4 );
		$installation->install();
		$failure = new RuntimeException( 'Site 2 failed.' );

		try {
			( new SynchronousSiteRunner() )->run_on_sites(
				SiteSelection::network( NetworkId::from_int( 1 ) ),
				function ( SiteId $site ) use ( $failure ): void {
					$this->visits[] = (string) $site->to_int();

					if ( 2 === $site->to_int() ) {
						throw $failure;
					}
				}
			);
			$this->fail( 'The failure was not propagated.' );
		} catch ( RuntimeException $caught ) {
			$this->assertSame( $failure, $caught );
		}

		$this->assertSame( [ '1', '2' ], $this->visits );
		$this->assertSame( 4, $installation->current_site() );
		$this->assertSame( 0, $installation->stack_depth() );
	}

	/**
	 * A task that switches to another site and does not switch back fails, and
	 * the original site is switched to again.
	 */
	public function test_a_task_that_leaves_another_site_switched_fails(): void {
		$installation = Multisite::with_networks( [ 1 => [ 1, 2, 3 ] ], 1 );
		$installation->install();

		try {
			( new SynchronousSiteRunner() )->run_on_sites(
				SiteSelection::network( NetworkId::from_int( 1 ) ),
				function ( SiteId $site ): void {
					$this->visits[] = (string) $site->to_int();
					\switch_to_blog( 3 );
				}
			);
			$this->fail( 'The unbalanced switch was not reported.' );
		} catch ( FailedToRestoreSite $failure ) {
			$this->assertStringStartsWith(
				'Lifecycle work in site 1 returned with site 3 switched to.',
				$failure->getMessage()
			);
		}

		$this->assertSame( [ '1' ], $this->visits );
		$this->assertSame( 1, $installation->current_site() );
	}

	/**
	 * A task that throws after leaving another site switched keeps its own
	 * exception, and the original site is still switched to again.
	 */
	public function test_a_failing_task_keeps_its_exception_over_an_unbalanced_switch(): void {
		$installation = Multisite::with_networks( [ 1 => [ 1, 2, 3 ] ], 2 );
		$installation->install();
		$failure = new RuntimeException( 'Failed after switching.' );

		try {
			( new SynchronousSiteRunner() )->run_on_sites(
				SiteSelection::site( SiteId::from_int( 1 ), NetworkId::from_int( 1 ) ),
				static function () use ( $failure ): void {
					\switch_to_blog( 3 );
					throw $failure;
				}
			);
			$this->fail( 'The failure was not propagated.' );
		} catch ( RuntimeException $caught ) {
			$this->assertSame( $failure, $caught );
		}

		$this->assertSame( 2, $installation->current_site() );
	}

	public function test_a_page_size_must_be_positive(): void {
		$this->expectException( InvalidArgument::class );
		$this->expectExceptionMessage( 'The page size 0 is not a positive integer.' );

		new SynchronousSiteRunner( 0 );
	}
}
