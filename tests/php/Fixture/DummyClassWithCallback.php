<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture;

/**
 * A class that takes a callable argument, to check that a callable argument
 * value reaches the constructor as-is.
 */
final class DummyClassWithCallback {

	/**
	 * @var callable
	 */
	private $callback;

	public function __construct( callable $callback ) {
		$this->callback = $callback;
	}

	public function get_callback(): callable {
		return $this->callback;
	}
}
