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

namespace MWPD\BasicScaffold\Infrastructure\Asset;

/**
 * A point in the request where WordPress asks for scripts and styles.
 *
 * Each context stands for one WordPress action. They are enqueue points, not
 * kinds of request: the block editor, for example, fires its own action and
 * then the admin one, so a service is asked for both in the same request.
 *
 * | Context          | Action                        |
 * | ---------------- | ----------------------------- |
 * | front_end()      | wp_enqueue_scripts            |
 * | admin()          | admin_enqueue_scripts         |
 * | block_editor()   | enqueue_block_editor_assets   |
 * | login()          | login_enqueue_scripts         |
 *
 * The block editor context loads assets for the editor's own interface, such
 * as a sidebar panel. Styles for the content inside the editor are not
 * covered yet.
 */
final class AssetContext {

	private const FRONT_END = 'front_end';

	private const ADMIN = 'admin';

	private const BLOCK_EDITOR = 'block_editor';

	private const LOGIN = 'login';

	/**
	 * Which context this is.
	 *
	 * @var self::FRONT_END|self::ADMIN|self::BLOCK_EDITOR|self::LOGIN
	 */
	private string $kind;

	/**
	 * Hook suffix of the admin screen, or an empty string outside of admin.
	 */
	private string $hook_suffix;

	/**
	 * Instantiate an AssetContext object.
	 *
	 * @param self::FRONT_END|self::ADMIN|self::BLOCK_EDITOR|self::LOGIN $kind        Which context this is.
	 * @param string                                                     $hook_suffix Hook suffix of the admin screen.
	 */
	private function __construct( string $kind, string $hook_suffix = '' ) {
		$this->kind        = $kind;
		$this->hook_suffix = $hook_suffix;
	}

	/**
	 * Get the context of a front-end page, at wp_enqueue_scripts.
	 */
	public static function front_end(): self {
		return new self( self::FRONT_END );
	}

	/**
	 * Get the context of an admin screen, at admin_enqueue_scripts.
	 *
	 * @param string $hook_suffix Hook suffix of the screen, as WordPress passes
	 *                            it to admin_enqueue_scripts, such as
	 *                            "index.php" or "settings_page_my-plugin".
	 */
	public static function admin( string $hook_suffix ): self {
		return new self( self::ADMIN, $hook_suffix );
	}

	/**
	 * Get the context of the block editor's interface, at
	 * enqueue_block_editor_assets.
	 */
	public static function block_editor(): self {
		return new self( self::BLOCK_EDITOR );
	}

	/**
	 * Get the context of the login screen, at login_enqueue_scripts.
	 */
	public static function login(): self {
		return new self( self::LOGIN );
	}

	/**
	 * Check whether this is the context of a front-end page.
	 */
	public function is_front_end(): bool {
		return self::FRONT_END === $this->kind;
	}

	/**
	 * Check whether this is the context of an admin screen, of any screen if
	 * no hook suffix is given, or of the screen with that hook suffix.
	 *
	 * @param string|null $hook_suffix Optional. Hook suffix of the screen to
	 *                                 check for, such as "index.php".
	 */
	public function is_admin( ?string $hook_suffix = null ): bool {
		return self::ADMIN === $this->kind
			&& ( null === $hook_suffix || $hook_suffix === $this->hook_suffix );
	}

	/**
	 * Check whether this is the context of the block editor's interface.
	 */
	public function is_block_editor(): bool {
		return self::BLOCK_EDITOR === $this->kind;
	}

	/**
	 * Check whether this is the context of the login screen.
	 */
	public function is_login(): bool {
		return self::LOGIN === $this->kind;
	}

	/**
	 * Get the hook suffix of the admin screen.
	 *
	 * @return string Hook suffix, or an empty string outside of admin.
	 */
	public function get_hook_suffix(): string {
		return $this->hook_suffix;
	}
}
