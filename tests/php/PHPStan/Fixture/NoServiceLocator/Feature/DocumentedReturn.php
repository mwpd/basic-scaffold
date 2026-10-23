<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\NoServiceLocator\Feature;

final class DocumentedReturn {
	/** @return \MWPD\BasicScaffold\Infrastructure\ServiceContainer */
	public function container() {}
}
