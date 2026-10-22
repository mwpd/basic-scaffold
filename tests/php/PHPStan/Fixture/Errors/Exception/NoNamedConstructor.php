<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Errors\Exception;

use RuntimeException;

final class NoNamedConstructor extends RuntimeException implements PluginException {
}
