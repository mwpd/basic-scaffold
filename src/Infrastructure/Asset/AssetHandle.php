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
 * Handle WordPress knows a script or style by.
 *
 * WordPress registers any string as a handle, but enqueueing reads a "?" as
 * the start of arguments and looks for the part before it, so such a handle
 * is registered and then never found. Whitespace and control characters end
 * up in the id of the printed tag. A handle is therefore checked once, where
 * it is declared.
 */
final class AssetHandle {

	/**
	 * Handle, as it was declared.
	 *
	 * @var non-empty-string
	 */
	private string $handle;

	/**
	 * Instantiate an AssetHandle object.
	 *
	 * @param non-empty-string $handle Handle of the asset.
	 */
	private function __construct( string $handle ) {
		$this->handle = $handle;
	}

	/**
	 * Create an asset handle from the handle as a service declares it.
	 *
	 * @param string $handle Handle, with the plugin's asset prefix.
	 *
	 * @throws InvalidAsset If the handle is empty, or contains a "?",
	 *                      whitespace or a control character.
	 */
	public static function from_string( string $handle ): self {
		if ( '' === $handle ) {
			throw InvalidAsset::for_empty_handle();
		}

		if ( 1 === \preg_match( '/[?\s\x00-\x1F\x7F]/', $handle ) ) {
			throw InvalidAsset::for_handle( $handle );
		}

		return new self( $handle );
	}

	/**
	 * Get the handle as a string.
	 *
	 * @return non-empty-string Handle of the asset.
	 */
	public function to_string(): string {
		return $this->handle;
	}
}
