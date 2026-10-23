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

use MWPD\BasicScaffold\Exception\InvalidAsset;
use MWPD\BasicScaffold\Infrastructure\Asset\AssetContext;
use MWPD\BasicScaffold\Infrastructure\Asset\AssetLocation;
use MWPD\BasicScaffold\Infrastructure\Asset\AssetPath;
use MWPD\BasicScaffold\Infrastructure\Asset\Script;
use MWPD\BasicScaffold\Infrastructure\Asset\Style;

/**
 * Styles, scripts, their paths, enqueue points and the asset location are
 * values that check what they are given.
 */
final class AssetDeclarationsTest extends TestCase {

	public function test_a_style_file_has_defaults(): void {
		$style = Style::file( 'acme-notice', 'assets/css/notice.css' );
		$path  = $style->get_path();

		$this->assertSame( 'acme-notice', $style->get_handle() );
		$this->assertNotNull( $path );
		$this->assertSame( 'assets/css/notice.css', $path->to_string() );
		$this->assertNull( $style->get_css() );
		$this->assertSame( [], $style->get_dependencies() );
		$this->assertNull( $style->get_version() );
		$this->assertSame( 'all', $style->get_media() );
	}

	public function test_style_options_return_changed_copies(): void {
		$style   = Style::file( 'acme-notice', 'assets/css/notice.css' );
		$changed = $style->depends_on( 'wp-components', 'acme-base' )->with_version( '2' )->for_media( 'print' );

		$this->assertSame( [], $style->get_dependencies(), 'The original is unchanged.' );
		$this->assertSame( [ 'wp-components', 'acme-base' ], $changed->get_dependencies() );
		$this->assertSame( '2', $changed->get_version() );
		$this->assertSame( 'print', $changed->get_media() );
		$this->assertSame( [ 'acme-base' ], $changed->depends_on( 'acme-base' )->get_dependencies(), 'Dependencies are replaced.' );
	}

	public function test_an_inline_style_has_css_and_no_path(): void {
		$style = Style::inline( 'acme-notice', '.notice { color: red; }' );

		$this->assertNull( $style->get_path() );
		$this->assertSame( '.notice { color: red; }', $style->get_css() );
	}

	public function test_script_options_return_changed_copies(): void {
		$script  = Script::file( 'acme-copy', 'assets/js/copy.js' );
		$changed = $script->depends_on( 'wp-i18n' )->with_version( '3' )->in_footer()->deferred();

		$this->assertSame( 'assets/js/copy.js', $script->get_path()->to_string() );
		$this->assertFalse( $script->is_in_footer(), 'The original is unchanged.' );
		$this->assertNull( $script->get_strategy() );
		$this->assertSame( [ 'wp-i18n' ], $changed->get_dependencies() );
		$this->assertSame( '3', $changed->get_version() );
		$this->assertTrue( $changed->is_in_footer() );
		$this->assertSame( Script::STRATEGY_DEFER, $changed->get_strategy() );
		$this->assertSame( Script::STRATEGY_ASYNC, $changed->async()->get_strategy(), 'The last strategy wins.' );
	}

	/**
	 * @dataProvider invalid_declarations
	 *
	 * @param callable(): mixed $declare Declaration that must fail.
	 */
	public function test_an_invalid_declaration_is_rejected( callable $declare, string $message ): void {
		$this->expectException( InvalidAsset::class );
		$this->expectExceptionMessage( $message );

		$declare();
	}

	/**
	 * @return array<string,array{callable(): mixed, string}>
	 */
	public static function invalid_declarations(): array {
		$path = 'must be relative to the plugin root';

		return [
			'empty style handle'     => [ static fn() => Style::file( '', 'a.css' ), 'An asset handle cannot be empty.' ],
			'empty script handle'    => [ static fn() => Script::file( '', 'a.js' ), 'An asset handle cannot be empty.' ],
			'empty path'             => [ static fn() => Style::file( 'acme-a', '' ), $path ],
			'absolute path'          => [ static fn() => Style::file( 'acme-a', '/etc/a.css' ), $path ],
			'URL'                    => [ static fn() => Script::file( 'acme-a', 'https://cdn.example.com/a.js' ), $path ],
			'parent directory'       => [ static fn() => Style::file( 'acme-a', 'assets/../../a.css' ), $path ],
			'backslash'              => [ static fn() => Style::file( 'acme-a', 'assets\\a.css' ), $path ],
			'query string'           => [ static fn() => Style::file( 'acme-a', 'a.css?ver=1' ), $path ],
			'null byte'              => [ static fn() => Style::file( 'acme-a', "a.css\0" ), $path ],
			'empty CSS'              => [ static fn() => Style::inline( 'acme-a', " \n" ), 'The inline style "acme-a" has no CSS.' ],
			'style tag'              => [ static fn() => Style::inline( 'acme-a', '<style>p{}</style>' ), 'contains a <style> tag' ],
			'closing style tag'      => [ static fn() => Style::inline( 'acme-a', 'p{}</STYLE><script>' ), 'contains a <style> tag' ],
			'media on inline style'  => [ static fn() => Style::inline( 'acme-a', 'p{}' )->for_media( 'print' ), 'Wrap the CSS in an @media rule' ],
			'empty media'            => [ static fn() => Style::file( 'acme-a', 'a.css' )->for_media( '' ), 'The media of the asset "acme-a"' ],
			'empty style version'    => [ static fn() => Style::file( 'acme-a', 'a.css' )->with_version( '' ), 'The version of the asset' ],
			'empty script version'   => [ static fn() => Script::file( 'acme-a', 'a.js' )->with_version( '' ), 'The version of the asset' ],
			'empty style dependency' => [ static fn() => Style::file( 'acme-a', 'a.css' )->depends_on( 'x', '' ), 'The dependency of the asset' ],
			'empty script dependency' => [ static fn() => Script::file( 'acme-a', 'a.js' )->depends_on( '' ), 'The dependency of the asset' ],
			'empty default version'  => [ static fn() => new AssetLocation( 'https://example.com', '' ), 'need a default version' ],
		];
	}

	public function test_a_nested_path_inside_the_plugin_is_accepted(): void {
		$this->assertSame( 'assets/css/a.b.css', AssetPath::for_asset( 'acme-a', 'assets/css/a.b.css' )->to_string() );
		$this->assertSame( './a.css', AssetPath::for_asset( 'acme-a', './a.css' )->to_string() );
	}

	public function test_the_location_joins_the_plugin_url_and_path_with_one_slash(): void {
		$path = AssetPath::for_asset( 'acme-a', 'assets/a.css' );

		$this->assertSame( 'https://example.com/acme/assets/a.css', ( new AssetLocation( 'https://example.com/acme/', '1' ) )->get_url( $path ) );
		$this->assertSame( 'https://example.com/acme/assets/a.css', ( new AssetLocation( 'https://example.com/acme', '1' ) )->get_url( $path ) );
	}

	public function test_the_location_supplies_the_default_version(): void {
		$location = new AssetLocation( 'https://example.com/acme', '1.2.3' );

		$this->assertSame( '1.2.3', $location->get_version( null ) );
		$this->assertSame( '4', $location->get_version( '4' ) );
	}

	public function test_each_context_names_one_enqueue_point(): void {
		$contexts = [
			'front end'    => AssetContext::front_end(),
			'admin'        => AssetContext::admin( 'index.php' ),
			'block editor' => AssetContext::block_editor(),
			'login'        => AssetContext::login(),
		];

		$answers = array_map(
			static fn( AssetContext $context ): array => [
				$context->is_front_end(),
				$context->is_admin(),
				$context->is_block_editor(),
				$context->is_login(),
			],
			$contexts
		);

		$this->assertSame(
			[
				'front end'    => [ true, false, false, false ],
				'admin'        => [ false, true, false, false ],
				'block editor' => [ false, false, true, false ],
				'login'        => [ false, false, false, true ],
			],
			$answers
		);
	}

	public function test_the_admin_context_tells_its_screen(): void {
		$context = AssetContext::admin( 'settings_page_acme' );

		$this->assertSame( 'settings_page_acme', $context->get_hook_suffix() );
		$this->assertTrue( $context->is_admin( 'settings_page_acme' ) );
		$this->assertFalse( $context->is_admin( 'index.php' ) );
		$this->assertFalse( AssetContext::front_end()->is_admin( '' ) );
		$this->assertSame( '', AssetContext::front_end()->get_hook_suffix() );
	}
}
