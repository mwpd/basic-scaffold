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
		. 'Rule: docs/architecture-rules.md#architectureprefixednames';

	private const HOOKS_HOW = "the prefix constant, as in static::HOOK_PREFIX . 'name', or a name that starts with"
		. " 'acme.plugin.'";

	protected function getRule(): Rule {
		$kind = static fn( string $prefix, array $constants = [], array $allowed = [] ): array => [
			'prefix'    => $prefix,
			'constants' => $constants,
			'allowed'   => $allowed,
		];

		return new PrefixedNamesRule(
			new PluginNamespace( 'MWPD\BasicScaffold\Tests\PHPStan\Fixture\Names' ),
			[
				'hooks'      => $kind( 'acme.plugin.', [ 'HOOK_PREFIX' ] ),
				'options'    => $kind( 'acme_plugin_', [], [ 'date_format' ] ),
				'transients' => $kind( 'acme_plugin_' ),
				'meta'       => $kind( 'acme_plugin_' ),
				'assets'     => $kind( 'acme-plugin-', [], [ 'jquery' ] ),
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
