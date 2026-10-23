<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\NoServiceLocator\Feature;

final class Unrelated {
	public function configure( self $other ): void {
		$other->bind();
	}
	public function bind(): void {}
}
