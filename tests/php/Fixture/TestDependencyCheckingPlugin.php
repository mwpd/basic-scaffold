<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture;

use LogicException;
use MWPD\BasicScaffold\Infrastructure\HasDependencies;
use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestServiceA;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestServiceB;
use MWPD\BasicScaffold\Tests\Fixture\Service\TestServiceNeedingB;

/**
 * A plugin that overrides dependencies_are_met() relying on its documented
 * precondition: it is only asked about services with dependencies.
 */
final class TestDependencyCheckingPlugin extends ServiceBasedPlugin {

	protected function get_service_classes(): array {
		return [
			'needs_b'   => TestServiceNeedingB::class,
			'service_a' => TestServiceA::class,
			'service_b' => TestServiceB::class,
		];
	}

	protected function dependencies_are_met( string $id, string $class_name, array &$services ): bool {
		if ( ! is_a( $class_name, HasDependencies::class, true ) ) {
			throw new LogicException( "Asked about \"{$id}\", which has no dependencies." );
		}

		return parent::dependencies_are_met( $id, $class_name, $services );
	}
}
