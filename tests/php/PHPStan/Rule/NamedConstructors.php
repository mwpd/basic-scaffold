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
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Finds the named constructors of an exception.
 *
 * A named constructor is a public static method that returns `self`,
 * `static` or the class itself. It may be declared in the class, a parent or
 * a trait.
 */
final class NamedConstructors {

	/**
	 * Get the names of a class's named constructors.
	 *
	 * @param ClassReflection $reflection Class to inspect.
	 * @return array<string> Names of its named constructors.
	 */
	public static function of( ClassReflection $reflection ): array {
		$names = [];
		foreach ( $reflection->getNativeReflection()->getMethods( ReflectionMethod::IS_STATIC ) as $method ) {
			$return_type = $method->getReturnType();

			if ( $method->isPublic()
				&& $return_type instanceof ReflectionNamedType
				&& in_array(
					strtolower( $return_type->getName() ),
					[ 'self', 'static', strtolower( $method->getDeclaringClass()->getName() ) ],
					true
				) ) {
				$names[] = $method->getName();
			}
		}

		return $names;
	}
}
