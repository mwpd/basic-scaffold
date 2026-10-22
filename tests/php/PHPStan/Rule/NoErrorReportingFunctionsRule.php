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
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * No WordPress error reporting inside the plugin.
 *
 * Inside the plugin, every error is an exception. Logging it, raising a
 * notice or calling _doing_it_wrong() decides how the error reaches the user
 * at the place where it happens, which is the job of the code that hands
 * control back to WordPress.
 *
 * WP_Error is checked by the NoWordPressError*Rule classes, under the same
 * identifier.
 *
 * @implements Rule<FuncCall>
 */
final class NoErrorReportingFunctionsRule implements Rule {

	/**
	 * Functions that report errors the WordPress way.
	 */
	private const FUNCTIONS = [
		'error_log',
		'trigger_error',
		'user_error',
		'_doing_it_wrong',
	];

	/**
	 * Code the rule applies to.
	 */
	private PluginNamespace $plugin_namespace;

	/**
	 * Reflection, to tell which function a call refers to.
	 */
	private ReflectionProvider $reflection;

	/**
	 * Instantiate a NoErrorReportingFunctionsRule object.
	 *
	 * @param PluginNamespace    $plugin_namespace Code the rule applies to.
	 * @param ReflectionProvider $reflection       Reflection, to tell which
	 *                                             function a call refers to.
	 */
	public function __construct( PluginNamespace $plugin_namespace, ReflectionProvider $reflection ) {
		$this->plugin_namespace = $plugin_namespace;
		$this->reflection       = $reflection;
	}

	/**
	 * Get the type of node the rule inspects.
	 *
	 * @return string Node class name.
	 */
	public function getNodeType(): string {
		return FuncCall::class;
	}

	/**
	 * Report calls to the functions that report errors the WordPress way.
	 *
	 * @param FuncCall $node  Function call to inspect.
	 * @param Scope    $scope Scope of the call.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	public function processNode( Node $node, Scope $scope ): array {
		if ( ! $node->name instanceof Name
			|| ! $this->plugin_namespace->contains_namespace( $scope->getNamespace() ) ) {
			return [];
		}

		// A function PHPStan does not know, like _doing_it_wrong() without the
		// WordPress stubs, keeps the name it is called by. An unqualified
		// name then falls back to the global function, as in PHP.
		$function = strtolower(
			$this->reflection->hasFunction( $node->name, $scope )
				? $this->reflection->getFunction( $node->name, $scope )->getName()
				: ltrim( $node->name->toString(), '\\' )
		);

		if ( ! in_array( $function, self::FUNCTIONS, true ) ) {
			return [];
		}

		return [
			RuleErrorBuilder::message(
				sprintf(
					'%s() reports an error where it happens. Throw a dedicated exception from the exception '
					. 'namespace instead; the code that hands control back to WordPress decides what the user sees.',
					$function
				)
			)
				->identifier( RuleManifest::NO_WORDPRESS_ERROR_MECHANISMS )
				->tip( RuleManifest::get_explanation( RuleManifest::NO_WORDPRESS_ERROR_MECHANISMS ) )
				->build(),
		];
	}
}
