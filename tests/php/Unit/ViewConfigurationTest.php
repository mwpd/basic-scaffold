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

namespace MWPD\BasicScaffold\Tests\Unit;

use MWPD\BasicScaffold\Exception\InvalidViewConfiguration;
use MWPD\BasicScaffold\Infrastructure\View\ViewConfiguration;
use MWPD\BasicScaffold\Tests\ViewHelper;

final class ViewConfigurationTest extends TestCase {

	public function test_it_keeps_the_plugin_root_and_override_directory(): void {
		$configuration = new ViewConfiguration( '/path/to/plugin', 'my-plugin' );

		$this->assertSame( '/path/to/plugin', $configuration->get_plugin_root() );
		$this->assertSame( 'my-plugin', $configuration->get_override_directory() );
	}

	public function test_it_places_the_theme_override_location_inside_the_theme(): void {
		$configuration = new ViewConfiguration( '/path/to/plugin', 'my-plugin' );

		$this->assertSame(
			'/path/to/theme/my-plugin',
			$configuration->get_theme_override_location( '/path/to/theme/' )
		);
	}

	public function test_it_rejects_an_empty_plugin_root(): void {
		$this->expectException( InvalidViewConfiguration::class );
		$this->expectExceptionMessage( 'plugin root of a view configuration cannot be empty' );

		new ViewConfiguration( '', 'my-plugin' );
	}

	/**
	 * @dataProvider invalid_override_directories
	 */
	public function test_it_rejects_an_override_directory_that_is_not_a_single_name( string $directory ): void {
		$this->expectException( InvalidViewConfiguration::class );
		$this->expectExceptionMessage( 'must be a single directory name' );

		new ViewConfiguration( '/path/to/plugin', $directory );
	}

	/**
	 * Names that would put overrides straight into the theme or outside the
	 * plugin's own override directory.
	 *
	 * @return array<string, array{string}>
	 */
	public static function invalid_override_directories(): array {
		return [
			'empty'            => [ '' ],
			'current'          => [ '.' ],
			'parent'           => [ '..' ],
			'nested'           => [ 'my-plugin/views' ],
			'escaping'         => [ '../views' ],
			'backslash nested' => [ 'my-plugin\\views' ],
		];
	}

	public function test_the_view_infrastructure_does_not_derive_paths_from_its_own_location(): void {
		/*
		 * Moving the infrastructure below another directory level must not
		 * change the plugin root. That holds as long as no view class counts
		 * directory levels from its own file, and the root only ever comes
		 * from the ViewConfiguration the composition root passes in.
		 */
		$view_directory = ViewHelper::PLUGIN_ROOT . '/src/Infrastructure/View';
		$files          = \glob( $view_directory . '/*.php' );
		$this->assertNotEmpty( $files );

		foreach ( (array) $files as $file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a local source file.
			$source = (string) \file_get_contents( (string) $file );

			$this->assertDoesNotMatchRegularExpression(
				'/__DIR__|__FILE__|\bdirname\s*\(/',
				$source,
				\basename( (string) $file ) . ' derives a path from its own location.'
			);
		}
	}
}
