<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\NamesWithoutTypeKind;

interface FooInterface {
}

interface IFoo {
}

abstract class AbstractFoo {
}

class BaseFoo {
}

trait FooTrait {
}

final class FooImpl {
}

final class FooException extends \RuntimeException {
}

abstract class FooAbstract {
}

final class FooImplementation {
}

final class FooClass {
}

abstract class AbstractBarInterface {
}

final class UsesFoo {
	use FooTrait;
}
