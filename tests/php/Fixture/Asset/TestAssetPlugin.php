<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Asset;

use MWPD\BasicScaffold\Infrastructure\Asset\AssetLocation;
use MWPD\BasicScaffold\Infrastructure\Asset\Assets;
use MWPD\BasicScaffold\Infrastructure\Service\DebugMode;
use MWPD\BasicScaffold\Infrastructure\ServiceBasedPlugin;
use MWPD\BasicScaffold\Infrastructure\ServiceContainer;

/**
 * A plugin configured with asset services, an asset loader and a main file.
 */
final class TestAssetPlugin extends ServiceBasedPlugin {

	public const MAIN_FILE = '/plugins/acme/acme.php';

	/**
	 * @var array<string,class-string>
	 */
	private array $services;

	private AssetRequests $requests;

	private ?DebugMode $debug_mode = null;

	private ?AssetLocation $location = null;

	private string $main_file = self::MAIN_FILE;

	/**
	 * @param array<string,class-string> $services  Services, in registration order.
	 * @param AssetRequests              $requests  Recorder the services receive.
	 * @param Assets                     $assets    Asset loader.
	 * @param ServiceContainer|null      $container Optional. Service container to use.
	 */
	public function __construct(
		array $services,
		AssetRequests $requests,
		Assets $assets,
		?ServiceContainer $container = null
	) {
		$this->services = $services;
		$this->requests = $requests;
		parent::__construct( false, null, $container, null, null, null, $assets );
	}

	public function with_debug_mode( DebugMode $debug_mode ): self {
		$this->debug_mode = $debug_mode;

		return $this;
	}

	public function without_main_file(): self {
		$this->main_file = '';

		return $this;
	}

	public function with_location( AssetLocation $location ): self {
		$this->location = $location;

		return $this;
	}

	protected function get_service_classes(): array {
		return $this->services;
	}

	protected function get_delegations(): array {
		$requests = $this->requests;
		/** @var array<class-string,callable> $delegations */
		$delegations = [ AssetRequests::class => static fn(): AssetRequests => $requests ];

		$debug_mode  = $this->debug_mode;
		if ( null !== $debug_mode ) {
			$delegations[ DebugMode::class ] = static fn(): DebugMode => $debug_mode;
		}

		$location = $this->location;
		if ( null !== $location ) {
			$delegations[ AssetLocation::class ] = static fn(): AssetLocation => $location;
		}

		return $delegations;
	}

	protected function get_main_file(): string {
		return $this->main_file;
	}
}
