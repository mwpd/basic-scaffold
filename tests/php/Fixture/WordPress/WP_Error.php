<?php

declare( strict_types=1 );

/**
 * Stand-in for WordPress's error class, for tests that run without WordPress.
 *
 * The rule tests need it to see classes that extend it.
 */
class WP_Error {
}
