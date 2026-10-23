<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Feature;

use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Infrastructure\Contract;
use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Infrastructure\DefaultWork;

final class UsesTraits implements Contract {

	use DefaultWork;
}

final class UsesShortcut implements Contract {

	use \MWPD\BasicScaffold\Tests\PHPStan\Fixture\Layers\Infrastructure\Shortcut;
}

final class UsesDefaultWithoutContract {

	use DefaultWork;
}
