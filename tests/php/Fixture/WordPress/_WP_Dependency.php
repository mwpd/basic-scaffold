<?php

declare( strict_types=1 );

/**
 * Stand-in for WordPress's registered script or style, for tests that run
 * without WordPress. It has the fields core's class has.
 *
 * phpcs:disable PEAR.NamingConventions.ValidClassName.StartWithCapital,Squiz.Classes.ValidClassName.NotCamelCaps
 */
final class _WP_Dependency {

	/**
	 * @var string
	 */
	public $handle;

	/**
	 * @var string|false
	 */
	public $src;

	/**
	 * @var array<string>
	 */
	public $deps;

	/**
	 * @var string|bool|null
	 */
	public $ver;

	/**
	 * @var mixed
	 */
	public $args;

	/**
	 * @var array<string,mixed>
	 */
	public $extra = [];

	/**
	 * @param string           $handle Handle.
	 * @param string|false     $src    Source URL.
	 * @param array<string>    $deps   Dependencies.
	 * @param string|bool|null $ver    Version.
	 * @param mixed            $args   Media of a style.
	 */
	public function __construct( string $handle, $src, array $deps, $ver, $args = null ) {
		$this->handle = $handle;
		$this->src    = $src;
		$this->deps   = $deps;
		$this->ver    = $ver;
		$this->args   = $args;
	}
}
