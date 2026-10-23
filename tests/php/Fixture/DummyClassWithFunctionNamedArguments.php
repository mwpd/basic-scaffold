<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture;

/**
 * A class whose argument names are also the names of PHP functions, so a
 * test can check that they are bound as names and never called.
 */
final class DummyClassWithFunctionNamedArguments {

	private string $key;

	private string $date;

	private string $trim;

	public function __construct( string $key, string $date, string $trim ) {
		$this->key  = $key;
		$this->date = $date;
		$this->trim = $trim;
	}

	/**
	 * @return array<string,string>
	 */
	public function get_arguments(): array {
		return [
			'key'  => $this->key,
			'date' => $this->date,
			'trim' => $this->trim,
		];
	}
}
