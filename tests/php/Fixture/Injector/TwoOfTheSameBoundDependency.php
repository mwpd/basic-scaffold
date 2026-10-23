<?php
declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Fixture\Injector;

use MWPD\BasicScaffold\Tests\Fixture\DummyInterface;

/**
 * Needs two instances of an interface that a binding resolves.
 */
final class TwoOfTheSameBoundDependency {

	public DummyInterface $first;

	public DummyInterface $second;

	public function __construct( DummyInterface $first, DummyInterface $second ) {
		$this->first  = $first;
		$this->second = $second;
	}
}
