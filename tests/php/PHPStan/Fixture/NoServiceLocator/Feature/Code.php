<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\NoServiceLocator\Feature;

final class Code {
	public function name(): string {
		return \MWPD\BasicScaffold\Infrastructure\ServiceContainer::class;
	}
}
