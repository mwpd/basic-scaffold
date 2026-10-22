<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Feature;

/**
 * @template T = \MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Infrastructure\Concrete
 */
final class Templated {

	/**
	 * @param \MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Plugin::NAME $key
	 */
	public function read( string $key ): void {
	}
}
