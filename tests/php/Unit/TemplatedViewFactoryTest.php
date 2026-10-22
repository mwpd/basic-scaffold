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

	public function test_each_factory_uses_the_theme_active_when_it_was_created(): void {
		Functions\when( 'get_template_directory' )->justReturn( ViewHelper::PARENT_THEME_FOLDER );

		Functions\when( 'get_stylesheet_directory' )->justReturn( ViewHelper::CHILD_THEME_FOLDER );
		$child_theme_factory = new TemplatedViewFactory();

		Functions\when( 'get_stylesheet_directory' )->justReturn( ViewHelper::PARENT_THEME_FOLDER );
		$parent_theme_factory = new TemplatedViewFactory();

		$this->assertStringStartsWith(
			'<p>View C comes from child theme.</p>',
			$child_theme_factory->create( 'view-c' )->render()
		);
		$this->assertStringStartsWith(
			'<p>View C comes from parent theme.</p>',
			$parent_theme_factory->create( 'view-c' )->render()
		);
	}
}
