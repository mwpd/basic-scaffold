<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Architecture;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

final class RepositoryLayoutTest extends TestCase {

	public function test_it_provides_the_core_registration_actions_in_order(): void {
		$this->assertSame(
			[
				'plugins_loaded',
				'after_setup_theme',
				'init',
				'wp_loaded',
				'parse_request',
				'wp',
				'template_redirect',
				'shutdown',
			],
			RepositoryLayout::of_plugin()->get_registration_actions()
		);
	}

	public function test_it_allows_the_registration_actions_to_be_overridden(): void {
		$actions = [ 'fixture.first', 'fixture.second' ];
		$layout  = new RepositoryLayout( __DIR__, '', [], [], '', [], [], [], $actions );

		$this->assertSame( $actions, $layout->get_registration_actions() );
	}

	public function test_it_preserves_the_default_order_for_existing_constructors(): void {
		$layout = new RepositoryLayout( __DIR__, '', [], [], '', [], [] );

		$this->assertSame(
			RepositoryLayout::of_plugin()->get_registration_actions(),
			$layout->get_registration_actions()
		);
	}

	public function test_it_provides_additional_core_actions_without_assigning_an_order(): void {
		$this->assertSame(
			[
				'admin_menu',
				'admin_init',
				'current_screen',
				'rest_api_init',
				'wp_enqueue_scripts',
				'admin_enqueue_scripts',
				'wp_head',
				'wp_footer',
				'admin_notices',
			],
			RepositoryLayout::of_plugin()->get_additional_core_actions()
		);
	}

	public function test_it_allows_the_additional_core_actions_to_be_overridden(): void {
		$actions = [ 'fixture.core_action' ];
		$layout  = new RepositoryLayout(
			__DIR__,
			'',
			[],
			[],
			'',
			[],
			[],
			[],
			RepositoryLayout::REGISTRATION_ACTIONS,
			$actions
		);

		$this->assertSame( $actions, $layout->get_additional_core_actions() );
	}

	public function test_it_preserves_the_additional_core_actions_for_existing_constructors(): void {
		$layout = new RepositoryLayout( __DIR__, '', [], [], '', [], [] );

		$this->assertSame(
			RepositoryLayout::of_plugin()->get_additional_core_actions(),
			$layout->get_additional_core_actions()
		);
	}
}
