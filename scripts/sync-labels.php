<?php
/**
 * Sync the repository's labels with .github/labels.json.
 *
 *   php scripts/sync-labels.php                     # dry run against the current repo
 *   php scripts/sync-labels.php --apply             # create, update, delete obsolete defaults
 *   php scripts/sync-labels.php --apply --prune     # also delete labels the manifest does not know
 *   php scripts/sync-labels.php --repo owner/name   # target another repository
 *
 * A dry run is the default because deleting a label removes it from every
 * issue, with no undo. --prune is separate for the same reason.
 *
 * The manifest is the single source of truth. Never create a label by hand:
 * the manifest will not know it exists, and the next sync will not fix it.
 * docs/process/github.md explains what each namespace means.
 *
 * Requires an authenticated `gh`.
 */

declare( strict_types=1 );

const MANIFEST = __DIR__ . '/../.github/labels.json';

// GitHub's defaults that the type: namespace supersedes. A taxonomy with two
// words for one thing gets both.
const OBSOLETE = [ 'bug', 'enhancement', 'documentation', 'invalid' ];

function fail( string $message ): void {
	fwrite( STDERR, $message . PHP_EOL );
	exit( 1 );
}

/**
 * @return array<string, array{color: string, description: string}>
 */
function read_manifest( string $path ): array {
	$entries = json_decode( (string) file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
	$labels  = [];

	// An empty taxonomy would plan deleting every label under --prune.
	if ( ! is_array( $entries ) || [] === $entries || array_keys( $entries ) !== range( 0, count( $entries ) - 1 ) ) {
		fail( "$path: expected a non-empty JSON list of labels" );
	}

	foreach ( $entries as $index => $entry ) {
		if ( ! is_array( $entry ) || ! is_string( $entry['name'] ?? null ) || ! is_string( $entry['description'] ?? null ) ) {
			fail( "$path: entry $index needs a string name and description" );
		}
		if ( ! preg_match( '/^[0-9a-f]{6}$/i', $entry['color'] ?? '' ) ) {
			fail( "$path: '{$entry['name']}' has an invalid color" );
		}
		if ( isset( $labels[ $entry['name'] ] ) ) {
			fail( "$path: duplicate label '{$entry['name']}'" );
		}
		$labels[ $entry['name'] ] = [
			'color'       => strtolower( $entry['color'] ),
			'description' => $entry['description'],
		];
	}

	return $labels;
}

/**
 * @param list<string> $args
 */
function gh( array $args ): string {
	$command = 'gh ' . implode( ' ', array_map( 'escapeshellarg', $args ) );
	exec( $command . ' 2>&1', $output, $status );
	$text = implode( PHP_EOL, $output );
	if ( 0 !== $status ) {
		fail( "Failed: $command" . PHP_EOL . $text );
	}
	return $text;
}

$options = getopt( '', [ 'apply', 'prune', 'repo:', 'help' ] );
if ( isset( $options['help'] ) ) {
	echo 'Usage: php scripts/sync-labels.php [--repo owner/name] [--apply] [--prune]' . PHP_EOL;
	exit( 0 );
}
$apply = isset( $options['apply'] );
$prune = isset( $options['prune'] );
$repo  = is_string( $options['repo'] ?? null )
	? $options['repo']
	: gh( [ 'repo', 'view', '--json', 'nameWithOwner', '--jq', '.nameWithOwner' ] );

$wanted   = read_manifest( MANIFEST );
$existing = [];
$listed   = gh( [ 'label', 'list', '--repo', $repo, '--limit', '500', '--json', 'name,color,description' ] );
foreach ( json_decode( $listed, true, 512, JSON_THROW_ON_ERROR ) as $label ) {
	$existing[ $label['name'] ] = [
		'color'       => strtolower( $label['color'] ),
		'description' => $label['description'],
	];
}

$actions = [];
foreach ( $wanted as $name => $spec ) {
	if ( ! isset( $existing[ $name ] ) ) {
		$actions[] = [ 'create', $name, '' ];
	} elseif ( $existing[ $name ] !== $spec ) {
		$actions[] = [ 'update', $name, '' ];
	}
}
foreach ( array_keys( $existing ) as $name ) {
	if ( isset( $wanted[ $name ] ) ) {
		continue;
	}
	if ( in_array( $name, OBSOLETE, true ) ) {
		$actions[] = [ 'delete', $name, '(obsolete default)' ];
	} elseif ( $prune ) {
		$actions[] = [ 'delete', $name, '(not in manifest)' ];
	} else {
		echo "note: '$name' is not in the manifest; --prune would delete it" . PHP_EOL;
	}
}

if ( [] === $actions ) {
	echo "$repo: labels already in sync." . PHP_EOL;
	exit( 0 );
}

foreach ( $actions as [ $verb, $name, $reason ] ) {
	echo rtrim( "$verb  $name  $reason" ) . PHP_EOL;
}

if ( ! $apply ) {
	echo PHP_EOL . "Dry run against $repo. Re-run with --apply to make these changes." . PHP_EOL;
	exit( 0 );
}

foreach ( $actions as [ $verb, $name ] ) {
	if ( 'delete' === $verb ) {
		gh( [ 'label', 'delete', $name, '--repo', $repo, '--yes' ] );
		continue;
	}
	gh( [ 'label', 'create' === $verb ? 'create' : 'edit', $name, '--repo', $repo, '--color', $wanted[ $name ]['color'], '--description', $wanted[ $name ]['description'] ] );
}

echo 'Applied ' . count( $actions ) . " change(s) to $repo." . PHP_EOL;
