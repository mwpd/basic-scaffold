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

declare(strict_types=1);

namespace MWPD\BasicScaffold\Infrastructure;

use Exception;

/**
 * Bundled fallback autoloader.
 *
 * Ideally, you would opt to fully embrace Composer and not need this at all.
 *
 * WordPress being far from ideal, though, it makes sense to include this for
 * the average plugin.
 *
 * @phpstan-type AutoloaderNamespace array{
 *     root: string,
 *     base_dir: string,
 *     prefix: string,
 *     suffix: string,
 *     lowercase: bool,
 *     underscores: bool,
 * }
 */
final class Autoloader {



	private const ROOT = 'root';

	private const BASE_DIR = 'base_dir';

	private const PREFIX = 'prefix';

	private const SUFFIX = 'suffix';

	private const LOWERCASE = 'lowercase';

	private const UNDERSCORES = 'underscores';

	private const DEFAULT_PREFIX = '';

	private const DEFAULT_SUFFIX = '.php';

	private const AUTOLOAD_METHOD = 'autoload';

	/**
	 * Array containing the registered namespace structures.
	 *
	 * @var array<int, AutoloaderNamespace>
	 */
	private array $namespaces = [];

	/**
	 * Destructor for the Autoloader class.
	 *
	 * The destructor automatically unregisters the autoload callback function
	 * with the SPL autoload system.
	 *
	 * @return void
	 */
	public function __destruct() {
		$this->unregister();
	}

	/**
	 * Registers the autoload callback with the SPL autoload system.
	 *
	 * @throws Exception If the autoloader could not be registered.
	 */
	public function register(): void {
		\spl_autoload_register( [ $this, self::AUTOLOAD_METHOD ] );
	}

	/**
	 * Unregisters the autoload callback with the SPL autoload system.
	 */
	public function unregister(): void {
		\spl_autoload_unregister( [ $this, self::AUTOLOAD_METHOD ] );
	}

	/**
	 * Add a specific namespace structure with our custom autoloader.
	 *
	 * @param string  $root        Root namespace name.
	 * @param string  $base_dir    Directory containing the class files.
	 * @param string  $prefix      Optional. Prefix to be added before the
	 *                             class. Defaults to an empty string.
	 * @param string  $suffix      Optional. Suffix to be added after the
	 *                             class. Defaults to '.php'.
	 * @param boolean $lowercase   Optional. Whether the class should be
	 *                             changed to lowercase. Defaults to false.
	 * @param boolean $underscores Optional. Whether the underscores should be
	 *                             changed to hyphens. Defaults to false.
	 */
	public function add_namespace(
		string $root,
		string $base_dir,
		string $prefix = self::DEFAULT_PREFIX,
		string $suffix = self::DEFAULT_SUFFIX,
		bool $lowercase = false,
		bool $underscores = false
	): self {
		$this->namespaces[] = [
			self::ROOT        => $this->normalize_root( $root ),
			self::BASE_DIR    => $this->ensure_trailing_slash( $base_dir ),
			self::PREFIX      => $prefix,
			self::SUFFIX      => $suffix,
			self::LOWERCASE   => $lowercase,
			self::UNDERSCORES => $underscores,
		];

		return $this;
	}

	/**
	 * The autoload function that gets registered with the SPL Autoloader
	 * system.
	 *
	 * @param string $class_string The class that got requested by the spl_autoloader.
	 */
	public function autoload( string $class_string ): void {

		// Iterate over namespaces to find a match.
		foreach ( $this->namespaces as $namespace ) {

			// Move on if the object does not belong to the current namespace.
			if ( 0 !== \strpos( $class_string, $namespace[ self::ROOT ] ) ) {
				continue;
			}

			// Remove namespace root level to correspond with root filesystem.
			$filename = \str_replace(
				$namespace[ self::ROOT ],
				'',
				$class_string
			);

			// Remove a leading backslash from the class name.
			$filename = $this->remove_leading_backslash( $filename );

			// Replace the namespace separator "\" by the system-dependent
			// directory separator.
			$filename = \str_replace(
				'\\',
				DIRECTORY_SEPARATOR,
				$filename
			);

			// Change to lower case if requested.
			if ( true === $namespace[ self::LOWERCASE ] ) {
				$filename = \strtolower( $filename );
			}

			// Change underscores into hyphens if requested.
			if ( true === $namespace[ self::UNDERSCORES ] ) {
				$filename = \str_replace( '_', '-', $filename );
			}

			// Add base_dir, prefix and suffix.
			$filepath = $namespace[ self::BASE_DIR ]
				. $namespace[ self::PREFIX ]
				. $filename
				. $namespace[ self::SUFFIX ];

			// Require the file if it exists and is readable.
			if ( \is_readable( $filepath ) ) {
				/**
				 * This include cannot be followed to be statically analyzed.
				 *
				 * @psalm-suppress UnresolvableInclude
				 */
				require $filepath;
			}
		}
	}

	/**
	 * Normalize a namespace root.
	 *
	 * @param string $root Namespace root that needs to be normalized.
	 *
	 * @return string Normalized namespace root.
	 */
	private function normalize_root( string $root ): string {
		$root = $this->remove_leading_backslash( $root );

		return $this->ensure_trailing_backslash( $root );
	}

	/**
	 * Remove a leading backslash from a namespace.
	 *
	 * @param string $namespace_string Namespace to remove the leading backslash from.
	 *
	 * @return string Modified namespace.
	 */
	private function remove_leading_backslash( string $namespace_string ): string {
		return \ltrim( $namespace_string, '\\' );
	}

	/**
	 * Make sure a namespace ends with a trailing backslash.
	 *
	 * @param string $namespace_string Namespace to check the trailing backslash of.
	 *
	 * @return string Modified namespace.
	 */
	private function ensure_trailing_backslash( string $namespace_string ): string {
		return \rtrim( $namespace_string, '\\' ) . '\\';
	}

	/**
	 * Make sure a path ends with a trailing slash.
	 *
	 * @param string $path Path to check the trailing slash of.
	 *
	 * @return string Modified path.
	 */
	private function ensure_trailing_slash( string $path ): string {
		return \rtrim( $path, '/' ) . '/';
	}
}
