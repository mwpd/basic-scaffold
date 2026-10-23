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
 * Where the plugin's assets are served from, and the version they carry by
 * default.
 *
 * The plugin builds this from its main file the first time a request has an
 * asset to enqueue: the URL of the plugin directory, and the "Version" header.
 * Nothing reads a constant for it. A composition root that serves its assets
 * from somewhere else, such as a CDN, delegates AssetLocation to its own.
 */
final class AssetLocation {

	/**
	 * URL of the plugin directory, without a trailing slash.
	 */
	private string $base_url;

	/**
	 * Version assets carry unless they set their own.
	 *
	 * @var non-empty-string
	 */
	private string $version;

	/**
	 * Instantiate an AssetLocation object.
	 *
	 * @param string $base_url URL of the directory the asset paths start
	 *                         from, with or without a trailing slash.
	 * @param string $version  Version assets carry unless they set their own.
	 *
	 * @throws InvalidAsset If the version is empty.
	 */
	public function __construct( string $base_url, string $version ) {
		if ( '' === $version ) {
			throw InvalidAsset::for_empty_default_version( $base_url );
		}

		$this->base_url = \rtrim( $base_url, '/' );
		$this->version  = $version;
	}

	/**
	 * Get the URL an asset file is served from.
	 *
	 * @param AssetPath $path Path of the asset, relative to the plugin root.
	 *
	 * @return string URL of the asset file.
	 */
	public function get_url( AssetPath $path ): string {
		return $this->base_url . '/' . $path->to_string();
	}

	/**
	 * Get the version an asset carries.
	 *
	 * @param string|null $version Version the asset sets itself, or null.
	 *
	 * @return non-empty-string The asset's own version if it has one, the
	 *                          default version otherwise.
	 */
	public function get_version( ?string $version ): string {
		return null === $version || '' === $version ? $this->version : $version;
	}
}
