<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\NamesWithoutTypeKind\Excluded;

interface FooInterface {
}

namespace mwpd\basicscaffold\tests\phpstan\fixture\nameswithouttypekind\excluded;

trait FooTrait {
}

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\NamesWithoutTypeKindSibling;

final class FooImpl {
}
