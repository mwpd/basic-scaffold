<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\NoGlobalState;

final class InjectedService {
	private array $input;

	public function __construct( array $input ) {
		$this->input = $input;
	}

	public function read(): array {
		$post = get_post();
		return [ $this->input, $post, $_SESSION ];
	}
}
