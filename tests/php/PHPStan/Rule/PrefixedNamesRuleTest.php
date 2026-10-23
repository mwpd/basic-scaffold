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

namespace MWPD\BasicScaffold\Tests\PHPStan\Rule;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<PrefixedNamesRule>
 */
final class PrefixedNamesRuleTest extends RuleTestCase {

	private const FIXTURES = __DIR__ . '/../Fixture/Names/';

	private const TIP = 'Principle 13: One plugin, to arbitrary complexity, see '
		. 'docs/design-principles.md#13-one-plugin-to-arbitrary-complexity. '
		. 'Pattern: docs/patterns.md#i-need-a-name-in-a-space-wordpress-shares. '
		. 'Rule: docs/architecture-rules.md#architectureprefixednames';

	private const HOOKS_HOW = "the prefix constant, as in static::HOOK_PREFIX . 'name', or a name that starts with"
		. " 'acme.plugin.'";

	private bool $without_kinds = false;

	protected function getRule(): Rule {
		$kind = static fn( string $prefix, array $constants = [], array $allowed = [] ): array => [
			'prefix'    => $prefix,
			'constants' => $constants,
			'allowed'   => $allowed,
		];

		return new PrefixedNamesRule(
			new PluginNamespace( 'MWPD\BasicScaffold\Tests\PHPStan\Fixture\Names' ),
			$this->without_kinds ? [] : [
				'hooks'      => $kind( 'acme.plugin.', [ 'HOOK_PREFIX' ] ),
				'options'    => $kind( 'acme_plugin_', [], [ 'date_format' ] ),
				'transients' => $kind( 'acme_plugin_' ),
				'meta'       => $kind( 'acme_plugin_' ),
				'assets'     => $kind( 'acme-plugin-', [], [ 'jquery' ] ),
				'postTypes'  => $kind( 'acme_', [ 'POST_TYPE_PREFIX' ] ),
				'taxonomies' => $kind( 'acme_', [ 'TAXONOMY_PREFIX' ] ),
				'shortcodes' => $kind( 'acme_plugin_', [ 'SHORTCODE_PREFIX' ] ),
				'restRoutes' => $kind( 'acme/plugin', [ 'REST_PREFIX' ] ),
				'cron'       => $kind( 'acme_plugin_', [ 'CRON_PREFIX' ], [ 'core_event' ] ),
				'adminPages' => $kind( 'acme-plugin-', [ 'ADMIN_PREFIX' ] ),
				'settings'   => $kind( 'acme_plugin_', [ 'SETTING_PREFIX' ] ),
				'ajax'       => $kind( 'acme_plugin_', [ 'AJAX_PREFIX' ] ),
			],
			$this->createReflectionProvider()
		);
	}

	public function test_it_reports_names_without_the_plugins_prefix(): void {
		$this->analyse(
			[ self::FIXTURES . 'Service.php' ],
			[
				[
					$this->unprefixed(
						'do_action',
						'hook',
						'settings_saved',
						self::HOOKS_HOW,
						'hooks'
					),
					12,
					self::TIP,
				],
				[
					'apply_filters() gets a hook name the rule cannot check. Use the prefix constant, as in'
						. " static::HOOK_PREFIX . 'name', or a name that starts with 'acme.plugin.', so the name is"
						. " recognizably the plugin's own.",
					15,
					self::TIP,
				],
				[
					$this->unprefixed(
						'update_option',
						'option',
						'settings',
						$this->how( 'acme_plugin_' ),
						'options'
					),
					16,
					self::TIP,
				],
				[
					$this->unprefixed(
						'update_option',
						'option',
						'date_format',
						$this->how( 'acme_plugin_' ),
						'options'
					),
					18,
					self::TIP,
				],
				[
					$this->unprefixed(
						'set_transient',
						'transient',
						'cache',
						$this->how( 'acme_plugin_' ),
						'transients'
					),
					20,
					self::TIP,
				],
				[
					$this->unprefixed(
						'register_post_meta',
						'meta key',
						'color',
						$this->how( 'acme_plugin_' ),
						'meta'
					),
					21,
					self::TIP,
				],
				[
					$this->unprefixed(
						'wp_register_script',
						'asset handle',
						'app',
						$this->how( 'acme-plugin-' ),
						'assets'
					),
					22,
					self::TIP,
				],
				[
					$this->unprefixed(
						'wp_enqueue_style',
						'asset handle',
						'theme',
						$this->how( 'acme-plugin-' ),
						'assets'
					),
					24,
					self::TIP,
				],
				[
					$this->unprefixed(
						'register_post_meta',
						'meta key',
						'color',
						$this->how( 'acme_plugin_' ),
						'meta'
					),
					26,
					self::TIP,
				],
				[
					'do_action() gets a hook name the rule cannot check. Use the prefix constant, as in'
						. " static::HOOK_PREFIX . 'name', or a name that starts with 'acme.plugin.', so the name is"
						. " recognizably the plugin's own.",
					28,
					self::TIP,
				],
				[
					$this->unprefixed( 'do_action', 'hook', 'other.saved', self::HOOKS_HOW, 'hooks' ),
					29,
					self::TIP,
				],
				[
					$this->unprefixed(
						'wp_enqueue_script',
						'asset handle',
						'jquery',
						$this->how( 'acme-plugin-' ),
						'assets'
					),
					30,
					self::TIP,
				],
			]
		);
	}

	public function test_it_checks_names_in_other_shared_spaces(): void {
		$this->analyse(
			[ self::FIXTURES . 'Registrations.php' ],
			[
				[
					$this->unprefixed(
						'register_post_type',
						'post type',
						'book',
						$this->constant_how( 'acme_', 'POST_TYPE_PREFIX' ),
						'postTypes'
					),
					10,
					self::TIP,
				],
				[
					$this->unprefixed(
						'register_taxonomy',
						'taxonomy',
						'genre',
						$this->constant_how( 'acme_', 'TAXONOMY_PREFIX' ),
						'taxonomies'
					),
					12,
					self::TIP,
				],
				[
					$this->unprefixed(
						'add_shortcode',
						'shortcode',
						'gallery',
						$this->constant_how( 'acme_plugin_', 'SHORTCODE_PREFIX' ),
						'shortcodes'
					),
					14,
					self::TIP,
				],
				[
					$this->unprefixed(
						'register_rest_route',
						'REST namespace',
						'plugin/v1',
						$this->constant_how( 'acme/plugin', 'REST_PREFIX' ),
						'restRoutes'
					),
					16,
					self::TIP,
				],
				[
					$this->unprefixed(
						'wp_schedule_event',
						'cron hook',
						'refresh',
						$this->constant_how( 'acme_plugin_', 'CRON_PREFIX' ),
						'cron'
					),
					18,
					self::TIP,
				],
				[
					$this->unprefixed(
						'wp_schedule_single_event',
						'cron hook',
						'refresh',
						$this->constant_how( 'acme_plugin_', 'CRON_PREFIX' ),
						'cron'
					),
					20,
					self::TIP,
				],
				[
					$this->unprefixed(
						'wp_next_scheduled',
						'cron hook',
						'refresh',
						$this->constant_how( 'acme_plugin_', 'CRON_PREFIX' ),
						'cron'
					),
					22,
					self::TIP,
				],
				[
					$this->unprefixed(
						'wp_clear_scheduled_hook',
						'cron hook',
						'refresh',
						$this->constant_how( 'acme_plugin_', 'CRON_PREFIX' ),
						'cron'
					),
					24,
					self::TIP,
				],
				[
					$this->unprefixed(
						'wp_unschedule_event',
						'cron hook',
						'refresh',
						$this->constant_how( 'acme_plugin_', 'CRON_PREFIX' ),
						'cron'
					),
					26,
					self::TIP,
				],
				[
					$this->unprefixed(
						'add_menu_page',
						'admin page',
						'settings',
						$this->constant_how( 'acme-plugin-', 'ADMIN_PREFIX' ),
						'adminPages'
					),
					28,
					self::TIP,
				],
				[
					$this->unprefixed(
						'add_submenu_page',
						'admin page',
						'settings',
						$this->constant_how( 'acme-plugin-', 'ADMIN_PREFIX' ),
						'adminPages'
					),
					30,
					self::TIP,
				],
				[
					$this->unprefixed(
						'add_options_page',
						'admin page',
						'settings',
						$this->constant_how( 'acme-plugin-', 'ADMIN_PREFIX' ),
						'adminPages'
					),
					32,
					self::TIP,
				],
				[
					$this->unprefixed(
						'add_management_page',
						'admin page',
						'settings',
						$this->constant_how( 'acme-plugin-', 'ADMIN_PREFIX' ),
						'adminPages'
					),
					34,
					self::TIP,
				],
				[
					$this->unprefixed(
						'register_setting',
						'setting',
						'group',
						$this->constant_how( 'acme_plugin_', 'SETTING_PREFIX' ),
						'settings'
					),
					36,
					self::TIP,
				],
				[
					$this->unprefixed(
						'register_setting',
						'setting',
						'settings',
						$this->constant_how( 'acme_plugin_', 'SETTING_PREFIX' ),
						'settings'
					),
					38,
					self::TIP,
				],
				[
					$this->unprefixed(
						'add_action',
						'AJAX action',
						'save',
						$this->constant_how( 'acme_plugin_', 'AJAX_PREFIX' ),
						'ajax'
					),
					40,
					self::TIP,
				],
				[
					$this->unprefixed(
						'add_action',
						'AJAX action',
						'save',
						$this->constant_how( 'acme_plugin_', 'AJAX_PREFIX' ),
						'ajax'
					),
					42,
					self::TIP,
				],
			]
		);
	}

	public function test_it_limits_the_full_post_type_and_taxonomy_names(): void {
		$this->analyse(
			[ self::FIXTURES . 'NameLengths.php' ],
			[
				[
					"register_post_type() uses the post type name 'acme_1234567890123456' which exceeds"
						. ' the WordPress limit of 20 characters. Shorten the full name, including the plugin prefix,'
						. ' to at most 20 characters.',
					11,
					self::TIP,
				],
				[
					"register_taxonomy() uses the taxonomy name 'acme_1234567890123456789012345678' which exceeds"
						. ' the WordPress limit of 32 characters. Shorten the full name, including the plugin prefix,'
						. ' to at most 32 characters.',
					13,
					self::TIP,
				],
			]
		);
	}

	public function test_it_handles_prefix_constants_dynamic_names_and_ajax_subscriptions(): void {
		$this->analyse(
			[ self::FIXTURES . 'NameExpressions.php' ],
			[
				[
					$this->unprefixed(
						'wp_clear_scheduled_hook',
						'cron hook',
						'core_event',
						$this->constant_how( 'acme_plugin_', 'CRON_PREFIX' ),
						'cron'
					),
					37,
					self::TIP,
				],
				[
					'add_shortcode() gets a shortcode name the rule cannot check. Use the prefix constant, as in'
						. " static::SHORTCODE_PREFIX . 'name', or a name that starts with 'acme_plugin_',"
						. ' so the name is'
						. " recognizably the plugin's own.",
					38,
					self::TIP,
				],
				[
					'add_action() gets an AJAX action name the rule cannot check. Use the prefix constant, as in'
						. " static::AJAX_PREFIX . 'name', or a name that starts with 'acme_plugin_', so the name is"
						. " recognizably the plugin's own.",
					39,
					self::TIP,
				],
				[
					'add_action() gets an AJAX action name the rule cannot check. Use the prefix constant, as in'
						. " static::AJAX_PREFIX . 'name', or a name that starts with 'acme_plugin_', so the name is"
						. " recognizably the plugin's own.",
					40,
					self::TIP,
				],
				[
					$this->unprefixed(
						'add_action',
						'AJAX action',
						'save',
						$this->constant_how( 'acme_plugin_', 'AJAX_PREFIX' ),
						'ajax'
					),
					41,
					self::TIP,
				],
				[
					$this->unprefixed(
						'add_action',
						'AJAX action',
						'',
						$this->constant_how( 'acme_plugin_', 'AJAX_PREFIX' ),
						'ajax'
					),
					42,
					self::TIP,
				],
				[
					$this->unprefixed(
						'register_setting',
						'setting',
						'group',
						$this->constant_how( 'acme_plugin_', 'SETTING_PREFIX' ),
						'settings'
					),
					43,
					self::TIP,
				],
				[
					$this->unprefixed(
						'register_setting',
						'setting',
						'name',
						$this->constant_how( 'acme_plugin_', 'SETTING_PREFIX' ),
						'settings'
					),
					43,
					self::TIP,
				],
				[
					"register_post_type() uses the post type name 'acme_1234567890123456' which exceeds"
						. ' the WordPress limit of 20 characters. Shorten the full name, including the plugin prefix,'
						. ' to at most 20 characters.',
					44,
					self::TIP,
				],
			]
		);
	}

	public function test_it_skips_kinds_absent_from_configuration(): void {
		$this->without_kinds = true;
		$this->analyse(
			[
				self::FIXTURES . 'Registrations.php',
				self::FIXTURES . 'NameLengths.php',
				self::FIXTURES . 'NameExpressions.php',
			],
			[]
		);
	}

	private function constant_how( string $prefix, string $constant ): string {
		return "the prefix constant, as in static::{$constant} . 'name', or a name that starts with '{$prefix}'";
	}

	private function how( string $prefix ): string {
		return "a name that starts with '{$prefix}'";
	}

	private function unprefixed(
		string $called,
		string $kind,
		string $name,
		string $how,
		string $setting
	): string {
		return "{$called}() uses the {$kind} name '{$name}' without the plugin's prefix. Use {$how}"
			. " (parameters.architecture.prefixedNames.{$setting}), so plugins built from this scaffold do not"
			. ' clash.';
	}
}
