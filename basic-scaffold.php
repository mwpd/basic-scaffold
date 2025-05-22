<?php
/**
 * MWPD Basic Plugin Scaffold.
 *
 * @package   MWPD\BasicScaffold
 * @author    Alain Schlesser <alain.schlesser@gmail.com>
 * @license   MIT
 * @link      https://www.mwpd.io/
 * @copyright 2019 Alain Schlesser
 *
 *------------------------------------------------------------------------------
 *-- 1. Provide the plugin meta information that WordPress needs.             --
 *------------------------------------------------------------------------------
 *
 * @wordpress-plugin
 * Plugin Name:  MWPD Basic Plugin Scaffold
 * Plugin URI:   https://www.mwpd.io/
 * Description:  Basic plugin scaffold for the "Modern WordPress Plugin Development" book,
 * Version:      0.1.0
 * Requires PHP: 7.2
 * Author:       Alain Schlesser <alain.schlesser@gmail.com>
 * Author URI:   https://www.alainschlesser.com/
 * Text Domain:  mwpd-basic
 * Domain Path:  /languages
 * License:      MIT
 * License URI:  https://opensource.org/licenses/MIT
 */
/*
 * As this is the only PHP file having side effects, we need to provide a
 * safeguard, so we want to make sure this file is only run from within
 * WordPress and cannot be directly called through a web request.
 */
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

require_once __DIR__ . '/src/boot.php';

\MWPD\BasicScaffold\boot(__DIR__);