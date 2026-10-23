<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture;

use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;

/**
 * A plugin that configures its injector with whatever a test hands it, and
 * applies the injector filters.
 */
final class TestInjectorConfigurationPlugin extends ServiceBasedPlugin {

	/**
	 * @var array<class-string,class-string|callable>
	 */
	private array $bindings;

	/**
	 * @var array<class-string|callable>
	 */
	private array $shared_instances;

	/**
	 * @param array<class-string,class-string|callable> $bindings         Injector bindings.
	 * @param array<class-string|callable>              $shared_instances Shared instances.
	 */
	public function __construct( array $bindings = [], array $shared_instances = [] ) {
		$this->bindings         = $bindings;
		$this->shared_instances = $shared_instances;
		parent::__construct();
	}

	protected function get_bindings(): array {
		return $this->bindings;
	}

	protected function get_shared_instances(): array {
		return $this->shared_instances;
	}
}
