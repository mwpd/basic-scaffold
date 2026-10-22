<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Hooks\Infrastructure;

interface Deactivateable {

	public function deactivate(): void;
}
