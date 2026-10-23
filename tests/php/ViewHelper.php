<?php
/**
 * MWPD Basic Plugin Scaffold.
 *
 * @package   MWPD\BasicScaffold
 * @author    Alain Schlesser <alain.schlesser@gmail.com>
 * @license   MIT
 * @link      https://www.mwpd.io/
 * @copyright 2019 Alain Schlesser
 */

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests;

interface ViewHelper {

	public const VIEWS_FOLDER = 'tests/php/Fixture/views/';

	public const CHILD_THEME_FOLDER = self::VIEWS_FOLDER . 'child_theme';

	public const PARENT_THEME_FOLDER = self::VIEWS_FOLDER . 'parent_theme';

	public const PLUGIN_FOLDER = self::VIEWS_FOLDER . 'plugin';

	public const LOCATIONS = [
		self::CHILD_THEME_FOLDER,
		self::PARENT_THEME_FOLDER,
		self::PLUGIN_FOLDER,
	];

	/*
	 * The repository root, which is the plugin root of the scaffold itself.
	 * Views created through a SimpleView resolve relative to it.
	 */
	public const PLUGIN_ROOT = __DIR__ . '/../..';

	/*
	 * Override directory the composition root configures for theme overrides.
	 */
	public const OVERRIDE_DIRECTORY = 'basic-scaffold';

	/*
	 * A plugin with a child and a parent theme that hold scoped overrides for
	 * it, overrides for another plugin, and bare `views/` folders of their own.
	 */
	public const SCOPED_FOLDER = self::VIEWS_FOLDER . 'scoped/';

	public const SCOPED_CHILD_THEME_FOLDER = self::SCOPED_FOLDER . 'child_theme';

	public const SCOPED_PARENT_THEME_FOLDER = self::SCOPED_FOLDER . 'parent_theme';

	public const SCOPED_PLUGIN_FOLDER = self::SCOPED_FOLDER . 'plugin';
}
