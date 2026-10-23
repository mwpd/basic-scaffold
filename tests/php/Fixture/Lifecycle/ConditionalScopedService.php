<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Lifecycle;

use MWPD\BasicScaffold\Infrastructure\Conditional;
use MWPD\BasicScaffold\Infrastructure\Service;
use MWPD\BasicScaffold\Infrastructure\SiteActivateable;
use MWPD\BasicScaffold\Infrastructure\SiteInitializable;
use MWPD\BasicScaffold\Infrastructure\SiteUninitializable;
use MWPD\BasicScaffold\Infrastructure\SiteUninstallable;

/**
 * Only needed in a site whose own option says so, which is only known in that site.
 */
final class ConditionalScopedService implements
	Service,
	Conditional,
	SiteActivateable,
	SiteInitializable,
	SiteUninitializable,
	SiteUninstallable {

	use RecordsScopedLifecycle;

	private const NAME = 'conditional';

	public const NEEDED_OPTION = 'mwpd_basic_scaffold_needed';

	public static function is_needed(): bool {
		return 'yes' === \get_option( self::NEEDED_OPTION );
	}
}
