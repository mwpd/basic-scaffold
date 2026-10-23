<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Lifecycle;

use MWPD\BasicScaffold\Infrastructure\NetworkUninstallable;
use MWPD\BasicScaffold\Infrastructure\Service;
use MWPD\BasicScaffold\Infrastructure\SiteActivateable;
use MWPD\BasicScaffold\Infrastructure\SiteInitializable;
use MWPD\BasicScaffold\Infrastructure\SiteUninitializable;
use MWPD\BasicScaffold\Infrastructure\SiteUninstallable;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\SiteContext;
use RuntimeException;

/**
 * Fails in a site whose own option says so, after recording the call.
 */
final class ThrowingScopedService implements
	Service,
	SiteActivateable,
	SiteInitializable,
	SiteUninitializable,
	SiteUninstallable,
	NetworkUninstallable {

	use RecordsScopedLifecycle;

	private const NAME = 'throwing';

	public const FAIL_OPTION = 'mwpd_basic_scaffold_fail';

	public function activate_site( SiteContext $context ): void {
		$this->record_site( 'activate_site', $context );
		$this->fail_if_asked();
	}

	public function initialize_site( SiteContext $context ): void {
		$this->record_site( 'initialize_site', $context );
		$this->fail_if_asked();
	}

	public function uninitialize_site( SiteContext $context ): void {
		$this->record_site( 'uninitialize_site', $context );
		$this->fail_if_asked();
	}

	public function uninstall_site( SiteContext $context ): void {
		$this->record_site( 'uninstall_site', $context );
		$this->fail_if_asked();
	}

	private function fail_if_asked(): void {
		if ( 'yes' === \get_option( self::FAIL_OPTION ) ) {
			throw new RuntimeException( 'Failed in site ' . \get_current_blog_id() . '.' );
		}
	}
}
