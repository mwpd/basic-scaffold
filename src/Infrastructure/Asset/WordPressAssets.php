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
use _WP_Dependency;

/**
 * Loads declared assets through the WordPress script and style APIs.
 *
 * WordPress keeps the first registration of a handle and ignores the ones
 * after it. So before registering, this compares the declaration with what is
 * already registered: the same declaration is only enqueued again, and a
 * different one is a conflict that would otherwise go unnoticed.
 */
final class WordPressAssets implements Assets {

	/**
	 * First WordPress version that accepts a loading strategy for scripts.
	 */
	private const STRATEGY_VERSION = '6.3';

	/**
	 * Register an asset with WordPress, if it is not yet, and enqueue it.
	 *
	 * @param Asset         $asset    Asset to enqueue.
	 * @param AssetLocation $location Where the plugin's assets are served from.
	 *
	 * @throws InvalidAsset If the asset is neither a Style nor a Script, or its
	 *                      handle is already registered with a different source
	 *                      or options.
	 */
	public function enqueue( Asset $asset, AssetLocation $location ): void {
		if ( $asset instanceof Style ) {
			$this->enqueue_style( $asset, $location );
			return;
		}

		if ( $asset instanceof Script ) {
			$this->enqueue_script( $asset, $location );
			return;
		}

		throw InvalidAsset::for_unsupported_declaration( $asset );
	}

	/**
	 * Register a style, if it is not yet, and enqueue it.
	 *
	 * @param Style         $style    Style to enqueue.
	 * @param AssetLocation $location Where the plugin's assets are served from.
	 *
	 * @throws InvalidAsset If the handle is registered with a different source
	 *                      or options.
	 */
	private function enqueue_style( Style $style, AssetLocation $location ): void {
		$handle  = $style->get_handle();
		$path    = $style->get_path();
		$src     = null === $path ? false : $location->get_url( $path );
		$version = $location->get_version( $style->get_version() );

		$registered = \wp_styles()->query( $handle, 'registered' );

		if ( $registered instanceof _WP_Dependency ) {
			if ( ! $this->is_registered_style( $registered, $style, $src, $version ) ) {
				throw InvalidAsset::for_conflicting_declaration( $handle );
			}
		} else {
			// An inline style is registered without a source, so WordPress
			// prints its CSS on its own.
			if ( ! \wp_register_style( $handle, $src, $style->get_dependencies(), $version, $style->get_media() ) ) {
				throw InvalidAsset::for_conflicting_declaration( $handle );
			}

			$css = $style->get_css();

			if ( null !== $css ) {
				\wp_add_inline_style( $handle, $css );
			}
		}

		\wp_enqueue_style( $handle );
	}

	/**
	 * Register a script, if it is not yet, and enqueue it.
	 *
	 * @param Script        $script   Script to enqueue.
	 * @param AssetLocation $location Where the plugin's assets are served from.
	 *
	 * @throws InvalidAsset If the handle is registered with a different source
	 *                      or options.
	 */
	private function enqueue_script( Script $script, AssetLocation $location ): void {
		$handle  = $script->get_handle();
		$src     = $location->get_url( $script->get_path() );
		$version = $location->get_version( $script->get_version() );

		$registered = \wp_scripts()->query( $handle, 'registered' );

		if ( $registered instanceof _WP_Dependency ) {
			if ( ! $this->is_registered_script( $registered, $script, $src, $version ) ) {
				throw InvalidAsset::for_conflicting_declaration( $handle );
			}
		} else {
			$registered = \wp_register_script(
				$handle,
				$src,
				$script->get_dependencies(),
				$version,
				$this->get_script_arguments( $script )
			);

			if ( ! $registered ) {
				throw InvalidAsset::for_conflicting_declaration( $handle );
			}
		}

		\wp_enqueue_script( $handle );
	}

	/**
	 * Get the last argument of wp_register_script() for a script.
	 *
	 * Since WordPress 6.3, it is an array with the footer flag and the loading
	 * strategy. Before that, it was the footer flag alone, and any non-empty
	 * array would read as true and move the script to the footer. So older
	 * versions get the flag, and the script loads without a strategy.
	 *
	 * @param Script $script Script to register.
	 *
	 * @return array{in_footer: bool, strategy?: string}|bool Footer flag and
	 *                                                       loading strategy.
	 */
	private function get_script_arguments( Script $script ) {
		if ( ! $this->supports_loading_strategies() ) {
			return $script->is_in_footer();
		}

		$arguments = [ 'in_footer' => $script->is_in_footer() ];
		$strategy  = $script->get_strategy();

		if ( null !== $strategy ) {
			$arguments['strategy'] = $strategy;
		}

		return $arguments;
	}

	/**
	 * Check whether a registered style is the one declared.
	 *
	 * @param _WP_Dependency $registered Style registered under the handle.
	 * @param Style          $style      Declared style.
	 * @param string|false   $src        URL of the declared stylesheet, or
	 *                                   false for an inline style.
	 * @param string         $version    Version of the declared style.
	 */
	private function is_registered_style( _WP_Dependency $registered, Style $style, $src, string $version ): bool {
		if ( $registered->src !== $src
			|| $registered->deps !== $style->get_dependencies()
			|| $registered->ver !== $version
			|| $registered->args !== $style->get_media() ) {
			return false;
		}

		$css = $style->get_css();

		return null === $css || \in_array( $css, (array) ( $registered->extra['after'] ?? [] ), true );
	}

	/**
	 * Check whether a registered script is the one declared.
	 *
	 * @param _WP_Dependency $registered Script registered under the handle.
	 * @param Script         $script     Declared script.
	 * @param string         $src        URL of the declared script.
	 * @param string         $version    Version of the declared script.
	 */
	private function is_registered_script(
		_WP_Dependency $registered,
		Script $script,
		string $src,
		string $version
	): bool {
		if ( $registered->src !== $src
			|| $registered->deps !== $script->get_dependencies()
			|| $registered->ver !== $version
			|| ( 1 === ( $registered->extra['group'] ?? 0 ) ) !== $script->is_in_footer() ) {
			return false;
		}

		// WordPress before 6.3 records no strategy to compare with.
		return ! $this->supports_loading_strategies()
			|| ( $registered->extra['strategy'] ?? null ) === $script->get_strategy();
	}

	/**
	 * Check whether WordPress accepts a loading strategy for scripts.
	 */
	private function supports_loading_strategies(): bool {
		return \version_compare( \get_bloginfo( 'version' ), self::STRATEGY_VERSION, '>=' );
	}
}
