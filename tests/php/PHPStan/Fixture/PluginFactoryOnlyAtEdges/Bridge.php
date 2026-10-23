<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\PluginFactoryOnlyAtEdges;

final class Bridge {
	public static function dispatch(): void {
		PluginFactory::create();
		$deferred = static fn() => PluginFactory::create();
	}

	public static function other(): void {
		PluginFactory::create();
	}
}
