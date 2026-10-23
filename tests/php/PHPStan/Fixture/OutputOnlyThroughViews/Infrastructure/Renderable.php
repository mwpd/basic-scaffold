<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\OutputOnlyThroughViews\Infrastructure;

interface Renderable {
	public function render(): string;
}
