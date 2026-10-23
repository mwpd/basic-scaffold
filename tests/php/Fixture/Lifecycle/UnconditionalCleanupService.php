<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Lifecycle;

use MWPD\BasicScaffold\Infrastructure\Lifecycle\NetworkContext;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\SiteContext;
use MWPD\BasicScaffold\Infrastructure\NetworkUninstallable;
use MWPD\BasicScaffold\Infrastructure\Service;
use MWPD\BasicScaffold\Infrastructure\SiteUninstallable;

/**
 * The cleanup the uninstall guidance shows: a focused service without a
 * condition, so it runs whatever the uninstall request looks like, that only
 * deletes the plugin's own, prefixed data and does not mind if it is gone.
 */
final class UnconditionalCleanupService implements Service, SiteUninstallable, NetworkUninstallable {

	public const SITE_OPTION = 'mwpd_basic_scaffold_settings';

	public const NETWORK_OPTION = 'mwpd_basic_scaffold_network_settings';

	public function uninstall_site( SiteContext $context ): void {
		// Deleting an option that does not exist is not an error.
		\delete_option( self::SITE_OPTION );
	}

	public function uninstall_network( NetworkContext $context ): void {
		// Switching sites does not switch networks: name the network.
		\delete_network_option( $context->network_id()->to_int(), self::NETWORK_OPTION );
	}
}
