<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Capabilities\RenamedPlugin\Feature;

use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Capabilities\RenamedPlugin\Lifecycle\Task;
use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Capabilities\RenamedPlugin\Lifecycle\Hooks;
use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Capabilities\RenamedPlugin\Lifecycle\Later;

final class MissingTask implements Hooks {}
