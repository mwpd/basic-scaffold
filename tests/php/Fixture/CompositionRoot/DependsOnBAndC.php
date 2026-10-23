<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\HasDependencies;
use MWPD\BasicScaffold\Infrastructure\Service;

final class DependsOnBAndC implements Service, HasDependencies {

	public static function get_dependencies(): array {
		return [ 'fixture.b', 'fixture.c', 'fixture.b' ];
	}
}
