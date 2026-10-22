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

use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;

/**
 * Tells which function a call refers to.
 */
final class CalledFunction {

	/**
	 * Get the name of the function a call refers to.
	 *
	 * A function PHPStan does not know, like `_doing_it_wrong()` without the
	 * WordPress stubs, keeps the name it is called by. An unqualified name then
	 * falls back to the global function, as in PHP.
	 *
	 * @param FuncCall           $call       Function call.
	 * @param Scope              $scope      Scope of the call.
	 * @param ReflectionProvider $reflection Reflection, to resolve the name.
	 * @return string|null Lowercase name, or null for a call through an
	 *                     expression.
	 */
	public static function name_of( FuncCall $call, Scope $scope, ReflectionProvider $reflection ): ?string {
		if ( ! $call->name instanceof Name ) {
			return null;
		}

		return strtolower(
			$reflection->hasFunction( $call->name, $scope )
				? $reflection->getFunction( $call->name, $scope )->getName()
				: ltrim( $call->name->toString(), '\\' )
		);
	}

	/**
	 * Describe where a call is, for a message.
	 *
	 * @param Scope $scope Scope of the call.
	 * @return string Method or function, and whether the call is in a closure.
	 */
	public static function describe_location( Scope $scope ): string {
		$class    = $scope->getClassReflection();
		$function = $scope->getFunction();
		$name     = $function === null ? 'the file' : $function->getName() . '()';

		if ( $class !== null && $function !== null ) {
			$name = ( $class->isAnonymous() ? 'class@anonymous' : $class->getName() ) . '::' . $name;
		}

		return $scope->isInAnonymousFunction() ? 'a closure in ' . $name : $name;
	}
}
