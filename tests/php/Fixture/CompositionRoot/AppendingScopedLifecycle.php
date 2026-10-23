<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\Lifecycle\NetworkContext;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\SiteContext;
use MWPD\BasicScaffold\Infrastructure\NetworkActivateable;
use MWPD\BasicScaffold\Infrastructure\Service;
use MWPD\BasicScaffold\Infrastructure\SiteUninstallable;

final class AppendingScopedLifecycle implements Service, NetworkActivateable, SiteUninstallable {

	public function activate_network( NetworkContext $context ): void {
		add_user_meta( 42, 'fixture_runs', 'network activated' );
	}

	public function uninstall_site( SiteContext $context ): void {
		add_post_meta( 42, 'fixture_runs', 'uninstalled' );
	}
}
