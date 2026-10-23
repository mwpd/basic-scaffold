<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\NamesWithoutTypeKind;

interface AnotherException extends \Throwable {
}

final class RelatedException extends \RuntimeException implements AnotherException {
}
