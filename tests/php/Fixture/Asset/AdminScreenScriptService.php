<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Asset;

use MWPD\BasicScaffold\Infrastructure\Asset\AssetContext;
use MWPD\BasicScaffold\Infrastructure\Asset\Script;
use MWPD\BasicScaffold\Infrastructure\HasAssets;
use MWPD\BasicScaffold\Infrastructure\Service;

/**
 * A service that only has assets: a script on one admin screen.
 */
final class AdminScreenScriptService implements Service, HasAssets {

	public const HANDLE = 'acme-settings';

	public const SCREEN = 'settings_page_acme';

	private AssetRequests $requests;

	public function __construct( AssetRequests $requests ) {
		$this->requests = $requests;
		$requests->built( 'admin script' );
	}

	public function get_assets( AssetContext $context ): iterable {
		$this->requests->asked( 'admin script', $context );

		if ( ! $context->is_admin( self::SCREEN ) ) {
			return [];
		}

		yield Script::file( self::HANDLE, 'assets/settings.js' )->deferred();
	}
}
