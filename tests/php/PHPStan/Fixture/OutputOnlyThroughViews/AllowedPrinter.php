<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\OutputOnlyThroughViews;

final class AllowedPrinter {
	public function output(): void {
		echo '<p>Explicitly allowed</p>';
	}
}
