<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Capabilities;

use MWPD\BasicScaffold\Infrastructure\Deactivateable;

final class DeactivateableOnly implements Deactivateable {
	public function deactivate(): void {}
}
