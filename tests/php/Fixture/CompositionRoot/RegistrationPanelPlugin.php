<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\BasicScaffoldPlugin;
use MWPD\BasicScaffold\Infrastructure\QueryMonitor\RegistrationPanel;
use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;
use ReflectionMethod;

/**
 * The plugin's own composition root, with the optional Query Monitor panel
 * declared the way its documentation says: one more service entry, and the
 * root's configuration as it ships.
 */
final class RegistrationPanelPlugin extends ServiceBasedPlugin {

	protected const SERVICE_PREFIX = 'mwpd.basic_scaffold.';

	protected function get_service_classes(): array {
		/** @var array<string,class-string|callable> $services */
		$services = $this->root( 'get_service_classes' );

		return $services + [ BasicScaffoldPlugin::REGISTRATION_PANEL_ID => RegistrationPanel::class ];
	}

	protected function get_bindings(): array {
		/** @var array<class-string,class-string|callable> $bindings */
		$bindings = $this->root( 'get_bindings' );

		return $bindings;
	}

	protected function get_arguments(): array {
		/** @var array<array<string,mixed>> $arguments */
		$arguments = $this->root( 'get_arguments' );

		return $arguments;
	}

	protected function get_shared_instances(): array {
		/** @var array<class-string|callable> $shared_instances */
		$shared_instances = $this->root( 'get_shared_instances' );

		return $shared_instances;
	}

	protected function get_delegations(): array {
		/** @var array<class-string,callable> $delegations */
		$delegations = $this->root( 'get_delegations' );

		return $delegations;
	}

	/**
	 * Read a part of the configuration of the plugin's composition root.
	 *
	 * @param string $method Configuration method of the root.
	 * @return array<mixed> What the root configures.
	 */
	private function root( string $method ): array {
		$reflection = new ReflectionMethod( BasicScaffoldPlugin::class, $method );
		$reflection->setAccessible( true );
		$configuration = $reflection->invoke( new BasicScaffoldPlugin() );

		return is_array( $configuration ) ? $configuration : [];
	}
}
