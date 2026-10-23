<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\PluginFactoryOnlyAtEdges;

final class ChildFactory extends PluginFactory {
	public static function again(): void {
		parent::create();
	}
}
