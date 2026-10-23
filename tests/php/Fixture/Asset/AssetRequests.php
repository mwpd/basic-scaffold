<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Asset;

use MWPD\BasicScaffold\Infrastructure\Asset\AssetContext;

/**
 * Records which asset services were built and asked, in order.
 */
final class AssetRequests {

	/**
	 * @var list<string>
	 */
	public array $events = [];

	public function built( string $service ): void {
		$this->events[] = 'built ' . $service;
	}

	public function asked( string $service, AssetContext $context ): void {
		$this->events[] = 'asked ' . $service . ' at ' . self::describe( $context );
	}

	private static function describe( AssetContext $context ): string {
		if ( $context->is_front_end() ) {
			return 'front end';
		}

		if ( $context->is_admin() ) {
			return 'admin ' . $context->get_hook_suffix();
		}

		return $context->is_block_editor() ? 'block editor' : 'login';
	}
}
