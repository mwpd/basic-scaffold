<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Lifecycle;

use MWPD\BasicScaffold\Infrastructure\Activateable;
use MWPD\BasicScaffold\Infrastructure\Deactivateable;
use MWPD\BasicScaffold\Infrastructure\DefaultRegistrationPriority;
use MWPD\BasicScaffold\Infrastructure\Delayed;
use MWPD\BasicScaffold\Infrastructure\Registerable;
use MWPD\BasicScaffold\Infrastructure\Service;

/**
 * A service whose post type produces rewrite rules.
 *
 * It registers the post type on init in every request. The plugin does not
 * call register() early for activation, so activate() registers the post type
 * itself before the plugin flushes the rewrite rules, and deactivate() removes
 * it again, as the WordPress handbook shows for the activation and
 * deactivation hooks.
 */
final class RewriteLifecycleService implements Service, Registerable, Delayed, Activateable, Deactivateable {

	use DefaultRegistrationPriority;

	public const POST_TYPE = 'bs_fixture_book';

	public const SLUG = 'fixture-books';

	public static function get_registration_action(): string {
		return 'init';
	}

	public function register(): void {
		$this->register_post_type();
	}

	public function activate(): void {
		$this->register_post_type();
	}

	public function deactivate(): void {
		\unregister_post_type( self::POST_TYPE );
	}

	private function register_post_type(): void {
		\register_post_type(
			self::POST_TYPE,
			[
				'public'  => true,
				'rewrite' => [ 'slug' => self::SLUG ],
			]
		);
	}
}
