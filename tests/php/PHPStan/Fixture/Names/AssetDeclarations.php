<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\PHPStan\Fixture\Names;

use MWPD\BasicScaffold\Infrastructure\Asset\Script;
use MWPD\BasicScaffold\Infrastructure\Asset\Style;

final class AssetDeclarations {
	public function declare( string $handle ): array {
		return [
			Style::file( 'acme-plugin-notice', 'assets/notice.css' ),
			Style::file( 'notice', 'assets/notice.css' ),
			Style::inline( 'inline-notice', 'p {}' ),
			Script::file( 'copy', 'assets/copy.js' ),
			Script::file( $handle, 'assets/copy.js' ),
			\MWPD\BasicScaffold\Infrastructure\Asset\STYLE::FILE( 'shouting', 'a.css' ),
		];
	}
}
