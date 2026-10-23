<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\ConstructorsOnlyAssign;

use MWPD\BasicScaffold\Infrastructure\Service;

final class WidgetService extends \WP_Widget implements Service {

	public function __construct( string $id, string $name ) {
		parent::__construct( $id, $name );
		$this->name = $name;
	}
}
