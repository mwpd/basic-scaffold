<?php
declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\Injector;

/**
 * Right branch of a diamond: depends on the same shared leaf.
 */
final class DiamondRight {

	public SharedLeaf $leaf;

	public function __construct( SharedLeaf $leaf ) {
		$this->leaf = $leaf;
	}
}
