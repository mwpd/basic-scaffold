<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Lifecycle;

use MWPD\BasicScaffold\Infrastructure\Lifecycle\SiteContext;
use MWPD\BasicScaffold\Infrastructure\Service;
use MWPD\BasicScaffold\Infrastructure\SiteActivateable;

/**
 * Switches to another site in its activation and forgets to switch back.
 */
final class LeakingSiteService implements Service, SiteActivateable {

	public const OTHER_SITE = 3;

	public function activate_site( SiteContext $context ): void {
		\switch_to_blog( self::OTHER_SITE );
	}
}
