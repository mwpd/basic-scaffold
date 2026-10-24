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
 * A script file a service needs.
 *
 * A script is an immutable value. Each method that changes an option returns
 * a changed copy, so declare a script in one chained expression:
 *
 *     Script::file( 'mwpd-basic-scaffold-copy', 'assets/js/copy.js' )
 *         ->depends_on( 'wp-i18n' )
 *         ->deferred();
 *
 * By default, a script has no dependencies, loads in the head without a
 * loading strategy and carries the plugin's version, which the plugin reads
 * from its main file.
 */
final class Script implements Asset {

	/**
	 * Loading strategy that runs the script once the document is parsed.
	 */
	public const STRATEGY_DEFER = 'defer';

	/**
	 * Loading strategy that runs the script as soon as it has loaded.
	 */
	public const STRATEGY_ASYNC = 'async';

	/**
	 * Handle of the script.
	 *
	 * @var non-empty-string
	 */
	private string $handle;

	/**
	 * Path of the script file.
	 */
	private AssetPath $path;

	/**
	 * Handles of the scripts this one needs first.
	 *
	 * @var list<non-empty-string>
	 */
	private array $dependencies = [];

	/**
	 * Version of the script, or null for the plugin's version.
	 *
	 * @var non-empty-string|null
	 */
	private ?string $version = null;

	/**
	 * Whether the script is printed at the end of the body.
	 */
	private bool $in_footer = false;

	/**
	 * Loading strategy, or null to load the script the classic, blocking way.
	 *
	 * @var self::STRATEGY_*|null
	 */
	private ?string $strategy = null;

	/**
	 * Instantiate a Script object.
	 *
	 * @param non-empty-string $handle Handle of the script.
	 * @param AssetPath        $path   Path of the script file.
	 */
	private function __construct( string $handle, AssetPath $path ) {
		$this->handle = $handle;
		$this->path   = $path;
	}

	/**
	 * Declare a script that ships with the plugin.
	 *
	 * @param string $handle Handle of the script, with the plugin's asset
	 *                       prefix.
	 * @param string $path   Path of the script, relative to the plugin root.
	 *
	 * @throws InvalidAsset If the handle is empty or one WordPress could not
	 *                      enqueue, or the path is not a plain path inside the
	 *                      plugin.
	 */
	public static function file( string $handle, string $path ): self {
		$handle = AssetHandle::from_string( $handle )->to_string();

		return new self( $handle, AssetPath::for_asset( $handle, $path ) );
	}

	/**
	 * Get a copy of the script that needs other scripts first.
	 *
	 * The handles are WordPress script handles, such as "wp-i18n" or another
	 * script of the plugin, and replace any dependencies set before. They have
	 * nothing to do with the service IDs of HasDependencies.
	 *
	 * @param string ...$handles Handles of the scripts to load first.
	 *
	 * @throws InvalidAsset If a handle is empty.
	 */
	public function depends_on( string ...$handles ): self {
		$script               = clone $this;
		$script->dependencies = [];

		foreach ( $handles as $handle ) {
			if ( '' === $handle ) {
				throw InvalidAsset::for_empty_option( $this->handle, 'dependency' );
			}

			$script->dependencies[] = $handle;
		}

		return $script;
	}

	/**
	 * Get a copy of the script with a version of its own.
	 *
	 * Browsers cache a script by its URL, which includes the version. Without
	 * one, the script carries the plugin's version, which changes with every
	 * release.
	 *
	 * @param string $version Version of the script.
	 *
	 * @throws InvalidAsset If the version is empty.
	 */
	public function with_version( string $version ): self {
		if ( '' === $version ) {
			throw InvalidAsset::for_empty_option( $this->handle, 'version' );
		}

		$script          = clone $this;
		$script->version = $version;

		return $script;
	}

	/**
	 * Get a copy of the script that is printed at the end of the body rather
	 * than in the head.
	 */
	public function in_footer(): self {
		$script            = clone $this;
		$script->in_footer = true;

		return $script;
	}

	/**
	 * Get a copy of the script that runs once the document has been parsed,
	 * in the order of its dependencies.
	 *
	 * This replaces async(). WordPress treats the strategy as a request: it
	 * loads the script the classic way when a script that depends on it, or
	 * one it depends on, cannot be deferred. WordPress before 6.3 ignores the
	 * strategy and loads the script the classic way.
	 */
	public function deferred(): self {
		$script           = clone $this;
		$script->strategy = self::STRATEGY_DEFER;

		return $script;
	}

	/**
	 * Get a copy of the script that runs as soon as it has loaded, in no
	 * particular order.
	 *
	 * This replaces deferred(). Like it, the strategy is a request that
	 * WordPress may turn down for the script's dependencies, and that
	 * WordPress before 6.3 ignores.
	 */
	public function async(): self {
		$script           = clone $this;
		$script->strategy = self::STRATEGY_ASYNC;

		return $script;
	}

	/**
	 * Get the handle WordPress knows the script by.
	 *
	 * @return non-empty-string Handle of the script.
	 */
	public function get_handle(): string {
		return $this->handle;
	}

	/**
	 * Get the path of the script file.
	 *
	 * @return AssetPath Path of the script file.
	 */
	public function get_path(): AssetPath {
		return $this->path;
	}

	/**
	 * Get the handles of the scripts this one needs first.
	 *
	 * @return list<non-empty-string> Handles of the dependencies.
	 */
	public function get_dependencies(): array {
		return $this->dependencies;
	}

	/**
	 * Get the version of the script.
	 *
	 * @return non-empty-string|null Version of the script, or null for the
	 *                               plugin's version.
	 */
	public function get_version(): ?string {
		return $this->version;
	}

	/**
	 * Check whether the script is printed at the end of the body.
	 *
	 * @return bool Whether the script goes in the footer.
	 */
	public function is_in_footer(): bool {
		return $this->in_footer;
	}

	/**
	 * Get the loading strategy of the script.
	 *
	 * @return self::STRATEGY_*|null Loading strategy, or null for the classic,
	 *                               blocking way.
	 */
	public function get_strategy(): ?string {
		return $this->strategy;
	}
}
