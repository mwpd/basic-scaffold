<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers;

use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Infrastructure\Contract;

final class StrayTraitUser implements Contract {

	use \MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Infrastructure\DefaultWork;
}
