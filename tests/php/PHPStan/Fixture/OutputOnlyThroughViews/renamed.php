<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\OutputOnlyThroughViews;

use MWPD\BasicScaffold\Tests\PHPStan\Fixture\OutputOnlyThroughViews\Infrastructure\Renderable;

final class RenamedOutput {
	public function output( Renderable $renderable ): void {
		echo $renderable->render();
	}
}
