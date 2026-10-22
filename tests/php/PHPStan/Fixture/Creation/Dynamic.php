<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Creation;

use DateTimeImmutable;

final class Dynamic {

	public function create(): void {
		$moment       = DateTimeImmutable::class;
		$exception    = CreationException::class;
		$collaborator = Service::class;

		$allowed   = new $moment();
		$throwable = new $exception( 'Failed.' );
		$service   = new $collaborator();
	}
}
