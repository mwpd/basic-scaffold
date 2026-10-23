<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\NoGlobalState;

class InputAdapter {
	public function read(): array {
		$read = static fn(): array => $_GET;
		return [ $_GET, $_POST, $_REQUEST, $_COOKIE, $_SERVER, $_FILES, $_ENV, $read() ];
	}

	public function globals(): array {
		global $wpdb;
		return $GLOBALS;
	}
}
