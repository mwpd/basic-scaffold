<?php
declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\Injector;

/**
 * Top of a diamond: both of its dependencies need the shared leaf.
 */
final class DiamondTop {

	public DiamondLeft $left;

	public DiamondRight $right;

	public function __construct( DiamondLeft $left, DiamondRight $right ) {
		$this->left  = $left;
		$this->right = $right;
	}
}
