<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\CompositionRoot;

use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;

/**
 * A renamed root whose configuration and service must each be evaluated once.
 */
final class PrefixedServiceIdPlugin extends ServiceBasedPlugin {

	protected const SERVICE_PREFIX = 'generated.plugin.';

	private bool $declarations_read = false;

	protected function get_service_classes(): array {
		if ( $this->declarations_read ) {
			throw new \RuntimeException( 'Service declarations were read again.' );
		}
		$this->declarations_read = true;
		$resolved                = false;

		return [
			'generated.plugin.service' => static function () use ( &$resolved ): string {
				if ( $resolved ) {
					throw new \RuntimeException( 'The service declaration was resolved again.' );
				}
				$resolved = true;

				return GoodService::class;
			},
		];
	}

	protected function get_delegations(): array {
		$built = false;

		return [
			GoodService::class => static function () use ( &$built ): GoodService {
				if ( $built ) {
					throw new \RuntimeException( 'The service was built again.' );
				}
				$built = true;

				return new GoodService();
			},
		];
	}
}
