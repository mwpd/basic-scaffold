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

use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\Throw_ as ThrowExpression;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Throw_ as ThrowStatement;
use PhpParser\NodeVisitorAbstract;

/**
 * Marks a throw that rethrows the exception of the catch block around it.
 *
 * Rules only see one node at a time, so they cannot tell on their own whether
 * `throw $exception;` passes on what a catch block caught. This visitor runs
 * while PHPStan parses a file and leaves that answer on the throw node.
 *
 * A caught variable is only visible where PHP makes it visible: a nested
 * function or class starts without it, a closure keeps it only if it imports
 * it with `use`, and a parameter of the same name hides it.
 */
final class RethrowVisitor extends NodeVisitorAbstract {

	public const ATTRIBUTE = 'architectureRethrow';

	/**
	 * Names of the caught variables visible at the current node.
	 *
	 * @var array<int, string|null>
	 */
	private array $caught = [];

	/**
	 * Caught variables of the enclosing scopes, restored when a nested
	 * function or class ends.
	 *
	 * @var array<int, array<int, string|null>>
	 */
	private array $outer_scopes = [];

	/**
	 * Track catch blocks, and mark throws of what they caught.
	 *
	 * @param Node $node Node being entered.
	 * @return null Leaves the node in place.
	 */
	public function enterNode( Node $node ) {
		if ( $node instanceof Catch_ ) {
			$this->caught[] = $node->var !== null && is_string( $node->var->name ) ? $node->var->name : null;

			return null;
		}

		if ( $node instanceof FunctionLike || $node instanceof ClassLike ) {
			$this->outer_scopes[] = $this->caught;
			$this->caught         = $this->get_visible_in( $node );

			return null;
		}

		if ( ( $node instanceof ThrowStatement || $node instanceof ThrowExpression )
			&& $node->expr instanceof Variable
			&& is_string( $node->expr->name )
			&& in_array( $node->expr->name, $this->caught, true ) ) {
			$node->setAttribute( self::ATTRIBUTE, true );
		}

		return null;
	}

	/**
	 * Stop tracking a catch block when it ends.
	 *
	 * @param Node $node Node being left.
	 * @return null Leaves the node in place.
	 */
	public function leaveNode( Node $node ) {
		if ( $node instanceof Catch_ ) {
			array_pop( $this->caught );
		}

		if ( $node instanceof FunctionLike || $node instanceof ClassLike ) {
			$this->caught = array_pop( $this->outer_scopes ) ?? [];
		}

		return null;
	}

	/**
	 * Get the caught variables that stay visible inside a function or class.
	 *
	 * @param FunctionLike|ClassLike $node Function or class being entered.
	 * @return array<int, string|null> Caught variables visible inside it.
	 */
	private function get_visible_in( Node $node ): array {
		if ( $node instanceof Closure ) {
			$imported = [];
			foreach ( $node->uses as $use ) {
				// PHP-Parser names its properties in camelCase.
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				if ( ! $use->byRef && is_string( $use->var->name ) ) {
					$imported[] = $use->var->name;
				}
			}
			$visible = array_intersect( $this->caught, $imported );
		} elseif ( $node instanceof ArrowFunction ) {
			$visible = $this->caught;
		} else {
			return [];
		}

		$parameters = [];
		foreach ( $node->getParams() as $parameter ) {
			if ( $parameter->var instanceof Variable && is_string( $parameter->var->name ) ) {
				$parameters[] = $parameter->var->name;
			}
		}

		return array_values( array_diff( $visible, $parameters ) );
	}
}
