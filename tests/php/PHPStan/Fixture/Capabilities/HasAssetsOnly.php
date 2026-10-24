<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Capabilities;

use MWPD\BasicScaffold\Infrastructure\{Asset\AssetContext, HasAssets};

final class HasAssetsOnly implements HasAssets {
	public function get_assets( AssetContext $context ): iterable { return []; }
}
