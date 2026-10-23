<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Asset;

use MWPD\BasicScaffold\Exception\InvalidAsset;
use MWPD\BasicScaffold\Infrastructure\Asset\Asset;
use MWPD\BasicScaffold\Infrastructure\Asset\AssetLocation;
use MWPD\BasicScaffold\Infrastructure\Asset\Assets;

/**
 * Records the assets the plugin enqueues, and the location it passes, and can
 * report a conflict for a handle.
 */
final class RecordingAssets implements Assets {

	/**
	 * @var list<Asset>
	 */
	public array $enqueued = [];

	/**
	 * @var list<AssetLocation>
	 */
	public array $locations = [];

	/**
	 * @var array<string,true>
	 */
	private array $conflicting = [];

	public function conflicting( string $handle ): self {
		$this->conflicting[ $handle ] = true;

		return $this;
	}

	public function enqueue( Asset $asset, AssetLocation $location ): void {
		if ( isset( $this->conflicting[ $asset->get_handle() ] ) ) {
			throw InvalidAsset::for_conflicting_declaration( $asset->get_handle() );
		}

		$this->enqueued[]  = $asset;
		$this->locations[] = $location;
	}

	/**
	 * @return list<string> Handles of the enqueued assets, in order.
	 */
	public function handles(): array {
		return array_map( static fn( Asset $asset ): string => $asset->get_handle(), $this->enqueued );
	}
}
