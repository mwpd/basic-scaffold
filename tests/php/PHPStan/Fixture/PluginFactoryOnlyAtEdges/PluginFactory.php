<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\PluginFactoryOnlyAtEdges;

class PluginFactory {
	public static function create(): void {}

	public static function again(): void {
		self::create();
		static::create();
	}
}
