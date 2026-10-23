<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture;

use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;

/**
 * A plugin that registers whichever services a test hands it.
 */
final class TestServicesPlugin extends ServiceBasedPlugin {

	/**
	 * @var array<string,class-string>
	 */
	private array $services;

	/**
	 * @param array<string,class-string> $services Services, in registration order.
	 */
	public function __construct( array $services ) {
		$this->services = $services;
		parent::__construct( false );
	}

	protected function get_service_classes(): array {
		return $this->services;
	}
}
