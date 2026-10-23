<?php

declare( strict_types=1 );

/**
 * Stand-in for WordPress's site class, for tests that run without WordPress.
 *
 * Like core's class, it copies the fields of the object it is given. Core
 * keeps both IDs as numeric strings, and calls the network ID site_id.
 */
final class WP_Site {

	/**
	 * ID of the site.
	 *
	 * @var string
	 */
	public $blog_id = '0';

	/**
	 * ID of the network the site belongs to.
	 *
	 * @var string
	 */
	public $site_id = '0';

	/**
	 * Instantiate a WP_Site object.
	 *
	 * @param object $site Fields of the site.
	 */
	public function __construct( object $site ) {
		foreach ( get_object_vars( $site ) as $key => $value ) {
			$this->$key = $value;
		}
	}
}
