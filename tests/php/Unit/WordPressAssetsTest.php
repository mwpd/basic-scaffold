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

use Brain\Monkey\Functions;
use MWPD\BasicScaffold\Exception\InvalidAsset;
use MWPD\BasicScaffold\Infrastructure\Asset\Asset;
use MWPD\BasicScaffold\Infrastructure\Asset\AssetLocation;
use MWPD\BasicScaffold\Infrastructure\Asset\Script;
use MWPD\BasicScaffold\Infrastructure\Asset\Style;
use MWPD\BasicScaffold\Infrastructure\Asset\WordPressAssets;
use MWPD\BasicScaffold\Tests\Fixture\Asset\DependencyRegistry;

/**
 * WordPressAssets registers and enqueues declared assets through the
 * WordPress script and style APIs, and catches conflicting registrations.
 */
final class WordPressAssetsTest extends TestCase {

	private const COPY_URL = 'https://example.com/wp-content/plugins/acme/assets/js/copy.js';

	private DependencyRegistry $styles;

	private DependencyRegistry $scripts;

	private AssetLocation $location;

	/**
	 * Arguments wp_register_script() received last.
	 *
	 * @var mixed
	 */
	private $script_arguments;

	protected function set_up() {
		parent::set_up();

		$this->styles   = new DependencyRegistry();
		$this->scripts  = new DependencyRegistry();
		$this->location = new AssetLocation( 'https://example.com/wp-content/plugins/acme/', '1.2.3' );

		$this->wordpress_version( '6.8' );

		Functions\when( 'wp_styles' )->justReturn( $this->styles );
		Functions\when( 'wp_scripts' )->justReturn( $this->scripts );

		Functions\when( 'wp_register_style' )->alias(
			fn( string $handle, $src, array $deps, $ver, string $media ): bool
				=> $this->styles->add( $handle, $src, $deps, $ver, $media )
		);
		Functions\when( 'wp_add_inline_style' )->alias(
			function ( string $handle, string $css ): bool {
				$after   = (array) ( $this->styles->registered[ $handle ]->extra['after'] ?? [] );
				$after[] = $css;
				$this->styles->add_data( $handle, 'after', $after );

				return true;
			}
		);
		Functions\when( 'wp_enqueue_style' )->alias( fn( string $handle ) => $this->styles->enqueue( $handle ) );

		Functions\when( 'wp_register_script' )->alias(
			function ( string $handle, string $src, array $deps, $ver, $args ): bool {
				$this->script_arguments = $args;

				if ( ! $this->scripts->add( $handle, $src, $deps, $ver ) ) {
					return false;
				}

				// WordPress 6.3 and later read an array, older versions a flag.
				$in_footer = is_array( $args ) ? (bool) ( $args['in_footer'] ?? false ) : (bool) $args;
				if ( $in_footer ) {
					$this->scripts->add_data( $handle, 'group', 1 );
				}

				if ( is_array( $args ) && isset( $args['strategy'] ) ) {
					$this->scripts->add_data( $handle, 'strategy', $args['strategy'] );
				}

				return true;
			}
		);
		Functions\when( 'wp_enqueue_script' )->alias( fn( string $handle ) => $this->scripts->enqueue( $handle ) );
	}

	public function test_a_stylesheet_is_registered_from_the_plugin_url_and_enqueued(): void {
		$this->enqueue(
			Style::file( 'acme-notice', 'assets/css/notice.css' )
				->depends_on( 'wp-components' )
				->for_media( 'screen' )
		);

		$style = $this->styles->registered['acme-notice'];
		$this->assertSame( 'https://example.com/wp-content/plugins/acme/assets/css/notice.css', $style->src );
		$this->assertSame( [ 'wp-components' ], $style->deps );
		$this->assertSame( '1.2.3', $style->ver );
		$this->assertSame( 'screen', $style->args );
		$this->assertSame( [ 'acme-notice' ], $this->styles->queue );
	}

	public function test_an_asset_can_carry_its_own_version(): void {
		$this->enqueue( Style::file( 'acme-notice', 'assets/css/notice.css' )->with_version( '7' ) );

		$this->assertSame( '7', $this->styles->registered['acme-notice']->ver );
	}

	public function test_an_inline_style_is_registered_without_a_source_and_its_css_attached(): void {
		$this->enqueue( Style::inline( 'acme-notice', '.notice { color: red; }' ) );

		$style = $this->styles->registered['acme-notice'];
		$this->assertFalse( $style->src );
		$this->assertSame( [ '.notice { color: red; }' ], $style->extra['after'] );
		$this->assertSame( [ 'acme-notice' ], $this->styles->queue );
	}

	public function test_the_same_declaration_again_is_only_enqueued_and_its_css_attached_once(): void {
		$this->enqueue( Style::inline( 'acme-notice', '.notice { color: red; }' ) );
		$this->enqueue( Style::inline( 'acme-notice', '.notice { color: red; }' ) );
		$this->enqueue( Script::file( 'acme-copy', 'assets/js/copy.js' )->deferred() );
		$this->enqueue( Script::file( 'acme-copy', 'assets/js/copy.js' )->deferred() );

		$this->assertSame( [ '.notice { color: red; }' ], $this->styles->registered['acme-notice']->extra['after'] );
		$this->assertSame( [ 'acme-notice', 'acme-notice' ], $this->styles->queue );
		$this->assertSame( [ 'acme-copy', 'acme-copy' ], $this->scripts->queue );
	}

	/**
	 * @dataProvider conflicting_styles
	 */
	public function test_a_style_declared_differently_under_a_registered_handle_conflicts( Style $other ): void {
		$this->enqueue( Style::file( 'acme-notice', 'assets/css/notice.css' ) );

		$this->expectException( InvalidAsset::class );
		$this->expectExceptionMessage(
			'The asset "acme-notice" is already registered with a different source or options.'
		);

		$this->enqueue( $other );
	}

	/**
	 * @return array<string,array{Style}>
	 */
	public static function conflicting_styles(): array {
		return [
			'other file'         => [ Style::file( 'acme-notice', 'assets/css/other.css' ) ],
			'other dependencies' => [
				Style::file( 'acme-notice', 'assets/css/notice.css' )->depends_on( 'wp-components' ),
			],
			'other version'      => [ Style::file( 'acme-notice', 'assets/css/notice.css' )->with_version( '2' ) ],
			'other media'        => [ Style::file( 'acme-notice', 'assets/css/notice.css' )->for_media( 'print' ) ],
			'inline instead'     => [ Style::inline( 'acme-notice', 'p { margin: 0; }' ) ],
		];
	}

	public function test_inline_css_that_differs_under_a_registered_handle_conflicts(): void {
		$this->enqueue( Style::inline( 'acme-notice', 'p { margin: 0; }' ) );

		$this->expectException( InvalidAsset::class );

		$this->enqueue( Style::inline( 'acme-notice', 'p { margin: 1em; }' ) );
	}

	public function test_a_handle_registered_by_someone_else_with_another_source_conflicts(): void {
		$this->styles->add( 'acme-notice', 'https://example.com/other.css', [], '1.2.3', 'all' );

		$this->expectException( InvalidAsset::class );

		$this->enqueue( Style::file( 'acme-notice', 'assets/css/notice.css' ) );
	}

	public function test_a_script_is_registered_with_its_footer_flag_and_strategy(): void {
		$this->enqueue(
			Script::file( 'acme-copy', 'assets/js/copy.js' )->depends_on( 'wp-i18n' )->in_footer()->async()
		);

		$script = $this->scripts->registered['acme-copy'];
		$this->assertSame( self::COPY_URL, $script->src );
		$this->assertSame( [ 'wp-i18n' ], $script->deps );
		$this->assertSame( '1.2.3', $script->ver );
		$this->assertSame(
			[
				'in_footer' => true,
				'strategy'  => 'async',
			],
			$this->script_arguments
		);
		$this->assertSame( [ 'acme-copy' ], $this->scripts->queue );
	}

	public function test_a_script_without_a_strategy_passes_none(): void {
		$this->enqueue( Script::file( 'acme-copy', 'assets/js/copy.js' ) );

		$this->assertSame( [ 'in_footer' => false ], $this->script_arguments );
	}

	public function test_before_wordpress_6_3_a_deferred_head_script_stays_in_the_head_without_a_strategy(): void {
		$this->wordpress_version( '6.2.6' );

		$this->enqueue( Script::file( 'acme-copy', 'assets/js/copy.js' )->deferred() );

		$this->assertFalse( $this->script_arguments, 'An array would read as true and move the script to the footer.' );
		$this->assertArrayNotHasKey( 'group', $this->scripts->registered['acme-copy']->extra );
	}

	public function test_before_wordpress_6_3_the_footer_flag_is_passed_on_its_own(): void {
		$this->wordpress_version( '6.0' );

		$this->enqueue( Script::file( 'acme-copy', 'assets/js/copy.js' )->in_footer() );
		$this->enqueue( Script::file( 'acme-copy', 'assets/js/copy.js' )->in_footer()->deferred() );

		$this->assertTrue( $this->script_arguments );
		$this->assertSame( [ 'acme-copy', 'acme-copy' ], $this->scripts->queue, 'No strategy is recorded to compare.' );
	}

	/**
	 * @dataProvider conflicting_scripts
	 */
	public function test_a_script_declared_differently_under_a_registered_handle_conflicts( Script $other ): void {
		$this->enqueue( Script::file( 'acme-copy', 'assets/js/copy.js' )->deferred() );

		$this->expectException( InvalidAsset::class );

		$this->enqueue( $other );
	}

	/**
	 * @return array<string,array{Script}>
	 */
	public static function conflicting_scripts(): array {
		return [
			'other file'     => [ Script::file( 'acme-copy', 'assets/js/other.js' )->deferred() ],
			'in the footer'  => [ Script::file( 'acme-copy', 'assets/js/copy.js' )->deferred()->in_footer() ],
			'other strategy' => [ Script::file( 'acme-copy', 'assets/js/copy.js' )->async() ],
			'no strategy'    => [ Script::file( 'acme-copy', 'assets/js/copy.js' ) ],
		];
	}

	public function test_a_script_registered_with_the_legacy_footer_flag_matches(): void {
		$this->scripts->add( 'acme-copy', self::COPY_URL, [], '1.2.3', 1 );

		$this->enqueue( Script::file( 'acme-copy', 'assets/js/copy.js' )->in_footer() );

		$this->assertSame( [ 'acme-copy' ], $this->scripts->queue );
	}

	public function test_an_empty_strategy_matches_a_script_without_one(): void {
		$this->scripts->add( 'acme-copy', self::COPY_URL, [], '1.2.3' );
		$this->scripts->add_data( 'acme-copy', 'strategy', '' );

		$this->enqueue( Script::file( 'acme-copy', 'assets/js/copy.js' ) );

		$this->assertSame( [ 'acme-copy' ], $this->scripts->queue );
	}

	public function test_the_dependency_translations_add_still_matches(): void {
		$this->enqueue( Script::file( 'acme-copy', 'assets/js/copy.js' ) );

		// What wp_set_script_translations() does to the registration.
		$registered             = $this->scripts->registered['acme-copy'];
		$registered->textdomain = 'acme';
		$registered->deps[]     = 'wp-i18n';

		$this->enqueue( Script::file( 'acme-copy', 'assets/js/copy.js' ) );

		$this->assertSame( [ 'acme-copy', 'acme-copy' ], $this->scripts->queue );
	}

	public function test_without_translations_an_extra_dependency_still_conflicts(): void {
		$this->scripts->add( 'acme-copy', self::COPY_URL, [ 'wp-i18n' ], '1.2.3' );

		$this->expectException( InvalidAsset::class );

		$this->enqueue( Script::file( 'acme-copy', 'assets/js/copy.js' ) );
	}

	public function test_styles_and_scripts_have_separate_handles(): void {
		$this->enqueue( Style::file( 'acme-copy', 'assets/css/copy.css' ) );
		$this->enqueue( Script::file( 'acme-copy', 'assets/js/copy.js' ) );

		$this->assertSame( [ 'acme-copy' ], $this->styles->queue );
		$this->assertSame( [ 'acme-copy' ], $this->scripts->queue );
	}

	public function test_an_unknown_kind_of_asset_is_rejected(): void {
		$asset = new class() implements Asset {
			public function get_handle(): string {
				return 'acme-other';
			}
		};

		$this->expectException( InvalidAsset::class );
		$this->expectExceptionMessage( 'Declare a Style or a Script.' );

		$this->enqueue( $asset );
	}

	/**
	 * Enqueue an asset through the adapter.
	 *
	 * @param Asset $asset Asset to enqueue.
	 */
	private function enqueue( Asset $asset ): void {
		( new WordPressAssets() )->enqueue( $asset, $this->location );
	}

	/**
	 * Make get_bloginfo( 'version' ) report a WordPress version.
	 *
	 * @param string $version WordPress version.
	 */
	private function wordpress_version( string $version ): void {
		Functions\when( 'get_bloginfo' )->alias(
			static fn( string $show ): string => 'version' === $show ? $version : ''
		);
	}
}
