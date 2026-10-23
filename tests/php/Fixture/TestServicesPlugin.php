<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture;

use MWPD\BasicScaffold\Infrastructure\Injector;
use MWPD\BasicScaffold\Infrastructure\Registration\RegistrationLog;
use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;
use MWPD\BasicScaffold\Infrastructure\ServiceContainer;

/**
 * A plugin that registers whichever services a test hands it.
 */
final class TestServicesPlugin extends ServiceBasedPlugin {

	/**
	 * @var array<string,class-string|callable>
	 */
	private array $services;

	/**
	 * @param array<string,class-string|callable> $services  Services, in registration order.
	 * @param Injector|null              $injector  Optional. Injector to use.
	 * @param ServiceContainer|null      $container Optional. Service container to use.
	 * @param RegistrationLog|null       $log       Optional. Registration log to use.
	 */
	public function __construct(
		array $services,
		?Injector $injector = null,
		?ServiceContainer $container = null,
		?RegistrationLog $log = null
	) {
		$this->services = $services;
		parent::__construct( false, $injector, $container, null, null, $log );
	}

	protected function get_service_classes(): array {
		return $this->services;
	}
}
