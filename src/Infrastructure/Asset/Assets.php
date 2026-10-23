<?php
/**
 * MWPD Basic Plugin Scaffold.
 *
 * @package   MWPD\BasicScaffold
 * @author    Alain Schlesser <alain.schlesser@gmail.com>
 * @license   MIT
 * @link      https://www.mwpd.io/
 * @copyright 2019 Alain Schlesser
 */

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Infrastructure\Asset;

use MWPD\BasicScaffold\Exception\InvalidAsset;

/**
 * The edge where declared assets are handed to WordPress.
 *
 * The plugin decides when a service is asked for its assets; an Assets
 * implementation only knows how to load one. WordPressAssets is the built-in
 * one, and a test or another loader takes its place through the plugin's
 * constructor.
 */
interface Assets {

	/**
	 * Register an asset with WordPress, if it is not yet, and enqueue it.
	 *
	 * An asset that is already registered with the same source and options is
	 * only enqueued, so two services may declare the same asset.
	 *
	 * @param Asset         $asset    Asset to enqueue.
	 * @param AssetLocation $location Where the plugin's assets are served from.
	 *
	 * @throws InvalidAsset If the asset is not a kind this implementation
	 *                      loads, or its handle is already registered with a
	 *                      different source or options.
	 */
	public function enqueue( Asset $asset, AssetLocation $location ): void;
}
