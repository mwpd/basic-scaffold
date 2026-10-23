<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Actions;
use MWPD\BasicScaffold\Exception\FailedToMakeInstance;
use MWPD\BasicScaffold\Exception\FailedToResolveDependencies;
use MWPD\BasicScaffold\Exception\InvalidConfiguration;
use MWPD\BasicScaffold\Infrastructure\Registration\RegistrationLog;
use MWPD\BasicScaffold\Infrastructure\Registration\RegistrationRecord;
use MWPD\BasicScaffold\Infrastructure\Registration\RegistrationReport;
use MWPD\BasicScaffold\Infrastructure\Registration\SimpleRegistrationLog;
use MWPD\BasicScaffold\Infrastructure\ServiceContainer\LazilyInstantiatedService;
use MWPD\BasicScaffold\Tests\Fixture\Registration\CountingDelayedNeededService;
use MWPD\BasicScaffold\Tests\Fixture\Registration\CountingUnneededService;
use MWPD\BasicScaffold\Tests\Fixture\Registration\IdleObserverService;
use MWPD\BasicScaffold\Tests\Fixture\Registration\LazyCountingService;
use MWPD\BasicScaffold\Tests\Fixture\Registration\ObservingService;
use MWPD\BasicScaffold\Tests\Fixture\Registration\RegisteredService;
use MWPD\BasicScaffold\Tests\Fixture\Registration\ReportNeedingService;
use MWPD\BasicScaffold\Tests\Fixture\Registration\ServiceNeedingDelayed;
use MWPD\BasicScaffold\Tests\Fixture\Registration\WriterNeedingService;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestCircularA;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestCircularB;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestDelayedDependentService;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestDelayedService;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestServiceNeedingUnneeded;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestUnneededService;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestUnneededServiceNeedingUnneeded;
use MWPD\BasicScaffold\Tests\Fixture\TestServicesPlugin;

/**
 * The plugin records the decisions it makes while registering its services,
 * as they happen, but only while a service observes the registration.
 *
 * @covers \MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin
 * @covers \MWPD\BasicScaffold\Infrastructure\Registration\RegistrationRecord
 * @covers \MWPD\BasicScaffold\Infrastructure\Registration\SimpleRegistrationLog
 */
final class RegistrationRecordingTest extends TestCase {

	private const OBSERVER = 'observer';

	/**
	 * Actions the tests fire, with the callbacks added to them.
	 */
	private const FIRED_ACTIONS = [ 'init', 'wp' ];

	/**
	 * @var array<string,array<int,list<callable>>>
	 */
	private array $callbacks = [];

	protected function set_up() {
		parent::set_up();

		CountingUnneededService::$asked           = 0;
		CountingDelayedNeededService::$asked      = 0;
		CountingDelayedNeededService::$registered = 0;
		LazyCountingService::$built               = 0;

		$this->capture_fired_actions();
	}

	/**
	 * Capture the callbacks added to the actions the tests fire, because
	 * Brain Monkey records added callbacks but never runs them.
	 */
	private function capture_fired_actions(): void {
		$this->callbacks = [];

		foreach ( self::FIRED_ACTIONS as $action ) {
			Actions\expectAdded( $action )
				->zeroOrMoreTimes()
				->whenHappen(
					function ( callable $callback, int $priority ) use ( $action ): void {
						$this->callbacks[ $action ][ $priority ][] = $callback;
					}
				);
		}
	}

	public function test_a_conditional_skip_is_recorded_with_the_real_class_without_asking_again(): void {
		$log = new SimpleRegistrationLog();
		$this->register( [ 'unneeded' => CountingUnneededService::class ], $log );

		$this->assertSame(
			[ [ 'unneeded', CountingUnneededService::class, RegistrationRecord::SKIPPED, [], [] ] ],
			$this->summarize( $log )
		);
		$this->assertSame( 1, CountingUnneededService::$asked );
	}

	public function test_a_delayed_service_is_recorded_waiting_for_its_action_until_it_fires(): void {
		$log    = new SimpleRegistrationLog();
		$plugin = $this->register( [ 'delayed' => CountingDelayedNeededService::class ], $log );

		$this->assertSame(
			[
				[
					'delayed',
					CountingDelayedNeededService::class,
					RegistrationRecord::WAITING_FOR_ACTION,
					[ 'init' ],
					[],
				],
			],
			$this->summarize( $log )
		);
		$this->assertFalse( $plugin->get_container()->has( 'delayed' ) );

		$this->fire( 'init' );

		$this->assertSame(
			[
				[
					'delayed',
					CountingDelayedNeededService::class,
					RegistrationRecord::WAITING_FOR_ACTION,
					[ 'init' ],
					[],
				],
				[ 'delayed', CountingDelayedNeededService::class, RegistrationRecord::REGISTERED, [], [] ],
			],
			$this->summarize( $log )
		);
		$this->assertSame( 1, CountingDelayedNeededService::$asked );
	}

	public function test_unresolved_dependencies_are_recorded_with_their_ids_and_the_actions_awaited(): void {
		$log = new SimpleRegistrationLog();
		$this->register(
			[
				'needs_delayed'   => ServiceNeedingDelayed::class,
				'delayed_service' => TestDelayedService::class,
			],
			$log
		);

		$this->assertSame(
			[
				[
					'needs_delayed',
					ServiceNeedingDelayed::class,
					RegistrationRecord::WAITING_FOR_DEPENDENCIES,
					[],
					[ 'delayed_service' ],
				],
				[
					'delayed_service',
					TestDelayedService::class,
					RegistrationRecord::WAITING_FOR_ACTION,
					[ 'init' ],
					[],
				],
				[
					'needs_delayed',
					ServiceNeedingDelayed::class,
					RegistrationRecord::WAITING_FOR_DEPENDENCIES,
					[ 'init' ],
					[ 'delayed_service' ],
				],
			],
			$this->summarize( $log )
		);

		$this->fire( 'init' );

		$this->assertSame(
			[
				[ 'delayed_service', TestDelayedService::class, RegistrationRecord::REGISTERED, [], [] ],
				[ 'needs_delayed', ServiceNeedingDelayed::class, RegistrationRecord::REGISTERED, [], [] ],
			],
			array_slice( $this->summarize( $log ), 3 )
		);
	}

	public function test_a_delayed_dependent_waits_for_its_dependency_then_its_own_action(): void {
		$log = new SimpleRegistrationLog();
		$this->register(
			[
				'dependent'       => TestDelayedDependentService::class,
				'delayed_service' => TestDelayedService::class,
			],
			$log
		);

		$this->fire( 'init' );
		$this->fire( 'wp' );

		$this->assertSame(
			[
				[
					'dependent',
					TestDelayedDependentService::class,
					RegistrationRecord::WAITING_FOR_DEPENDENCIES,
					[],
					[ 'delayed_service' ],
				],
				[
					'delayed_service',
					TestDelayedService::class,
					RegistrationRecord::WAITING_FOR_ACTION,
					[ 'init' ],
					[],
				],
				[
					'dependent',
					TestDelayedDependentService::class,
					RegistrationRecord::WAITING_FOR_DEPENDENCIES,
					[ 'init' ],
					[ 'delayed_service' ],
				],
				[ 'delayed_service', TestDelayedService::class, RegistrationRecord::REGISTERED, [], [] ],
				[
					'dependent',
					TestDelayedDependentService::class,
					RegistrationRecord::WAITING_FOR_ACTION,
					[ 'wp' ],
					[],
				],
				[ 'dependent', TestDelayedDependentService::class, RegistrationRecord::REGISTERED, [], [] ],
			],
			$this->summarize( $log )
		);
	}

	public function test_a_dropped_service_is_recorded_as_skipped_with_the_dependency_that_is_missing(): void {
		$log = new SimpleRegistrationLog();
		$this->register(
			[
				'unneeded_service' => TestUnneededService::class,
				'dropped'          => TestUnneededServiceNeedingUnneeded::class,
			],
			$log
		);

		$this->assertSame(
			[
				[ 'unneeded_service', TestUnneededService::class, RegistrationRecord::SKIPPED, [], [] ],
				[
					'dropped',
					TestUnneededServiceNeedingUnneeded::class,
					RegistrationRecord::WAITING_FOR_DEPENDENCIES,
					[],
					[ 'unneeded_service' ],
				],
				[
					'dropped',
					TestUnneededServiceNeedingUnneeded::class,
					RegistrationRecord::SKIPPED,
					[],
					[ 'unneeded_service' ],
				],
			],
			$this->summarize( $log )
		);
	}

	public function test_a_failure_is_recorded_before_the_unchanged_exception(): void {
		$log = new SimpleRegistrationLog();

		try {
			$this->register(
				[
					'unneeded_service' => TestUnneededService::class,
					'needs_unneeded'   => TestServiceNeedingUnneeded::class,
				],
				$log
			);
			$this->fail( 'A needed service with a dependency that is not registered should fail.' );
		} catch ( FailedToResolveDependencies $exception ) {
			$this->assertStringContainsString( 'unneeded_service', $exception->getMessage() );
		}

		$this->assertSame(
			[
				'needs_unneeded',
				TestServiceNeedingUnneeded::class,
				RegistrationRecord::FAILED,
				[],
				[ 'unneeded_service' ],
			],
			$this->summarize( $log )[2]
		);
	}

	public function test_a_dependency_cycle_is_recorded_as_a_failure(): void {
		$log = new SimpleRegistrationLog();

		try {
			$this->register(
				[
					'circular_a' => TestCircularA::class,
					'circular_b' => TestCircularB::class,
				],
				$log
			);
			$this->fail( 'A dependency cycle should fail.' );
		} catch ( FailedToResolveDependencies $exception ) {
			$this->assertStringContainsString( 'circular_a', $exception->getMessage() );
		}

		$records = $this->summarize( $log );
		$last    = end( $records );
		$this->assertIsArray( $last );
		$this->assertSame( RegistrationRecord::FAILED, $last[2] );
	}

	public function test_success_stays_distinguishable_and_order_is_kept_across_services(): void {
		$log = new SimpleRegistrationLog();
		$this->register(
			[
				'registered' => RegisteredService::class,
				'unneeded'   => CountingUnneededService::class,
				'delayed'    => CountingDelayedNeededService::class,
				'lazy'       => LazyCountingService::class,
			],
			$log
		);

		$this->assertSame(
			[
				[ 'registered', RegisteredService::class, RegistrationRecord::REGISTERED, [], [] ],
				[ 'unneeded', CountingUnneededService::class, RegistrationRecord::SKIPPED, [], [] ],
				[
					'delayed',
					CountingDelayedNeededService::class,
					RegistrationRecord::WAITING_FOR_ACTION,
					[ 'init' ],
					[],
				],
				[ 'lazy', LazyCountingService::class, RegistrationRecord::REGISTERED, [], [] ],
			],
			$this->summarize( $log )
		);
	}

	public function test_a_lazy_service_is_recorded_with_its_class_and_never_built(): void {
		$log    = new SimpleRegistrationLog();
		$plugin = $this->register( [ 'lazy' => LazyCountingService::class ], $log );

		$this->assertSame(
			[ [ 'lazy', LazyCountingService::class, RegistrationRecord::REGISTERED, [], [] ] ],
			$this->summarize( $log )
		);
		$this->assertSame( 0, LazyCountingService::$built );

		// Reading the report again builds nothing and changes nothing either.
		$records = $log->get_records();
		$this->assertSame( $records, $log->get_records() );
		$this->assertSame( 0, LazyCountingService::$built );

		// The container still holds the lazy wrapper, not the service.
		$this->assertInstanceOf( LazilyInstantiatedService::class, $this->stored( $plugin, 'lazy' ) );
	}

	public function test_registering_again_records_nothing_more(): void {
		$log    = new SimpleRegistrationLog();
		$plugin = $this->register( [ 'registered' => RegisteredService::class ], $log );
		$before = $log->get_records();

		$plugin->register_services();

		$this->assertSame( $before, $log->get_records() );
	}

	public function test_nothing_is_recorded_without_a_service_that_observes(): void {
		$log = $this->createMock( RegistrationLog::class );
		$log->expects( $this->never() )->method( 'record' );

		$plugin = new TestServicesPlugin(
			[
				'idle'     => IdleObserverService::class,
				'unneeded' => CountingUnneededService::class,
				'delayed'  => CountingDelayedNeededService::class,
			],
			null,
			null,
			$log
		);
		$plugin->register_services();
		$this->fire( 'init' );

		$this->assertSame( 1, CountingUnneededService::$asked );
		$this->assertSame( 1, CountingDelayedNeededService::$registered );
	}

	public function test_a_service_receives_the_given_log_as_a_read_only_report(): void {
		$log    = new SimpleRegistrationLog();
		$plugin = $this->register( [ 'unneeded' => CountingUnneededService::class ], $log );

		$observer = $plugin->get_container()->get( self::OBSERVER );
		$this->assertInstanceOf( ObservingService::class, $observer );
		$this->assertSame( $log, $observer->report );
	}

	public function test_the_built_in_log_works_without_configuration(): void {
		$plugin = new TestServicesPlugin(
			[
				self::OBSERVER => ObservingService::class,
				'unneeded'     => CountingUnneededService::class,
			]
		);
		$plugin->register_services();

		$observer = $plugin->get_container()->get( self::OBSERVER );
		$this->assertInstanceOf( ObservingService::class, $observer );
		$this->assertInstanceOf( SimpleRegistrationLog::class, $observer->report );
		$this->assertSame(
			[ self::OBSERVER, 'unneeded' ],
			array_map(
				static fn( RegistrationRecord $record ): string => $record->get_service_id(),
				$observer->report->get_records()
			)
		);
	}

	public function test_no_service_receives_the_writer(): void {
		$plugin = $this->register( [ 'writer' => WriterNeedingService::class ], new SimpleRegistrationLog() );

		$this->expectException( FailedToMakeInstance::class );
		$plugin->get_container()->get( 'writer' );
	}

	public function test_the_report_is_refused_while_no_service_observes(): void {
		$plugin = new TestServicesPlugin(
			[
				'idle'   => IdleObserverService::class,
				'reader' => ReportNeedingService::class,
			]
		);
		$plugin->register_services();

		$this->expectException( InvalidConfiguration::class );
		$this->expectExceptionMessage( RegistrationReport::class );
		$plugin->get_container()->get( 'reader' );
	}

	/**
	 * Recording changes nothing about the registration: the same services end
	 * up registered, with the same hooks, after the same number of questions
	 * to each condition and each resolver.
	 */
	public function test_recording_leaves_the_registration_unchanged(): void {
		$observed   = $this->run_registration( ObservingService::class );
		$unobserved = $this->run_registration( IdleObserverService::class );

		$this->assertSame( $unobserved, $observed );
		$this->assertSame( 1, $observed['unneeded asked'] );
		$this->assertSame( 1, $observed['resolver calls'] );
	}

	/**
	 * Register a set of services with the observer, and report what happened.
	 *
	 * @param class-string $observer Service that observes or does not.
	 * @return array<string,mixed> What the registration did.
	 */
	private function run_registration( string $observer ): array {
		// A fresh request: no action has fired, no hook is added.
		Monkey\tearDown();
		Monkey\setUp();
		$this->capture_fired_actions();

		CountingUnneededService::$asked           = 0;
		CountingDelayedNeededService::$asked      = 0;
		CountingDelayedNeededService::$registered = 0;
		$resolver_calls                           = 0;

		$plugin = new TestServicesPlugin(
			[
				self::OBSERVER    => $observer,
				'registered'      => RegisteredService::class,
				'unneeded'        => CountingUnneededService::class,
				'delayed'         => CountingDelayedNeededService::class,
				'needs_delayed'   => ServiceNeedingDelayed::class,
				'delayed_service' => static function () use ( &$resolver_calls ): string {
					++$resolver_calls;

					return TestDelayedService::class;
				},
			]
		);
		$plugin->register_services();
		$this->fire( 'init' );

		$ids = [];
		foreach ( $plugin->get_container() as $id => $service ) {
			$ids[] = $id;
		}

		return [
			'container'        => $ids,
			'hooks'            => array_map( 'array_keys', $this->callbacks ),
			'unneeded asked'   => CountingUnneededService::$asked,
			'delayed asked'    => CountingDelayedNeededService::$asked,
			'delayed register' => CountingDelayedNeededService::$registered,
			'resolver calls'   => $resolver_calls,
			'footer hooked'    => has_action( 'wp_footer' ),
		];
	}

	/**
	 * Register services after an observer, recording into a log.
	 *
	 * @param array<string,class-string> $services Services after the observer.
	 * @param RegistrationLog            $log      Log to record into.
	 */
	private function register( array $services, RegistrationLog $log ): TestServicesPlugin {
		$plugin = new TestServicesPlugin( [ self::OBSERVER => ObservingService::class ] + $services, null, null, $log );
		$plugin->register_services();

		return $plugin;
	}

	/**
	 * Summarize the records of a log, without the observer's own.
	 *
	 * @return list<array{string,string,string,list<string>,list<string>}>
	 */
	private function summarize( RegistrationReport $report ): array {
		$summary = [];

		foreach ( $report->get_records() as $record ) {
			if ( self::OBSERVER === $record->get_service_id() ) {
				continue;
			}

			$summary[] = [
				$record->get_service_id(),
				$record->get_class_name(),
				$record->get_state(),
				$record->get_awaited_actions(),
				$record->get_missing_dependencies(),
			];
		}

		return $summary;
	}

	/**
	 * Get what the container stores under an ID, without resolving it.
	 *
	 * @return mixed Stored entry.
	 */
	private function stored( TestServicesPlugin $plugin, string $id ) {
		foreach ( $plugin->get_container() as $stored_id => $service ) {
			if ( $stored_id === $id ) {
				return $service;
			}
		}

		return null;
	}

	/**
	 * Fire an action the way WordPress does: count it as done, then run its
	 * callbacks by priority, including those added while it runs.
	 *
	 * @param non-empty-string $action Action to fire.
	 */
	private function fire( string $action ): void {
		do_action( $action );

		$ran     = [];
		$current = PHP_INT_MIN;

		while ( true ) {
			$next = null;

			foreach ( $this->callbacks[ $action ] ?? [] as $priority => $callbacks ) {
				if ( $priority < $current || ( null !== $next && $priority >= $next[0] ) ) {
					continue;
				}

				foreach ( $callbacks as $index => $callback ) {
					if ( ! isset( $ran[ "{$priority}:{$index}" ] ) ) {
						$next = [ $priority, $index, $callback ];
						break;
					}
				}
			}

			if ( null === $next ) {
				return;
			}

			[ $current, $index, $callback ] = $next;
			$ran[ "{$current}:{$index}" ]   = true;
			$callback();
		}
	}
}
