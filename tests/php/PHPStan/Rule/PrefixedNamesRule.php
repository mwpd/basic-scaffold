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

use MWPD\BasicScaffold\Tests\Architecture\RuleManifest;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\Type;

/**
 * The names the plugin creates carry its prefix.
 *
 * Several plugins built from this scaffold run side by side, so every name
 * they put into WordPress's shared spaces has to be their own: the hooks they
 * fire, their stored data, registered content, endpoints and admin pages.
 * Each kind of name has its own prefix, so each can follow the
 * convention WordPress uses for it.
 *
 * A name passes when PHPStan knows it starts with the prefix, or when it is
 * built from one of the class's own prefix constants, such as
 * `static::HOOK_PREFIX . 'services'`. Names that only read what WordPress
 * itself owns, such as `get_option( 'date_format' )`, can be allowed per kind.
 * Names WordPress owns that the plugin also has to write, such as the
 * `uninstall_plugins` option it removes its uninstall callback from, are
 * listed separately per kind, so every such write stays reviewed.
 *
 * @phpstan-type Kind array{prefix: string, constants: array<string>, allowed: array<string>, managed: array<string>}
 * @implements Rule<FuncCall>
 */
final class PrefixedNamesRule implements Rule {

	/**
	 * The functions checked: kind of name, positions of the names, and whether
	 * the call only reads a name, so that names WordPress owns may be allowed.
	 */
	private const FUNCTIONS = [
		'do_action'                => [ 'hooks', [ 0 ], true ],
		'do_action_ref_array'      => [ 'hooks', [ 0 ], true ],
		'apply_filters'            => [ 'hooks', [ 0 ], true ],
		'apply_filters_ref_array'  => [ 'hooks', [ 0 ], true ],
		'add_option'               => [ 'options', [ 0 ], false ],
		'update_option'            => [ 'options', [ 0 ], false ],
		'delete_option'            => [ 'options', [ 0 ], false ],
		'get_option'               => [ 'options', [ 0 ], true ],
		'add_site_option'          => [ 'options', [ 0 ], false ],
		'update_site_option'       => [ 'options', [ 0 ], false ],
		'delete_site_option'       => [ 'options', [ 0 ], false ],
		'get_site_option'          => [ 'options', [ 0 ], true ],
		'set_transient'            => [ 'transients', [ 0 ], false ],
		'get_transient'            => [ 'transients', [ 0 ], false ],
		'delete_transient'         => [ 'transients', [ 0 ], false ],
		'set_site_transient'       => [ 'transients', [ 0 ], false ],
		'get_site_transient'       => [ 'transients', [ 0 ], false ],
		'delete_site_transient'    => [ 'transients', [ 0 ], false ],
		'register_meta'            => [ 'meta', [ 1 ], false ],
		'register_post_meta'       => [ 'meta', [ 1 ], false ],
		'register_term_meta'       => [ 'meta', [ 1 ], false ],
		'wp_register_script'       => [ 'assets', [ 0 ], false ],
		'wp_register_style'        => [ 'assets', [ 0 ], false ],
		'wp_enqueue_script'        => [ 'assets', [ 0 ], true ],
		'wp_enqueue_style'         => [ 'assets', [ 0 ], true ],
		'register_post_type'       => [ 'postTypes', [ 0 ], false ],
		'register_taxonomy'        => [ 'taxonomies', [ 0 ], false ],
		'add_shortcode'            => [ 'shortcodes', [ 0 ], false ],
		'register_rest_route'      => [ 'restRoutes', [ 0 ], false ],
		'wp_schedule_event'        => [ 'cron', [ 2 ], false ],
		'wp_schedule_single_event' => [ 'cron', [ 1 ], false ],
		'wp_next_scheduled'        => [ 'cron', [ 0 ], true ],
		'wp_clear_scheduled_hook'  => [ 'cron', [ 0 ], false ],
		'wp_unschedule_event'      => [ 'cron', [ 1 ], false ],
		'add_menu_page'            => [ 'adminPages', [ 3 ], false ],
		'add_submenu_page'         => [ 'adminPages', [ 4 ], false ],
		'add_options_page'         => [ 'adminPages', [ 3 ], false ],
		'add_management_page'      => [ 'adminPages', [ 3 ], false ],
		'register_setting'         => [ 'settings', [ 0, 1 ], false ],
		'add_action'               => [ 'ajax', [ 0 ], false ],
	];

	/**
	 * WordPress limits on the full name, including its prefix.
	 */
	private const MAX_LENGTHS = [
		'postTypes'  => 20,
		'taxonomies' => 32,
	];

	/**
	 * Code the rule applies to.
	 */
	private PluginNamespace $plugin_namespace;

	/**
	 * Prefix, prefix constants and allowed names of each kind of name.
	 *
	 * @var array<string, Kind>
	 */
	private array $kinds;

	/**
	 * Reflection, to tell which function a call refers to.
	 */
	private ReflectionProvider $reflection;

	/**
	 * Instantiate a PrefixedNamesRule object.
	 *
	 * @param PluginNamespace     $plugin_namespace Code the rule applies to.
	 * @param array<string, Kind> $kinds            Prefix, prefix constants and
	 *                                              allowed names of each kind.
	 * @param ReflectionProvider  $reflection       Reflection, to tell which
	 *                                              function a call refers to.
	 */
	public function __construct( PluginNamespace $plugin_namespace, array $kinds, ReflectionProvider $reflection ) {
		$this->plugin_namespace = $plugin_namespace;
		$this->kinds            = $kinds;
		$this->reflection       = $reflection;
	}

	/**
	 * Get the type of node the rule inspects.
	 *
	 * @return string Node class name.
	 */
	public function getNodeType(): string {
		return FuncCall::class;
	}

	/**
	 * Report a name the plugin creates without its prefix.
	 *
	 * @param FuncCall $node  Function call to inspect.
	 * @param Scope    $scope Scope of the call.
	 * @return list<IdentifierRuleError> Errors found.
	 */
	public function processNode( Node $node, Scope $scope ): array {
		$called = CalledFunction::name_of( $node, $scope, $this->reflection );

		if ( $called === null
			|| ! isset( self::FUNCTIONS[ $called ] )
			|| ! $this->plugin_namespace->contains_namespace( $scope->getNamespace() ) ) {
			return [];
		}

		[ $kind, $positions, $reads ] = self::FUNCTIONS[ $called ];
		$settings                     = $this->kinds[ $kind ] ?? null;

		if ( $settings === null ) {
			return [];
		}

		// Enqueueing with a source registers the handle, so it creates the name.
		if ( $kind === 'assets' && $reads && self::passes_source( $node, $scope ) ) {
			$reads = false;
		}

		$errors = [];
		foreach ( $positions as $position ) {
			$problem = $this->check_argument( $node, $position, $scope, $called, $kind, $settings, $reads );
			if ( $problem === null ) {
				continue;
			}

			$errors[] = RuleErrorBuilder::message( $problem )
				->identifier( RuleManifest::PREFIXED_NAMES )
				->tip( RuleManifest::get_explanation( RuleManifest::PREFIXED_NAMES ) )
				->build();
		}

		return $errors;
	}

	/**
	 * Find the argument that gives the name, and check it.
	 *
	 * An argument unpacked from a constant array is read from the array. Any
	 * other unpacking hides the name from the rule.
	 *
	 * @param FuncCall $node     Function call.
	 * @param int      $position Position of the name.
	 * @param Scope    $scope    Scope of the call.
	 * @param string   $called   Function the name is passed to.
	 * @param string   $kind     Kind of name.
	 * @param Kind     $settings Prefix, prefix constants and allowed names.
	 * @param bool     $reads    Whether the call only reads the name.
	 * @return string|null What is wrong and what to do instead, or null.
	 */
	private function check_argument(
		FuncCall $node,
		int $position,
		Scope $scope,
		string $called,
		string $kind,
		array $settings,
		bool $reads
	): ?string {
		$index = 0;

		foreach ( $node->getArgs() as $argument ) {
			if ( ! $argument->unpack ) {
				if ( $index === $position ) {
					$type = $scope->getType( $argument->value );

					return $this->check_name( $argument->value, $type, $scope, $called, $kind, $settings, $reads );
				}

				++$index;
				continue;
			}

			$arrays = $scope->getType( $argument->value )->getConstantArrays();
			if ( count( $arrays ) !== 1 ) {
				return $this->check_name(
					null,
					$scope->getType( $argument->value ),
					$scope,
					$called,
					$kind,
					$settings,
					$reads
				);
			}

			foreach ( $arrays[0]->getValueTypes() as $type ) {
				if ( $index === $position ) {
					return $this->check_name( null, $type, $scope, $called, $kind, $settings, $reads );
				}

				++$index;
			}
		}

		return null;
	}

	/**
	 * Check a name, stripping WordPress's AJAX wrapper before checking its prefix.
	 *
	 * Other add_action() subscriptions belong to code outside the plugin and
	 * are not names the plugin creates. A dynamic action is checked only when
	 * its leading expression identifies it as AJAX.
	 *
	 * @param Expr|null $name     Expression that gives the name.
	 * @param Type      $type     Type of the name.
	 * @param Scope     $scope    Scope of the call.
	 * @param string    $called   Function called.
	 * @param string    $kind     Kind of name.
	 * @param Kind      $settings Prefix, prefix constants and allowed names.
	 * @param bool      $reads    Whether the call only reads the name.
	 * @return string|null What is wrong and what to do instead, or null.
	 */
	private function check_name(
		?Expr $name,
		Type $type,
		Scope $scope,
		string $called,
		string $kind,
		array $settings,
		bool $reads
	): ?string {
		if ( $kind !== 'ajax' ) {
			return $this->check( $name, $type, $called, $kind, $settings, $reads );
		}

		if ( $name !== null && $this->ajax_uses_prefix_constant( $name, $scope, $settings['constants'] ) ) {
			return null;
		}

		$strings = $type->getConstantStrings();
		foreach ( $strings as $string ) {
			$value  = $string->getValue();
			$length = self::ajax_wrapper_length( $value );
			if ( $length === 0 ) {
				continue;
			}

			$problem = $this->check(
				null,
				new ConstantStringType( substr( $value, $length ) ),
				$called,
				$kind,
				$settings,
				false
			);
			if ( $problem !== null ) {
				return $problem;
			}
		}

		if ( $strings !== [] || $name === null ) {
			return null;
		}

		while ( $name instanceof Concat ) {
			$name    = $name->left;
			$leading = $scope->getType( $name )->getConstantStrings();
			foreach ( $leading as $string ) {
				if ( self::ajax_wrapper_length( $string->getValue() ) > 0 ) {
					return $this->check( null, $type, $called, $kind, $settings, false );
				}
			}
		}

		return null;
	}

	/**
	 * Recognize a configured prefix constant after an AJAX wrapper.
	 *
	 * @param Expr          $name      Action name expression.
	 * @param Scope         $scope     Scope of the call.
	 * @param array<string> $constants Names of the prefix constants.
	 * @return bool Whether the AJAX action starts with a prefix constant.
	 */
	private function ajax_uses_prefix_constant( Expr $name, Scope $scope, array $constants ): bool {
		if ( ! $name instanceof Concat ) {
			return false;
		}

		$strings = $scope->getType( $name->left )->getConstantStrings();
		if ( count( $strings ) === 1
			&& in_array( $strings[0]->getValue(), [ 'wp_ajax_', 'wp_ajax_nopriv_' ], true )
			&& $this->starts_with_prefix_constant( $name->right, $constants ) ) {
			return true;
		}

		return $this->ajax_uses_prefix_constant( $name->left, $scope, $constants );
	}

	/**
	 * Get the length of WordPress's AJAX wrapper, or zero for other actions.
	 *
	 * @param string $name Action name.
	 * @return int Length to strip before checking the plugin's prefix.
	 */
	private static function ajax_wrapper_length( string $name ): int {
		foreach ( [ 'wp_ajax_nopriv_', 'wp_ajax_' ] as $wrapper ) {
			if ( strpos( $name, $wrapper ) === 0 ) {
				return strlen( $wrapper );
			}
		}

		return 0;
	}

	/**
	 * Check whether an enqueue call passes a source, which registers the handle.
	 *
	 * @param FuncCall $node  Enqueue call.
	 * @param Scope    $scope Scope of the call.
	 * @return bool Whether a source other than an empty string may be passed.
	 */
	private static function passes_source( FuncCall $node, Scope $scope ): bool {
		$source = $node->getArgs()[1] ?? null;

		if ( $source === null ) {
			return false;
		}

		$strings = $scope->getType( $source->value )->getConstantStrings();

		return $strings === [] || $strings[0]->getValue() !== '';
	}

	/**
	 * Check one name.
	 *
	 * @param Expr|null $name     Expression that gives the name, if there is
	 *                            one of its own.
	 * @param Type      $type     Type of the name.
	 * @param string    $called   Function the name is passed to.
	 * @param string    $kind     Kind of name.
	 * @param Kind      $settings Prefix, prefix constants and allowed names.
	 * @param bool      $reads    Whether the call only reads the name.
	 * @return string|null What is wrong and what to do instead, or null.
	 */
	private function check(
		?Expr $name,
		Type $type,
		string $called,
		string $kind,
		array $settings,
		bool $reads
	): ?string {
		if ( $name !== null && $this->starts_with_prefix_constant( $name, $settings['constants'] ) ) {
			return null;
		}

		$how = $settings['constants'] === []
			? sprintf( "a name that starts with '%s'", $settings['prefix'] )
			: sprintf(
				"the prefix constant, as in static::%s . 'name', or a name that starts with '%s'",
				$settings['constants'][0],
				$settings['prefix']
			);

		$strings = $type->getConstantStrings();
		if ( $strings === [] ) {
			return sprintf(
				'%1$s() gets %2$s %3$s name the rule cannot check. Use %4$s, so the name is recognizably the'
				. ' plugin\'s own.',
				$called,
				in_array( $kind, [ 'assets', 'adminPages', 'ajax' ], true ) ? 'an' : 'a',
				self::describe_kind( $kind ),
				$how
			);
		}

		foreach ( $strings as $string ) {
			$value = $string->getValue();
			$limit = self::MAX_LENGTHS[ $kind ] ?? null;
			if ( $limit !== null && strlen( $value ) > $limit ) {
				return sprintf(
					"%1\$s() uses the %2\$s name '%3\$s' which exceeds the WordPress limit of %4\$d characters."
					. ' Shorten the full name, including the plugin prefix, to at most %4$d characters.',
					$called,
					self::describe_kind( $kind ),
					$value,
					$limit
				);
			}

			if ( strpos( $value, $settings['prefix'] ) === 0
				|| ( $reads && in_array( $value, $settings['allowed'], true ) )
				|| in_array( $value, $settings['managed'], true ) ) {
				continue;
			}

			return sprintf(
				"%1\$s() uses the %2\$s name '%3\$s' without the plugin's prefix. Use %4\$s"
				. ' (parameters.architecture.prefixedNames.%5$s), so plugins built from this scaffold do not'
				. ' clash.',
				$called,
				self::describe_kind( $kind ),
				$value,
				$how,
				$kind
			);
		}

		return null;
	}

	/**
	 * Check whether a name is built from a prefix constant.
	 *
	 * @param Expr          $name      Expression that gives the name.
	 * @param array<string> $constants Names of the prefix constants.
	 * @return bool Whether the left-most part is a prefix constant.
	 */
	private function starts_with_prefix_constant( Expr $name, array $constants ): bool {
		while ( $name instanceof Concat ) {
			$name = $name->left;
		}

		// Only the plugin's own constant counts, not one of the same name in
		// another class.
		return $name instanceof ClassConstFetch
			&& $name->class instanceof Name
			&& in_array( $name->class->toLowerString(), [ 'self', 'static' ], true )
			&& $name->name instanceof Identifier
			&& in_array( $name->name->toString(), $constants, true );
	}

	/**
	 * Describe a kind of name for a message.
	 *
	 * @param string $kind Kind of name.
	 * @return string Description.
	 */
	private static function describe_kind( string $kind ): string {
		$descriptions = [
			'hooks'      => 'hook',
			'options'    => 'option',
			'transients' => 'transient',
			'meta'       => 'meta key',
			'assets'     => 'asset handle',
			'postTypes'  => 'post type',
			'taxonomies' => 'taxonomy',
			'shortcodes' => 'shortcode',
			'restRoutes' => 'REST namespace',
			'cron'       => 'cron hook',
			'adminPages' => 'admin page',
			'settings'   => 'setting',
			'ajax'       => 'AJAX action',
		];

		return $descriptions[ $kind ] ?? $kind;
	}
}
