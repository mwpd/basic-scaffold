<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\NamesWithoutTypeKind;

interface Foo {
}

final class SimpleFoo implements Foo {
}

final class FooFactory {
	public function make(): object {
		return new class() {
		};
	}
}

final class Image {
}

interface PluginException extends \Throwable {
}

interface Registerable {
}

interface HasDependencies {
}

abstract class ServiceBasedFoo {
}

trait Stringify {
}

final class CachedFoo {
}

final class WordPressFoo {
}
