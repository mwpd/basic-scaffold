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

use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use Throwable;

/**
 * Where principle 5 allows `new`, shared by the rules that check it.
 */
final class CreationAllowances {

	/**
	 * Interfaces whose implementations are factories.
	 *
	 * @var array<string>
	 */
	private array $factories;

	/**
	 * Value objects, normalized.
	 *
	 * @var array<string>
	 */
	private array $value_objects;

	/**
	 * Reflection, to inspect the classes being created.
	 */
	private ReflectionProvider $reflection;

	/**
	 * Instantiate a CreationAllowances object.
	 *
	 * @param array<string>      $factories     Interfaces whose implementations
	 *                                          are factories.
	 * @param array<string>      $value_objects Classes that may be created
	 *                                          anywhere.
	 * @param ReflectionProvider $reflection    Reflection, to inspect the
	 *                                          classes being created.
	 */
	public function __construct( array $factories, array $value_objects, ReflectionProvider $reflection ) {
		$this->factories     = $factories;
		$this->value_objects = array_map( [ PluginNamespace::class, 'normalize' ], $value_objects );
		$this->reflection    = $reflection;
	}

	/**
	 * Check whether a class is a factory, which may create anything.
	 *
	 * A factory is a class named `…Factory`, a class implementing an interface
	 * named `…Factory`, or one implementing a configured factory interface.
	 *
	 * @param ClassReflection $reflection Class to check.
	 * @return bool Whether it is a factory.
	 */
	public function is_factory( ClassReflection $reflection ): bool {
		if ( ! $reflection->isAnonymous() && substr( $reflection->getName(), -7 ) === 'Factory' ) {
			return true;
		}

		foreach ( $reflection->getInterfaces() as $interface ) {
			if ( substr( $interface->getName(), -7 ) === 'Factory' ) {
				return true;
			}
		}

		foreach ( $this->factories as $factory ) {
			if ( $reflection->implementsInterface( $factory ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether a class may be created anywhere.
	 *
	 * @param string $class_name Class being created.
	 * @return bool Whether it is a value object or throwable.
	 */
	public function is_value_object( string $class_name ): bool {
		if ( in_array( PluginNamespace::normalize( $class_name ), $this->value_objects, true ) ) {
			return true;
		}

		return $this->reflection->hasClass( $class_name )
			&& $this->reflection->getClass( $class_name )->implementsInterface( Throwable::class );
	}
}
