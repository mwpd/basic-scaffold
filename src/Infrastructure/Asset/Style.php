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
 * A stylesheet a service needs, either a file in the plugin or a few lines of
 * CSS printed inline.
 *
 * A style is an immutable value. Each method that changes an option returns a
 * changed copy, so declare a style in one chained expression:
 *
 *     Style::file( 'mwpd-basic-scaffold-header', 'assets/css/header.css' )
 *         ->depends_on( 'wp-components' )
 *         ->for_media( 'screen' );
 *
 * By default, a style has no dependencies, loads for all media and carries
 * the plugin's version, which the plugin reads from its main file.
 */
final class Style implements Asset {

	/**
	 * Handle of the style.
	 *
	 * @var non-empty-string
	 */
	private string $handle;

	/**
	 * Path of the stylesheet, or null for an inline style.
	 */
	private ?AssetPath $path;

	/**
	 * CSS of an inline style, or null for a stylesheet file.
	 *
	 * @var non-empty-string|null
	 */
	private ?string $css;

	/**
	 * Handles of the styles this one needs first.
	 *
	 * @var list<non-empty-string>
	 */
	private array $dependencies = [];

	/**
	 * Version of the style, or null for the plugin's version.
	 *
	 * @var non-empty-string|null
	 */
	private ?string $version = null;

	/**
	 * Media the stylesheet applies to.
	 *
	 * @var non-empty-string
	 */
	private string $media = 'all';

	/**
	 * Instantiate a Style object.
	 *
	 * @param non-empty-string      $handle Handle of the style.
	 * @param AssetPath|null        $path   Path of the stylesheet, or null.
	 * @param non-empty-string|null $css    CSS of an inline style, or null.
	 */
	private function __construct( string $handle, ?AssetPath $path, ?string $css ) {
		$this->handle = $handle;
		$this->path   = $path;
		$this->css    = $css;
	}

	/**
	 * Declare a stylesheet that ships with the plugin.
	 *
	 * @param string $handle Handle of the style, with the plugin's asset prefix.
	 * @param string $path   Path of the stylesheet, relative to the plugin root.
	 *
	 * @throws InvalidAsset If the handle is empty or the path is not a plain
	 *                      path inside the plugin.
	 */
	public static function file( string $handle, string $path ): self {
		$handle = self::validate_handle( $handle );

		return new self( $handle, AssetPath::for_asset( $handle, $path ), null );
	}

	/**
	 * Declare CSS that is printed inline, without a file of its own.
	 *
	 * This suits a few lines of CSS, for which a separate request would cost
	 * more than it saves. WordPress prints them in a style tag under the
	 * handle, so a theme can still remove them by dequeuing it.
	 *
	 * @param string $handle Handle of the style, with the plugin's asset prefix.
	 * @param string $css    CSS to print, without a style tag.
	 *
	 * @throws InvalidAsset If the handle or the CSS is empty, or the CSS contains
	 *                      a style tag.
	 */
	public static function inline( string $handle, string $css ): self {
		$handle = self::validate_handle( $handle );

		if ( '' === $css || '' === \trim( $css ) ) {
			throw InvalidAsset::for_empty_css( $handle );
		}

		// WordPress would strip the tags with a notice; a closing tag inside
		// the CSS would end the style element early.
		if ( false !== \stripos( $css, '<style' ) || false !== \stripos( $css, '</style' ) ) {
			throw InvalidAsset::for_style_tag( $handle );
		}

		return new self( $handle, null, $css );
	}

	/**
	 * Get a copy of the style that needs other styles first.
	 *
	 * The handles are WordPress style handles, such as "wp-components" or
	 * another style of the plugin, and replace any dependencies set before.
	 * They have nothing to do with the service IDs of HasDependencies.
	 *
	 * @param string ...$handles Handles of the styles to load first.
	 *
	 * @throws InvalidAsset If a handle is empty.
	 */
	public function depends_on( string ...$handles ): self {
		$style               = clone $this;
		$style->dependencies = [];

		foreach ( $handles as $handle ) {
			if ( '' === $handle ) {
				throw InvalidAsset::for_empty_option( $this->handle, 'dependency' );
			}

			$style->dependencies[] = $handle;
		}

		return $style;
	}

	/**
	 * Get a copy of the style with a version of its own.
	 *
	 * Browsers cache a style by its URL, which includes the version. Without
	 * one, the style carries the plugin's version, which changes with every
	 * release.
	 *
	 * @param string $version Version of the style.
	 *
	 * @throws InvalidAsset If the version is empty.
	 */
	public function with_version( string $version ): self {
		if ( '' === $version ) {
			throw InvalidAsset::for_empty_option( $this->handle, 'version' );
		}

		$style          = clone $this;
		$style->version = $version;

		return $style;
	}

	/**
	 * Get a copy of the stylesheet that only applies to some media.
	 *
	 * @param string $media Media type or query, such as "print" or
	 *                      "(prefers-color-scheme: dark)".
	 *
	 * @throws InvalidAsset If the media is empty, or the style is inline.
	 */
	public function for_media( string $media ): self {
		if ( null === $this->path ) {
			throw InvalidAsset::for_media_on_inline_style( $this->handle );
		}

		if ( '' === $media ) {
			throw InvalidAsset::for_empty_option( $this->handle, 'media' );
		}

		$style        = clone $this;
		$style->media = $media;

		return $style;
	}

	/**
	 * Get the handle WordPress knows the style by.
	 *
	 * @return non-empty-string Handle of the style.
	 */
	public function get_handle(): string {
		return $this->handle;
	}

	/**
	 * Get the path of the stylesheet.
	 *
	 * @return AssetPath|null Path of the stylesheet, or null for an inline style.
	 */
	public function get_path(): ?AssetPath {
		return $this->path;
	}

	/**
	 * Get the CSS of an inline style.
	 *
	 * @return non-empty-string|null CSS to print, or null for a stylesheet file.
	 */
	public function get_css(): ?string {
		return $this->css;
	}

	/**
	 * Get the handles of the styles this one needs first.
	 *
	 * @return list<non-empty-string> Handles of the dependencies.
	 */
	public function get_dependencies(): array {
		return $this->dependencies;
	}

	/**
	 * Get the version of the style.
	 *
	 * @return non-empty-string|null Version of the style, or null for the
	 *                               plugin's version.
	 */
	public function get_version(): ?string {
		return $this->version;
	}

	/**
	 * Get the media the stylesheet applies to.
	 *
	 * @return non-empty-string Media type or query.
	 */
	public function get_media(): string {
		return $this->media;
	}

	/**
	 * Check that a handle is not empty.
	 *
	 * @param string $handle Handle to check.
	 *
	 * @throws InvalidAsset If the handle is empty.
	 *
	 * @return non-empty-string Handle that was checked.
	 */
	private static function validate_handle( string $handle ): string {
		if ( '' === $handle ) {
			throw InvalidAsset::for_empty_handle();
		}

		return $handle;
	}
}
