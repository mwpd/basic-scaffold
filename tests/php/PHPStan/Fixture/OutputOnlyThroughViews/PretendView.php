<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\OutputOnlyThroughViews;

final class PretendView {

	public function render(): string {
		return '<p>This is not a view</p>';
	}
}
