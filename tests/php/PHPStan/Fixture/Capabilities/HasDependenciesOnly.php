<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Capabilities;

use MWPD\BasicScaffold\Infrastructure\HasDependencies;

final class HasDependenciesOnly implements HasDependencies {
	public static function get_dependencies(): array { return []; }
}
