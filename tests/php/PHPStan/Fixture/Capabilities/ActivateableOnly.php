<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Capabilities;

use MWPD\BasicScaffold\Infrastructure\Activateable;

final class ActivateableOnly implements Activateable {
	public function activate(): void {}
}
