<?php

declare( strict_types=1 );

namespace OtherPlugin {
	final class Service {
		public function output(): void {
			echo '<p>Other plugin</p>';
			print 'Other plugin';
			printf( '%s', 'Other plugin' );
			vprintf( '%s', [ 'Other plugin' ] );
		}
	}
	?><p>Other namespace</p><?php
}

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\OutputOnlyThroughViewsOther {
	echo 'Namespace prefix without a separator';
}

namespace {
	echo 'Global namespace';
}
