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
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Print_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Echo_;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Templates explicitly escape values that bypass the view's automatic escaping.
 *
 * Only direct assignments from raw() are tracked, conservatively for the rest
 * of the template. An escaping call protects its argument subtree, so escaping
 * a concatenation is sufficient, but escaping one sibling never protects another.
 *
 * @implements Rule<FileNode>
 */
final class RawIsEscapedRule implements Rule {

	/**
	 * Production view directories, without trailing slashes.
	 *
	 * @var array<string>
	 */
	private array $views;

	/**
	 * Accepted escaping functions, normalized as PHP function names.
	 *
	 * @var array<string>
	 */
	private array $escaping_functions;

	/**
	 * @param array<string> $views              Production view directories.
	 * @param array<string> $escaping_functions Functions accepted for escaping.
	 */
	public function __construct( array $views, array $escaping_functions ) {
		$this->views              = array_map( static fn( string $view ): string => rtrim( $view, '/' ), $views );
		$this->escaping_functions = array_map(
			static fn( string $name ): string => strtolower( ltrim( $name, '\\' ) ),
			$escaping_functions
		);
	}

	public function getNodeType(): string {
		return FileNode::class;
	}

	/**
	 * @param FileNode $node  File to inspect.
	 * @param Scope    $scope Scope of the file.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	public function processNode( Node $node, Scope $scope ): array {
		foreach ( $this->views as $views ) {
			if ( strpos( $scope->getFile(), $views . '/' ) === 0 ) {
				$variables = [];
				$errors    = [];
				foreach ( $node->getNodes() as $statement ) {
					$this->inspect( $statement, basename( $scope->getFile() ), $variables, $errors );
				}
				return $errors;
			}
		}

		return [];
	}

	/**
	 * Inspect template code, checking nested function bodies in their own scope.
	 *
	 * @param Node                      $node      Node to inspect.
	 * @param string                    $template  Template file name.
	 * @param array<string, true>       $variables Variables directly assigned from raw().
	 * @param list<IdentifierRuleError> $errors    Errors found.
	 */
	private function inspect( Node $node, string $template, array &$variables, array &$errors ): void {
		if ( $node instanceof FunctionLike || $node instanceof ClassLike ) {
			$local_variables = [];
			foreach ( $this->children( $node ) as $child ) {
				$this->inspect( $child, $template, $local_variables, $errors );
			}
			return;
		}

		if ( $node instanceof Assign && $node->var instanceof Variable && is_string( $node->var->name )
			&& $this->is_raw_call( $node->expr ) ) {
			$variables[ $node->var->name ] = true;
		}

		// An output expression can itself assign a raw value. Record that
		// before checking its siblings, even if the assignment is escaped.
		foreach ( $this->children( $node ) as $child ) {
			$this->inspect( $child, $template, $variables, $errors );
		}

		$outputs = $node instanceof Echo_ ? $node->exprs : [];
		if ( $node instanceof Print_ ) {
			$outputs = [ $node->expr ];
		}

		foreach ( $outputs as $output ) {
			if ( $this->has_unescaped_raw( $output, $variables ) ) {
				$errors[] = $this->build_error( $template, $node->getStartLine() );
				break;
			}
		}
	}

	/**
	 * Look for raw reads outside the argument subtree of an escaping function.
	 *
	 * @param Node                $node      Printed expression or descendant.
	 * @param array<string, true> $variables Variables directly assigned from raw().
	 * @return bool Whether an unescaped raw value occurs here.
	 */
	private function has_unescaped_raw( Node $node, array $variables ): bool {
		if ( $node instanceof FunctionLike || $node instanceof ClassLike ) {
			return false;
		}

		if ( $node instanceof FuncCall && $node->name instanceof Name
			&& in_array( strtolower( ltrim( $node->name->toString(), '\\' ) ), $this->escaping_functions, true ) ) {
			return false;
		}

		if ( $this->is_raw_call( $node )
			|| ( $node instanceof Variable && is_string( $node->name ) && isset( $variables[ $node->name ] ) ) ) {
			return true;
		}

		foreach ( $this->children( $node ) as $child ) {
			if ( $this->has_unescaped_raw( $child, $variables ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param Node $node Possible raw() call.
	 * @return bool Whether this is a direct call to $this->raw().
	 */
	private function is_raw_call( Node $node ): bool {
		return $node instanceof MethodCall && $node->var instanceof Variable && $node->var->name === 'this'
			&& $node->name instanceof Identifier && strtolower( $node->name->toString() ) === 'raw';
	}

	/**
	 * @param Node $node Parent node.
	 * @return list<Node> Child nodes in source order.
	 */
	private function children( Node $node ): array {
		$children = [];
		foreach ( $node->getSubNodeNames() as $name ) {
			$value = $node->$name;
			foreach ( is_array( $value ) ? $value : [ $value ] as $child ) {
				if ( $child instanceof Node ) {
					$children[] = $child;
				}
			}
		}

		return $children;
	}

	/**
	 * @param string $template Template file name.
	 * @param int    $line     Line of the output expression.
	 * @return IdentifierRuleError Error to report.
	 */
	private function build_error( string $template, int $line ): IdentifierRuleError {
		return RuleErrorBuilder::message(
			sprintf(
				'Template %1$s, line %2$d, prints an unescaped raw() value. Wrap it in a context-appropriate'
				. ' esc_*(), wp_kses*(), absint() or intval() function listed in'
				. ' parameters.architecture.rawIsEscaped.escapingFunctions.',
				$template,
				$line
			)
		)
			->line( $line )
			->identifier( RuleManifest::RAW_IS_ESCAPED )
			->tip( RuleManifest::get_explanation( RuleManifest::RAW_IS_ESCAPED ) )
			->build();
	}
}
