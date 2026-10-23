<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\NoGlobalState;

global $wpdb, $wp_query;
$input = $_GET;
function read_input(): array {
	return $_POST;
}
$read = static function (): array {
	return $_COOKIE;
};
$read_env = static fn(): array => $_ENV;
$object = new class() {
	public function read(): array {
		return $_SERVER;
	}
};
