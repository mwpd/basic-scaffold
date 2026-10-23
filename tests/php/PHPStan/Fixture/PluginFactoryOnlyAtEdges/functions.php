<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\PluginFactoryOnlyAtEdges;

PluginFactory::create();
function run(): void {
	PluginFactory::create();
}
$deferred = static fn() => PluginFactory::create();
