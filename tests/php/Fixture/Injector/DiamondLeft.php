<?php
declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\Injector;

/**
 * Left branch of a diamond: depends on the shared leaf.
 */
final class DiamondLeft {

	public SharedLeaf $leaf;

	public function __construct( SharedLeaf $leaf ) {
		$this->leaf = $leaf;
	}
}
