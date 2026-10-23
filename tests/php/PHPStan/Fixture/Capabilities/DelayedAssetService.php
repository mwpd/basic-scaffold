<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Capabilities;

use MWPD\BasicScaffold\Infrastructure\Asset\AssetContext;
use MWPD\BasicScaffold\Infrastructure\{DefaultRegistrationPriority, Delayed, HasAssets};
use MWPD\BasicScaffold\Infrastructure\Service;

final class DelayedAssetService implements Service, Delayed, HasAssets {
	use DefaultRegistrationPriority;

	public static function get_registration_action(): string { return "wp"; }

	public function get_assets( AssetContext $context ): iterable { return []; }
}
