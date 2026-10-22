<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Feature;

final class AnonymousDocumented {

	public function make(): object {
		return new class() {

			/**
			 * @return \MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Infrastructure\Concrete
			 */
			public function concrete(): object {
				return $this;
			}
		};
	}
}
