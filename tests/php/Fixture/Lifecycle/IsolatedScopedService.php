<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Lifecycle;

use MWPD\BasicScaffold\Infrastructure\Injector;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\SiteContext;
use MWPD\BasicScaffold\Infrastructure\Service;
use MWPD\BasicScaffold\Infrastructure\SiteActivateable;
use MWPD\BasicScaffold\Infrastructure\SiteUninstallable;

/**
 * Records the collaborators of the graph it was built in, and what its shared
 * cache read in the site it runs in.
 */
final class IsolatedScopedService implements Service, SiteActivateable, SiteUninstallable {

	private LifecycleRecorder $recorder;

	private SiteCache $cache;

	private Injector $injector;

	public function __construct( LifecycleRecorder $recorder, SiteCache $cache, Injector $injector ) {
		$this->recorder = $recorder;
		$this->cache    = $cache;
		$this->injector = $injector;
	}

	public function activate_site( SiteContext $context ): void {
		$this->record( $context );
	}

	public function uninstall_site( SiteContext $context ): void {
		$this->record( $context );
	}

	private function record( SiteContext $context ): void {
		$site = $context->site_id()->to_int();

		$this->recorder->record( 'name@' . $site . ':' . $this->cache->site_name() );
		$this->recorder->keep( 'cache@' . $site, $this->cache );
		$this->recorder->keep( 'injector@' . $site, $this->injector );
		$this->recorder->keep( 'made@' . $site, $this->injector->make( SiteCache::class ) );
	}
}
