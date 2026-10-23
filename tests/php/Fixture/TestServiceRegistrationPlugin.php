<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture;

use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;
use MWPD\BasicScaffold\Tests\Fixture\Lifecycle\LifecycleRecorder;

/**
 * Observe the maps passed to extension points and the start of scheduling.
 * Services come through the filter so tests can exercise untrusted input.
 */
final class TestServiceRegistrationPlugin extends ServiceBasedPlugin {

	/**
	 * @var list<array<string,class-string|callable>>
	 */
	public array $dependency_maps = [];

	/**
	 * @var list<array<string,class-string|callable>>
	 */
	public array $missing_dependency_maps = [];

	private LifecycleRecorder $recorder;

	public function __construct( LifecycleRecorder $recorder ) {
		$this->recorder = $recorder;
		parent::__construct();
	}

	protected function dependencies_are_met( string $id, string $class_name, array &$services ): bool {
		$this->dependency_maps[] = $services;

		return parent::dependencies_are_met( $id, $class_name, $services );
	}

	protected function collect_missing_dependencies( string $class_name, array $services ): array {
		$this->missing_dependency_maps[] = $services;

		return parent::collect_missing_dependencies( $class_name, $services );
	}

	protected function schedule_potential_service_registration( string $id, string $class_name ): void {
		$this->recorder->record( "schedule:{$id}" );
		parent::schedule_potential_service_registration( $id, $class_name );
	}
}
