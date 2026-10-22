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

use MWPD\BasicScaffold\Tests\Architecture\RuleManifest;
use PhpParser\Node;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Every exception of the plugin has the same shape.
 *
 * An exception implements the marker interface, so everything the plugin
 * throws can be caught in one place. It extends the SPL exception that
 * matches its category, so callers can catch it without knowing the plugin.
 * It lives in the exception namespace, and is created through a named
 * constructor that states the cause. And everything in the exception
 * namespace is such an exception.
 *
 * Whether exceptions are final is checked by FinalByDefaultRule.
 *
 * @implements Rule<InClassNode>
 */
final class ExceptionShapeRule implements Rule {

	/**
	 * Exceptions of PHP's SPL that an exception of the plugin may extend.
	 */
	private const SPL_EXCEPTIONS = [
		'logicexception',
		'badfunctioncallexception',
		'badmethodcallexception',
		'domainexception',
		'invalidargumentexception',
		'lengthexception',
		'outofrangeexception',
		'runtimeexception',
		'outofboundsexception',
		'overflowexception',
		'rangeexception',
		'underflowexception',
		'unexpectedvalueexception',
	];

	/**
	 * Code the rule applies to.
	 */
	private PluginNamespace $plugin_namespace;

	/**
	 * Marker interface every exception of the plugin implements.
	 */
	private string $marker;

	/**
	 * Namespace the plugin's exceptions live in.
	 */
	private string $exception_namespace;

	/**
	 * Namespace the plugin's exceptions live in, for matching.
	 */
	private PluginNamespace $exceptions;

	/**
	 * Code in the exception namespace that is not an exception, normalized.
	 *
	 * @var array<string>
	 */
	private array $exempt;

	/**
	 * Instantiate an ExceptionShapeRule object.
	 *
	 * @param PluginNamespace $plugin_namespace    Code the rule applies to.
	 * @param string          $marker              Marker interface of the
	 *                                             plugin's exceptions.
	 * @param string          $exception_namespace Namespace the plugin's
	 *                                             exceptions live in.
	 * @param array<string>   $exempt              Code in the exception
	 *                                             namespace that is not an
	 *                                             exception.
	 */
	public function __construct(
		PluginNamespace $plugin_namespace,
		string $marker,
		string $exception_namespace,
		array $exempt
	) {
		$this->plugin_namespace    = $plugin_namespace;
		$this->marker              = trim( $marker, '\\' );
		$this->exception_namespace = trim( $exception_namespace, '\\' );
		$this->exceptions          = new PluginNamespace( $exception_namespace );
		$this->exempt              = array_map( [ PluginNamespace::class, 'normalize' ], $exempt );
	}

	/**
	 * Get the type of node the rule inspects.
	 *
	 * @return string Node class name.
	 */
	public function getNodeType(): string {
		return InClassNode::class;
	}

	/**
	 * Report an exception, or a member of the exception namespace, out of shape.
	 *
	 * @param InClassNode $node  Class node to inspect.
	 * @param Scope       $scope Scope of the class.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	public function processNode( Node $node, Scope $scope ): array {
		$class = $node->getClassReflection();
		$name  = $class->getName();

		if ( $class->isAnonymous() || ! $this->plugin_namespace->contains( $name ) ) {
			return [];
		}

		$in_namespace = $this->exceptions->contains( $name );
		$is_exception = $class->implementsInterface( $this->marker );

		if ( ! $is_exception ) {
			return $in_namespace
				&& PluginNamespace::normalize( $name ) !== PluginNamespace::normalize( $this->marker )
				&& ! in_array( PluginNamespace::normalize( $name ), $this->exempt, true )
				? [
					$this->build_error(
						sprintf(
							'%1$s is in %2$s but does not implement %3$s. Only the plugin\'s exceptions live there:'
							. ' implement %3$s, or move it out of %2$s.',
							$name,
							$this->exception_namespace,
							$this->marker
						)
					),
				]
				: [];
		}

		if ( $class->isInterface() ) {
			return [];
		}

		$errors = [];

		if ( ! $in_namespace ) {
			$errors[] = $this->build_error(
				sprintf(
					'Exception %1$s is outside %2$s. Move it to %2$s, where every exception of the plugin lives.',
					$name,
					$this->exception_namespace
				)
			);
		}

		if ( ! $this->extends_spl_exception( $class ) ) {
			$errors[] = $this->build_error(
				sprintf(
					'Exception %s does not extend an SPL exception. Extend the one that matches its category, such'
					. ' as InvalidArgumentException for invalid input or RuntimeException for a failure while'
					. ' running, so callers can catch it without knowing the plugin.',
					$name
				)
			);
		}

		if ( ! $class->isAbstract() && ! $this->has_named_constructor( $node ) ) {
			$errors[] = $this->build_error(
				sprintf(
					'Exception %s has no named constructor. Add a public static method that states the cause and'
					. ' returns self, such as from_path() or for_circular_reference(), and build its message there.',
					$name
				)
			);
		}

		return $errors;
	}

	/**
	 * Check whether a class extends one of the SPL exceptions.
	 *
	 * @param ClassReflection $reflection Class to check.
	 * @return bool Whether the class extends an SPL exception.
	 */
	private function extends_spl_exception( ClassReflection $reflection ): bool {
		foreach ( $reflection->getParentClassesNames() as $parent ) {
			if ( in_array( strtolower( $parent ), self::SPL_EXCEPTIONS, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether a class declares a named constructor.
	 *
	 * A named constructor is a public static method that returns `self` or
	 * `static`.
	 *
	 * @param InClassNode $node Class node to check.
	 * @return bool Whether the class declares a named constructor.
	 */
	private function has_named_constructor( InClassNode $node ): bool {
		foreach ( $node->getOriginalNode()->getMethods() as $method ) {
			$return_type = $method->getReturnType();

			if ( $method->isPublic()
				&& $method->isStatic()
				&& $return_type instanceof Name
				&& in_array( $return_type->toLowerString(), [ 'self', 'static' ], true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build the error for one exception out of shape.
	 *
	 * @param string $message What is wrong, where, and what to do instead.
	 * @return IdentifierRuleError Error to report.
	 */
	private function build_error( string $message ): IdentifierRuleError {
		return RuleErrorBuilder::message( $message )
			->identifier( RuleManifest::EXCEPTION_SHAPE )
			->tip( RuleManifest::get_explanation( RuleManifest::EXCEPTION_SHAPE ) )
			->build();
	}
}
