<?php

declare( strict_types=1 );

/**
 * Stand-in for a WordPress function that returns a WP_Error on failure.
 *
 * @return array<string>|WP_Error
 */
function wp_fixture_query() {
	return [];
}
