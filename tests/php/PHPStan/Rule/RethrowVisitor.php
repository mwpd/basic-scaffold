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
use PhpParser\Node\Expr\Throw_ as ThrowExpression;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Throw_ as ThrowStatement;
use PhpParser\NodeVisitorAbstract;

/**
 * Marks a throw that rethrows the exception of the catch block around it.
 *
 * Rules only see one node at a time, so they cannot tell on their own whether
 * `throw $exception;` passes on what a catch block caught. This visitor runs
 * while PHPStan parses a file and leaves that answer on the throw node.
 */
final class RethrowVisitor extends NodeVisitorAbstract {

	public const ATTRIBUTE = 'architectureRethrow';

	/**
	 * Names of the variables the enclosing catch blocks caught.
	 *
	 * @var array<int, string|null>
	 */
	private array $caught = [];

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

		return null;
	}
}
