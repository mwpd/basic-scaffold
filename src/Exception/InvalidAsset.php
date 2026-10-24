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

namespace MWPD\BasicScaffold\Exception;

use InvalidArgumentException;

/**
 * Exception thrown when a script or style is declared in a way WordPress
 * cannot load as intended.
 */
final class InvalidAsset extends InvalidArgumentException implements BasicScaffoldException {

	/**
	 * Create a new instance of the exception for an empty asset handle.
	 */
	public static function for_empty_handle(): self {
		return new self( 'An asset handle cannot be empty. Start it with the plugin\'s asset prefix.' );
	}

	/**
	 * Create a new instance of the exception for a handle WordPress could not
	 * enqueue as it was registered.
	 *
	 * @param string $handle Handle that was passed.
	 */
	public static function for_handle( string $handle ): self {
		$message = \sprintf(
			'The asset handle "%s" contains a "?", whitespace or a control character. '
			. 'WordPress would register it, but not find it when it is enqueued.',
			$handle
		);

		return new self( $message );
	}

	/**
	 * Create a new instance of the exception for an asset path that is not a
	 * plain path inside the plugin.
	 *
	 * @param string $handle Handle of the asset.
	 * @param string $path   Path that was passed.
	 */
	public static function for_path( string $handle, string $path ): self {
		$message = \sprintf(
			'The path "%s" of the asset "%s" must be relative to the plugin root, such as "assets/css/notice.css": '
			. 'no leading slash, URL, backslash, query, "%%" escape, ".." segment or control character.',
			$path,
			$handle
		);

		return new self( $message );
	}

	/**
	 * Create a new instance of the exception for an inline style without CSS.
	 *
	 * @param string $handle Handle of the style.
	 */
	public static function for_empty_css( string $handle ): self {
		return new self( \sprintf( 'The inline style "%s" has no CSS.', $handle ) );
	}

	/**
	 * Create a new instance of the exception for inline CSS that contains a
	 * style tag of its own.
	 *
	 * @param string $handle Handle of the style.
	 */
	public static function for_style_tag( string $handle ): self {
		$message = \sprintf(
			'The inline style "%s" contains a <style> tag. Pass the CSS only; WordPress adds the tag.',
			$handle
		);

		return new self( $message );
	}

	/**
	 * Create a new instance of the exception for a media query on an inline
	 * style, where WordPress would ignore it.
	 *
	 * @param string $handle Handle of the style.
	 */
	public static function for_media_on_inline_style( string $handle ): self {
		$message = \sprintf(
			'The inline style "%s" cannot have a media type, as WordPress only applies it to stylesheet links. '
			. 'Wrap the CSS in an @media rule instead.',
			$handle
		);

		return new self( $message );
	}

	/**
	 * Create a new instance of the exception for an empty value of an asset
	 * option.
	 *
	 * @param string $handle Handle of the asset.
	 * @param string $option Option that was left empty, such as "version".
	 */
	public static function for_empty_option( string $handle, string $option ): self {
		return new self( \sprintf( 'The %s of the asset "%s" cannot be empty.', $option, $handle ) );
	}

	/**
	 * Create a new instance of the exception for an asset location without a
	 * default version.
	 *
	 * @param string $base_url URL the assets are served from.
	 */
	public static function for_empty_default_version( string $base_url ): self {
		return new self( \sprintf( 'The assets served from "%s" need a default version.', $base_url ) );
	}

	/**
	 * Create a new instance of the exception for a declaration the plugin
	 * cannot enqueue.
	 *
	 * @param mixed $declaration Value a service returned as an asset.
	 */
	public static function for_unsupported_declaration( $declaration ): self {
		$message = \sprintf(
			'A service declared an asset of type "%s". Declare a Style or a Script.',
			\is_object( $declaration ) ? \get_class( $declaration ) : \gettype( $declaration )
		);

		return new self( $message );
	}

	/**
	 * Create a new instance of the exception for a handle that is already
	 * registered with a different source or options.
	 *
	 * @param string $handle Handle of the asset.
	 */
	public static function for_conflicting_declaration( string $handle ): self {
		$message = \sprintf(
			'The asset "%s" is already registered with a different source or options. '
			. 'Give each asset its own handle, or declare it the same way everywhere.',
			$handle
		);

		return new self( $message );
	}

	/**
	 * Create a new instance of the exception for a main plugin file without a
	 * version to load the assets with.
	 *
	 * @param string $main_file Main plugin file.
	 */
	public static function for_missing_version( string $main_file ): self {
		$message = \sprintf(
			'The main plugin file "%s" has no "Version" header, so its assets have no version to bust caches with. '
			. 'Add the header, or return an AssetLocation of your own from get_asset_location().',
			$main_file
		);

		return new self( $message );
	}
}
