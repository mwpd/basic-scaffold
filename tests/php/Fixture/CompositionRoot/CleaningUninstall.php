<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\Lifecycle\SiteContext;
use MWPD\BasicScaffold\Infrastructure\Service;
use MWPD\BasicScaffold\Infrastructure\SiteUninstallable;

final class CleaningUninstall implements Service, SiteUninstallable {

	public function uninstall_site( SiteContext $context ): void {
		delete_option( 'fixture_runs' );
	}
}
