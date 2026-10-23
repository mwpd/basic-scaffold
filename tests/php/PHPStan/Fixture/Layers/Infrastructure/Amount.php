<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Infrastructure;

final class Amount {

	public static function of( int $value ): self {
		return new self();
	}
}
