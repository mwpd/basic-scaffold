<?php
declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\Injector;

/**
 * Closes the cycle back to the class that depends on it.
 */
final class CycleEnd {

	public CycleStart $start;

	public function __construct( CycleStart $start ) {
		$this->start = $start;
	}
}
