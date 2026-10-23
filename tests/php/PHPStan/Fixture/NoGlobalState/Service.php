<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\NoGlobalState;

final class Service {
	public function run(): array {
		global $wpdb;
		return [
			$GLOBALS['wp_query'],
			$_GET,
			$_POST,
			$_REQUEST,
			$_COOKIE,
			$_SERVER,
			$_FILES,
			$_ENV,
		];
	}
}
