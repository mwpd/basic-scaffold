<?php

global $wpdb;
$value = $GLOBALS['wp_query'];
$value = $_GET;
function read_view_input(): array {
	return $_POST;
}
$read = static function (): array {
	return $_COOKIE;
};
$read_env = static fn(): array => $_ENV;
$_SERVER['key'] = 'value';
unset( $GLOBALS['key'] );
$exists = isset( $_FILES['key'] );
