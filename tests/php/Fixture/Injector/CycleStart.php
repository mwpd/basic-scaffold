<?php
declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\Injector;

/**
 * Depends on a class that in turn depends on this one: a real cycle.
 */
final class CycleStart {

	public CycleEnd $end;

	public function __construct( CycleEnd $end ) {
		$this->end = $end;
	}
}
