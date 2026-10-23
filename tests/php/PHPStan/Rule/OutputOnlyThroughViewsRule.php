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
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Print_;
use PhpParser\Node\Stmt\Echo_;
use PhpParser\Node\Stmt\InlineHTML;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Services output rendered views rather than building their own markup.
 *
 * Only direct method calls count: a string variable or concatenation loses
 * the type that proves the output came from the rendering system.
 *
 * @implements Rule<Node>
 */
final class OutputOnlyThroughViewsRule implements Rule {

	private PluginNamespace $plugin_namespace;

	private string $infrastructure;

	/**
	 * Production template directories, without trailing slashes.
	 *
	 * @var array<string>
	 */
	private array $views;

	/**
	 * Classes exempt from the rule, normalized as PHP class names.
	 *
	 * @var array<string>
	 */
	private array $allowed;

	private ReflectionProvider $reflection;

	/**
	 * @param PluginNamespace    $plugin_namespace Code the rule applies to.
	 * @param string             $infrastructure   Namespace of the rendering interfaces.
	 * @param array<string>      $views            Production template directories.
	 * @param array<string>      $allowed          Named classes allowed to output directly.
	 * @param ReflectionProvider $reflection       Reflection, to resolve function names.
	 */
	public function __construct(
		PluginNamespace $plugin_namespace,
		string $infrastructure,
		array $views,
		array $allowed,
		ReflectionProvider $reflection
	) {
		$this->plugin_namespace = $plugin_namespace;
		$this->infrastructure   = trim( $infrastructure, '\\' );
		$this->views            = array_map( static fn( string $view ): string => rtrim( $view, '/' ), $views );
		$this->allowed          = array_map( [ PluginNamespace::class, 'normalize' ], $allowed );
		$this->reflection       = $reflection;
	}

	public function getNodeType(): string {
		return Node::class;
	}

	/**
	 * @param Node  $node  Node to inspect.
	 * @param Scope $scope Scope of the node.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	public function processNode( Node $node, Scope $scope ): array {
		if ( ! $node instanceof Echo_ && ! $node instanceof Print_
			&& ! $node instanceof FuncCall && ! $node instanceof InlineHTML ) {
			return [];
		}

		if ( ! $this->plugin_namespace->contains_namespace( $scope->getNamespace() ) ) {
			return [];
		}

		foreach ( $this->views as $views ) {
			if ( strpos( $scope->getFile(), $views . '/' ) === 0 ) {
				return [];
			}
		}

		$class = $scope->getClassReflection();
		if ( $class !== null && ! $class->isAnonymous()
			&& in_array( PluginNamespace::normalize( $class->getName() ), $this->allowed, true ) ) {
			return [];
		}

		$outputs = [];
		if ( $node instanceof Echo_ ) {
			$outputs = $node->exprs;
		} elseif ( $node instanceof Print_ ) {
			$outputs = [ $node->expr ];
		} elseif ( $node instanceof FuncCall ) {
			$function = CalledFunction::name_of( $node, $scope, $this->reflection );
			if ( ! in_array( $function, [ 'printf', 'vprintf' ], true ) ) {
				return [];
			}

			// A format string or a formatting argument is output too. Requiring
			// every argument to be a view call prevents a rendered sibling from
			// hiding markup or values assembled in the service.
			foreach ( $node->getArgs() as $argument ) {
				$outputs[] = $argument->value;
			}
		}

		foreach ( $outputs as $output ) {
			if ( ! $this->is_view_call( $output, $scope ) ) {
				return [ $this->build_error( $scope ) ];
			}
		}

		return $node instanceof InlineHTML ? [ $this->build_error( $scope ) ] : [];
	}

	/**
	 * @param Expr  $output Expression being output.
	 * @param Scope $scope  Scope of the expression.
	 * @return bool Whether PHPStan proves the receiver is part of the rendering system.
	 */
	private function is_view_call( Expr $output, Scope $scope ): bool {
		if ( ! $output instanceof MethodCall ) {
			return false;
		}

		$type = $scope->getType( $output->var );

		return ( new ObjectType( $this->infrastructure . '\Renderable' ) )->isSuperTypeOf( $type )->yes()
			|| ( new ObjectType( $this->infrastructure . '\View' ) )->isSuperTypeOf( $type )->yes();
	}

	private function build_error( Scope $scope ): IdentifierRuleError {
		$class = $scope->getClassReflection();
		$owner = $class === null
			? basename( $scope->getFile() )
			: ( $class->isAnonymous() ? 'class@anonymous' : $class->getName() );

		return RuleErrorBuilder::message(
			'Output in ' . $owner . ' does not come directly from a Renderable or View method call.'
			. ' Render a view from an injected ViewFactory and echo its result.'
		)
			->identifier( RuleManifest::OUTPUT_ONLY_THROUGH_VIEWS )
			->tip( RuleManifest::get_explanation( RuleManifest::OUTPUT_ONLY_THROUGH_VIEWS ) )
			->build();
	}
}
