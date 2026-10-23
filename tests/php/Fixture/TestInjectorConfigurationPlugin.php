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
	 * @var array<array<string,mixed>>
	 */
	private array $arguments;

	/**
	 * @var array<class-string,callable>
	 */
	private array $delegations;

	/**
	 * @param array<class-string,class-string|callable> $bindings         Injector bindings.
	 * @param array<class-string|callable>              $shared_instances Shared instances.
	 * @param array<array<string,mixed>>                $arguments        Argument bindings.
	 * @param array<class-string,callable>              $delegations      Delegations.
	 */
	public function __construct(
		array $bindings = [],
		array $shared_instances = [],
		array $arguments = [],
		array $delegations = []
	) {
		$this->bindings         = $bindings;
		$this->shared_instances = $shared_instances;
		$this->arguments        = $arguments;
		$this->delegations      = $delegations;
		parent::__construct();
	}

	protected function get_bindings(): array {
		return $this->bindings;
	}

	protected function get_shared_instances(): array {
		return $this->shared_instances;
	}

	protected function get_arguments(): array {
		return $this->arguments;
	}

	protected function get_delegations(): array {
		return $this->delegations;
	}
}
