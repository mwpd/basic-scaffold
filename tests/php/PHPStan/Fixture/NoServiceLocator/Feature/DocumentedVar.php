<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\NoServiceLocator\Feature;

final class DocumentedVar {
	/** @var \MWPD\BasicScaffold\Infrastructure\ServiceContainer */
	private $container;
}
