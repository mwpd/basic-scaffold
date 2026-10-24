<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Asset;

use MWPD\BasicScaffold\Infrastructure\Asset\AssetContext;
use MWPD\BasicScaffold\Infrastructure\HasAssets;
use MWPD\BasicScaffold\Infrastructure\Service;
use TypeError;

/**
 * A service whose code fails with an Error rather than an Exception.
 */
final class ErroringAssetService implements Service, HasAssets {

	public function get_assets( AssetContext $context ): iterable {
		throw new TypeError( 'The service broke.' );
	}
}
