<?php
declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Unit;

use Brain\Monkey\Functions;
use MWPD\BasicScaffold\Infrastructure\View\TemplatedViewFactory;
use MWPD\BasicScaffold\Infrastructure\ViewFactory;
use MWPD\BasicScaffold\Tests\ViewHelper;

final class TemplatedViewFactoryTest extends TestCase {

	public function test_it_can_be_instantiated(): void {
		$factory = new TemplatedViewFactory();

		$this->assertInstanceOf( TemplatedViewFactory::class, $factory );
	}

	public function test_it_implements_the_interface(): void {
		$factory = new TemplatedViewFactory();

		$this->assertInstanceOf( ViewFactory::class, $factory );
	}

	public function test_each_factory_uses_the_theme_active_on_first_use(): void {
		Functions\when( 'get_template_directory' )->justReturn( ViewHelper::PARENT_THEME_FOLDER );

		Functions\when( 'get_stylesheet_directory' )->justReturn( ViewHelper::CHILD_THEME_FOLDER );
		$child_theme_factory  = new TemplatedViewFactory();
		$parent_theme_factory = new TemplatedViewFactory();
		$child_view           = $child_theme_factory->create( 'view-c' );

		Functions\when( 'get_stylesheet_directory' )->justReturn( ViewHelper::PARENT_THEME_FOLDER );

		$this->assertStringStartsWith(
			'<p>View C comes from child theme.</p>',
			$child_view->render()
		);
		$this->assertStringStartsWith(
			'<p>View C comes from child theme.</p>',
			$child_theme_factory->create( 'view-c' )->render()
		);
		$this->assertStringStartsWith(
			'<p>View C comes from parent theme.</p>',
			$parent_theme_factory->create( 'view-c' )->render()
		);
	}

	public function test_it_reads_theme_directories_once_on_first_create(): void {
		$reads = [];
		Functions\expect( 'get_stylesheet_directory' )->once()->andReturnUsing(
			static function () use ( &$reads ): string {
				$reads[] = 'child';

				return ViewHelper::CHILD_THEME_FOLDER;
			}
		);
		Functions\expect( 'get_template_directory' )->once()->andReturnUsing(
			static function () use ( &$reads ): string {
				$reads[] = 'parent';

				return ViewHelper::PARENT_THEME_FOLDER;
			}
		);

		$factory = new TemplatedViewFactory();
		$this->assertSame( [], $reads, 'Construction must not read theme directories.' );

		$view = $factory->create( 'view-c' );
		$this->assertSame( [ 'child', 'parent' ], $reads );
		$this->assertStringStartsWith( '<p>View C comes from child theme.</p>', $view->render() );
		$this->assertStringStartsWith(
			'<p>View C comes from child theme.</p>',
			$factory->create( 'view-c' )->render()
		);
		$this->assertSame( [ 'child', 'parent' ], $reads );
	}

	public function test_it_keeps_explicit_locations_in_the_given_order(): void {
		Functions\expect( 'get_stylesheet_directory' )->never();
		Functions\expect( 'get_template_directory' )->never();

		$factory = new TemplatedViewFactory( [ ViewHelper::PLUGIN_FOLDER, ViewHelper::CHILD_THEME_FOLDER ] );

		$this->assertStringStartsWith(
			'<p>View C comes from plugin.</p>',
			$factory->create( 'view-c' )->render()
		);
		$this->assertStringStartsWith(
			'<p>View C comes from plugin.</p>',
			$factory->create( 'view-c' )->render()
		);
	}
}
