<?php

namespace MWPD\BasicScaffold\SampleSubsystem;

class Provider implements \MWPD\BasicScaffold\Infrastructure\Provider {

	public function get_bindings(): array {
		return [

		];
	}

	public function get_arguments(): array {
		return [

		];
	}

	public function get_shared_instances(): array {
		return [

		];
	}

	public function get_delegations(): array {
		return [

		];
	}

	public function get_service_classes(): array {
		return [
			'mwpd.basic_scaffold.sample-subsystem.sample-backend-service' => SampleBackendService::class,
			'mwpd.basic_scaffold.sample-subsystem.sample-loop-service'    => SampleLoopService::class,
		];
	}
}
