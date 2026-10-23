<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture;

use MWPD\BasicScaffold\Infrastructure\Injector;
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
	 * @param Injector|null              $injector Optional. Injector to use.
	 */
	public function __construct( array $services, ?Injector $injector = null ) {
		$this->services = $services;
		parent::__construct( false, $injector );
	}

	protected function get_service_classes(): array {
		return $this->services;
	}
}
