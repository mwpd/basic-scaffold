<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Lifecycle;

use MWPD\BasicScaffold\Infrastructure\Lifecycle\NetworkContext;
use MWPD\BasicScaffold\Infrastructure\Lifecycle\SiteContext;

/**
 * Records construction and each lifecycle call under the fixture's NAME, with
 * the site or network it happened in: "activate_site:NAME@2" for site 2,
 * "activate_network:NAME#1" for network 1, "construct:NAME@2" when the
 * service is built while site 2 is current.
 *
 * A call made while another site is switched to than the context names is
 * recorded with "!wrong-site", so a test sees it.
 */
trait RecordsScopedLifecycle {

	private LifecycleRecorder $recorder;

	public function __construct( LifecycleRecorder $recorder ) {
		$this->recorder = $recorder;
		$this->recorder->record( 'construct:' . self::NAME . '@' . \get_current_blog_id() );
	}

	public function activate(): void {
		$this->recorder->record( 'activate:' . self::NAME . '@' . \get_current_blog_id() );
	}

	public function deactivate(): void {
		$this->recorder->record( 'deactivate:' . self::NAME . '@' . \get_current_blog_id() );
	}

	public function activate_site( SiteContext $context ): void {
		$this->record_site( 'activate_site', $context );
	}

	public function deactivate_site( SiteContext $context ): void {
		$this->record_site( 'deactivate_site', $context );
	}

	public function initialize_site( SiteContext $context ): void {
		$this->record_site( 'initialize_site', $context );
	}

	public function uninitialize_site( SiteContext $context ): void {
		$this->record_site( 'uninitialize_site', $context );
	}

	public function uninstall_site( SiteContext $context ): void {
		$this->record_site( 'uninstall_site', $context );
	}

	public function activate_network( NetworkContext $context ): void {
		$this->record_network( 'activate_network', $context );
	}

	public function deactivate_network( NetworkContext $context ): void {
		$this->record_network( 'deactivate_network', $context );
	}

	public function uninstall_network( NetworkContext $context ): void {
		$this->record_network( 'uninstall_network', $context );
	}

	private function record_site( string $event, SiteContext $context ): void {
		$site = $context->site_id()->to_int();

		$this->recorder->record(
			$event . ':' . self::NAME . '@' . $site
			. ( \get_current_blog_id() === $site ? '' : '!wrong-site' )
		);
	}

	private function record_network( string $event, NetworkContext $context ): void {
		$network = $context->network_id()->to_int();

		$this->recorder->record(
			$event . ':' . self::NAME . '#' . $network
			. ( \get_current_blog_id() === \get_main_site_id( $network ) ? '' : '!wrong-site' )
		);
	}
}
