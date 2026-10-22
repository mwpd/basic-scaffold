<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Errors\TestDouble;

use MWPD\BasicScaffold\Tests\PHPStan\Fixture\Errors\Exception\PluginException;
use RuntimeException;

final class FakeException extends RuntimeException implements PluginException {
}
