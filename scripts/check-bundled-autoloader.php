<?php
/**
 * Verify every source declaration loads without Composer or WordPress.
 *
 * Run with: php -n scripts/check-bundled-autoloader.php
 *
 * Load the bundled autoloader directly so this check is independent of the
 * bootstrap's Composer detection and does not run plugin lifecycle hooks.
 */

declare( strict_types=1 );

use MWPD\BasicScaffold\Infrastructure\Autoloader;

$source = __DIR__ . '/../src/';
$files  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ) );
$names  = [];

foreach ( $files as $file ) {
	if ( $file instanceof SplFileInfo && $file->isFile() && 'php' === $file->getExtension() ) {
		$relative = substr( $file->getPathname(), strlen( $source ), -4 );
		$names[]  = 'MWPD\\BasicScaffold\\' . str_replace( DIRECTORY_SEPARATOR, '\\', $relative );
	}
}

if ( [] === $names ) {
	fwrite( STDERR, "No PHP source declarations found in {$source}.\n" );
	exit( 1 );
}

// Another autoloader could hide a missing dependency on Composer. PHP 7 returns
// false instead of an empty array here, hence empty().
if ( ! empty( spl_autoload_functions() ) ) {
	fwrite( STDERR, "Run this check in a fresh PHP process without other autoloaders.\n" );
	exit( 1 );
}

require $source . 'Infrastructure/Autoloader.php';
$autoloader = new Autoloader();
$autoloader->add_namespace( 'MWPD\\BasicScaffold', $source )->register();
$failures = [];
sort( $names );

foreach ( $names as $name ) {
	try {
		// class_exists triggers loading for all three declaration kinds. Avoid
		// loading an interface or trait again after it returned false.
		if ( ! class_exists( $name ) && ! interface_exists( $name, false ) && ! trait_exists( $name, false ) ) {
			$failures[] = $name . ': declaration not found';
		}
	} catch ( Throwable $exception ) {
		$failures[] = $name . ': ' . $exception->getMessage();
	}
}

if ( [] !== $failures ) {
	fwrite( STDERR, "Bundled autoloader failures:\n" . implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo 'Bundled autoloader loaded all ' . count( $names ) . " source declarations.\n";
