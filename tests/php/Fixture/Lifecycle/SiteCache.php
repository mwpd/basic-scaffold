<?php

declare(strict_types=1);

namespace MWPD\BasicScaffold\Tests\Fixture\Lifecycle;

/**
 * A shared collaborator that reads a site option once and keeps it, as
 * TemplatedViewFactory keeps the theme's locations.
 */
final class SiteCache {

	private ?string $name = null;

	public function site_name(): string {
		if ( null === $this->name ) {
			$name       = \get_option( 'blogname' );
			$this->name = is_string( $name ) ? $name : '';
		}

		return $this->name;
	}
}
