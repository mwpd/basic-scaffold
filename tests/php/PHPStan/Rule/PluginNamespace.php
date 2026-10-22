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

namespace MWPD\BasicScaffold\Tests\PHPStan\Rule;

/**
 * The part of the code base the architecture rules apply to.
 *
 * That is the plugin's root namespace, without the parts of it that are not
 * plugin code, such as the tests. Rules receive it as one value, so they all
 * agree on what counts as plugin code.
 */
final class PluginNamespace {

	/**
	 * Root namespace, in lowercase and with a trailing separator.
	 */
	private string $root;

	/**
	 * Excluded namespaces, in lowercase and with a trailing separator.
	 *
	 * @var array<string>
	 */
	private array $excluded;

	/**
	 * Instantiate a PluginNamespace object.
	 *
	 * @param string        $root     Root namespace of the plugin.
	 * @param array<string> $excluded Namespaces inside it that are not plugin code.
	 */
	public function __construct( string $root, array $excluded = [] ) {
		// PHP compares namespaces and class names without regard to case.
		$this->root     = self::normalize( $root ) . '\\';
		$this->excluded = array_map(
			static fn( string $name ): string => self::normalize( $name ) . '\\',
			$excluded
		);
	}

	/**
	 * Check whether a class, interface, trait or function is plugin code.
	 *
	 * @param string $name Fully qualified name.
	 * @return bool Whether the name belongs to the plugin.
	 */
	public function contains( string $name ): bool {
		$name = self::normalize( $name );

		if ( strpos( $name, $this->root ) !== 0 ) {
			return false;
		}

		foreach ( $this->excluded as $excluded ) {
			if ( strpos( $name, $excluded ) === 0 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check whether code declared in a namespace is plugin code.
	 *
	 * Anonymous classes have no name of their own to check, so rules look at
	 * the namespace they are declared in.
	 *
	 * @param string|null $name Namespace, or null for the global one.
	 * @return bool Whether code in the namespace belongs to the plugin.
	 */
	public function contains_namespace( ?string $name ): bool {
		if ( $name === null ) {
			return false;
		}

		// Any name declared in the namespace stands in for the namespace.
		return $this->contains( $name . '\\_' );
	}

	/**
	 * Normalize a name for comparison.
	 *
	 * @param string $name Namespace or fully qualified name.
	 * @return string Name in lowercase, without leading or trailing separators.
	 */
	public static function normalize( string $name ): string {
		return strtolower( trim( $name, '\\' ) );
	}
}
