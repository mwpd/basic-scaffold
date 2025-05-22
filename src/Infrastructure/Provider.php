<?php

namespace MWPD\BasicScaffold\Infrastructure;

interface Provider {
	public function get_bindings(): array;

	public function get_arguments(): array;

	public function get_shared_instances(): array;

	public function get_delegations(): array;

	public function get_service_classes(): array;
}
