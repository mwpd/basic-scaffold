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
 * Path of an asset file, relative to the plugin root.
 *
 * Assets ship as they are (design principle 1), so the path names the file
 * the browser loads, such as "assets/css/notice.css". It becomes a URL only
 * once the plugin knows where it is installed, see AssetLocation.
 */
final class AssetPath {

	/**
	 * Path relative to the plugin root, with forward slashes.
	 *
	 * @var non-empty-string
	 */
	private string $path;

	/**
	 * Instantiate an AssetPath object.
	 *
	 * @param non-empty-string $path Path relative to the plugin root.
	 */
	private function __construct( string $path ) {
		$this->path = $path;
	}

	/**
	 * Create an asset path from the path as a service declares it.
	 *
	 * @param string $handle Handle of the asset, for the message.
	 * @param string $path   Path relative to the plugin root.
	 *
	 * @throws InvalidAsset If the path is empty, absolute, a URL, or could
	 *                      leave the plugin directory.
	 */
	public static function for_asset( string $handle, string $path ): self {
		/*
		 * The path is joined to the plugin URL as it is. A leading slash, a
		 * scheme or a backslash would make the browser load something else
		 * than the file in the plugin, and a ".." segment could point outside
		 * of it.
		 */
		if ( '' === $path
			|| '/' === $path[0]
			|| false !== \strpbrk( $path, "\\\0:?#" )
			|| \in_array( '..', \explode( '/', $path ), true ) ) {
			throw InvalidAsset::for_path( $handle, $path );
		}

		return new self( $path );
	}

	/**
	 * Get the path as a string.
	 *
	 * @return non-empty-string Path relative to the plugin root.
	 */
	public function to_string(): string {
		return $this->path;
	}
}
