<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\FinalByDefault;

interface SomeInterface {
}

trait SomeTrait {
}

final class FinalClass implements SomeInterface {
	use SomeTrait;
}

abstract class AbstractClass {
}

/**
 * Only declared final in its documentation, so it can still be extended.
 *
 * @final
 */
class OnlyFinalInDocumentation {
}

class OpenClass {
}

class DesignedBaseClass {
}

final class ClassWithAnonymousClass {

	public function make(): object {
		return new class() {
		};
	}
}
