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

namespace MWPD\BasicScaffold\Tests\Architecture;

use Nette\Neon\Exception as NeonException;
use SimpleXMLElement;

/**
 * Checks that every quality gate analyses the paths promised by the layout.
 */
final class EveryPathAnalysedChecks {

	// A new allowance needs a documented reason in the gate's configuration.
	private const PHPSTAN_EXCLUSIONS = [
		'tests/php/Fixture/views/broken-view.php',
	];

	private const PSALM_EXCLUSIONS = [
		'tests/php/PHPStan',
		'tests/php/Fixture/views/broken-view.php',
	];

	private const PHPCS_EXCLUSIONS = [];

	private RepositoryLayout $layout;

	/**
	 * @param RepositoryLayout $layout Layout of the repository to check.
	 */
	public function __construct( RepositoryLayout $layout ) {
		$this->layout = $layout;
	}

	/**
	 * @return array<string> Problems found in the three gate configurations.
	 */
	public function check_paths(): array {
		return array_merge(
			$this->check_phpstan(),
			$this->check_xml( 'Psalm', 'psalm.xml', 'psalm' ),
			$this->check_xml( 'PHPCS', 'phpcs.xml.dist', 'ruleset' )
		);
	}

	/**
	 * @return array<string> Problems found in PHPStan's included and excluded paths.
	 */
	private function check_phpstan(): array {
		$path = $this->layout->get_phpstan_config();
		$file = $this->layout->relative( $path );
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return [ $this->configuration_problem( 'PHPStan', $file, false ) ];
		}

		try {
			$config = $this->layout->read_phpstan_config();
		} catch ( NeonException $exception ) {
			return [ $this->configuration_problem( 'PHPStan', $file, true ) ];
		}
		if ( ! is_array( $config ) ) {
			return [ $this->configuration_problem( 'PHPStan', $file, true ) ];
		}

		$parameters = $config['parameters'] ?? [];
		$parameters = is_array( $parameters ) ? $parameters : [];
		$excluded   = $parameters['excludePaths'] ?? [];
		if ( is_array( $excluded ) && ( isset( $excluded['analyse'] ) || isset( $excluded['analyseAndScan'] ) ) ) {
			$excluded = array_merge(
				$this->strings( $excluded['analyse'] ?? [] ),
				$this->strings( $excluded['analyseAndScan'] ?? [] )
			);
		}

		return $this->check_gate(
			'PHPStan',
			$file,
			$this->strings( $parameters['paths'] ?? [] ),
			$this->strings( $excluded ),
			self::PHPSTAN_EXCLUSIONS,
			'parameters.paths',
			'parameters.excludePaths'
		);
	}

	/**
	 * Keep XML errors local: a broken configuration should report its fix,
	 * rather than emit a warning or affect other parsers in the suite.
	 *
	 * @param string $gate Gate being checked.
	 * @param string $file Relative configuration path.
	 * @param string $root Expected root element.
	 * @return array<string> Problems found.
	 */
	private function check_xml( string $gate, string $file, string $root ): array {
		$path = $this->layout->absolute( $file );
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return [ $this->configuration_problem( $gate, $file, false ) ];
		}

		$previous = libxml_use_internal_errors( true );
		try {
			$config = simplexml_load_string(
				(string) file_get_contents( $path ),
				SimpleXMLElement::class,
				LIBXML_NONET
			);
		} finally {
			libxml_clear_errors();
			libxml_use_internal_errors( $previous );
		}
		if ( $config === false || $config->getName() !== $root ) {
			return [ $this->configuration_problem( $gate, $file, true ) ];
		}

		$included = [];
		$excluded = [];
		if ( $gate === 'Psalm' ) {
			foreach ( [ 'file', 'directory' ] as $element ) {
				foreach ( $config->{'projectFiles'}->$element as $entry ) {
					$included[] = (string) $entry['name'];
				}
				foreach ( $config->{'projectFiles'}->{'ignoreFiles'}->$element as $entry ) {
					$excluded[] = (string) $entry['name'];
				}
			}
		} else {
			foreach ( $config->file as $entry ) {
				$included[] = (string) $entry;
			}
			// A rule-scoped pattern disables a sniff, not analysis of a path.
			foreach ( $config->{'exclude-pattern'} as $entry ) {
				$excluded[] = ltrim( (string) $entry, '/' );
			}
		}

		return $this->check_gate(
			$gate,
			$file,
			$included,
			$excluded,
			$gate === 'Psalm' ? self::PSALM_EXCLUSIONS : self::PHPCS_EXCLUSIONS,
			$gate === 'Psalm' ? 'projectFiles' : '<file> entries',
			$gate === 'Psalm' ? 'projectFiles/ignoreFiles' : 'global <exclude-pattern> entries'
		);
	}

	/**
	 * @param string        $gate              Gate being checked.
	 * @param string        $file              Configuration to edit.
	 * @param array<string> $included          Included roots.
	 * @param array<string> $excluded          Excluded paths or patterns.
	 * @param array<string> $allowed           Documented exceptions to coverage.
	 * @param string        $include_setting   Setting for included roots.
	 * @param string        $exclusion_setting Setting for exclusions.
	 * @return array<string> Problems found.
	 */
	private function check_gate(
		string $gate,
		string $file,
		array $included,
		array $excluded,
		array $allowed,
		string $include_setting,
		string $exclusion_setting
	): array {
		$included = array_map( [ $this, 'normalize' ], $included );
		$allowed  = array_merge( $this->layout->get_rule_fixture_paths(), $allowed );
		$allowed  = array_map( [ $this, 'normalize' ], $allowed );
		$problems = [];
		foreach ( $this->layout->get_analysis_paths() as $required ) {
			$required = $this->normalize( $required );
			$relative = $this->layout->relative( $required );
			if ( ! $this->inside_any( $required, $included ) ) {
				$problems[] = $this->problem(
					sprintf(
						'%1$s does not analyse %2$s. Add it to %3$s in %4$s.',
						$gate,
						$relative,
						$include_setting,
						$file
					)
				);
			}
			foreach ( $excluded as $exclusion ) {
				$pattern = $this->normalize( $exclusion );
				if ( ! $this->overlaps( $pattern, $required ) ) {
					continue;
				}
				// Only the fixed prefix can prove that an entire glob stays
				// inside a permitted fixture; matching one file is insufficient.
				$prefix = $this->pattern_root( $pattern );
				if ( $prefix !== $required && $this->inside_any( $prefix, [ $required ] )
					&& $this->inside_any( $prefix, $allowed ) ) {
					continue;
				}
				$problems[] = $this->problem(
					sprintf(
						'%1$s excludes %2$s through %3$s. Remove it from %4$s in %5$s.',
						$gate,
						$relative,
						$exclusion,
						$exclusion_setting,
						$file
					)
				);
			}
		}

		return $problems;
	}

	/**
	 * Resolve paths lexically so missing files cannot hide a coverage gap.
	 *
	 * @param string $path Relative or absolute path, optionally containing globs.
	 * @return string Normalized absolute path.
	 */
	private function normalize( string $path ): string {
		$root  = dirname( $this->layout->get_phpstan_config() );
		$path  = str_replace( [ '%currentWorkingDirectory%', '\\' ], [ $root, '/' ], $path );
		$path  = strpos( $path, '/' ) === 0 ? $path : $root . '/' . $path;
		$parts = [];
		foreach ( explode( '/', $path ) as $part ) {
			if ( $part === '..' ) {
				array_pop( $parts );
			} elseif ( $part !== '' && $part !== '.' ) {
				$parts[] = $part;
			}
		}

		return '/' . implode( '/', $parts );
	}

	/**
	 * @param string        $path  Normalized path.
	 * @param array<string> $roots Normalized roots.
	 * @return bool Whether a root equals or contains the path.
	 */
	private function inside_any( string $path, array $roots ): bool {
		foreach ( $roots as $root ) {
			if ( $path === $root || strpos( $path, rtrim( $root, '/' ) . '/' ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $pattern Normalized exclusion pattern.
	 * @return string Fixed ancestor containing every path the pattern can exclude.
	 */
	private function pattern_root( string $pattern ): string {
		$length = strcspn( $pattern, '*?[' );

		return $length === strlen( $pattern ) ? $pattern : dirname( substr( $pattern, 0, $length ) . '_' );
	}

	/**
	 * Check the common path components, including globs, without relying on
	 * files that happen to exist today. A sub-exclusion can hide future code too.
	 *
	 * @param string $pattern Normalized exclusion pattern.
	 * @param string $path    Required root.
	 * @return bool Whether the pattern can exclude the root or something inside it.
	 */
	private function overlaps( string $pattern, string $path ): bool {
		$ancestor = $path;
		while ( true ) {
			if ( fnmatch( $pattern, $ancestor ) ) {
				return true;
			}
			if ( $ancestor === '/' ) {
				break;
			}
			$ancestor = dirname( $ancestor );
		}
		// The bootstrap is a file: a pattern below it cannot hide its code.
		if ( $path === $this->normalize( $this->layout->get_bootstrap() ) ) {
			return false;
		}

		$parts = explode( '/', $pattern );
		$roots = explode( '/', $path );
		foreach ( $parts as $index => $part ) {
			if ( ! isset( $roots[ $index ] ) ) {
				return true;
			}
			if ( ! fnmatch( $part, $roots[ $index ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param mixed $value Untrusted configuration value.
	 * @return array<string> String entries only.
	 */
	private function strings( $value ): array {
		return is_array( $value ) ? array_values( array_filter( $value, 'is_string' ) ) : [];
	}

	private function configuration_problem( string $gate, string $file, bool $parse ): string {
		return $this->problem(
			$gate . ' configuration ' . $file . ( $parse
				? ' cannot be parsed. Restore a valid configuration file.'
				: ' cannot be read. Restore a readable configuration file.' )
		);
	}

	/**
	 * @param string $problem What is wrong, where, and the specific fix.
	 * @return string Complete message with guidance and the manifest explanation.
	 */
	private function problem( string $problem ): string {
		return sprintf(
			'[%1$s] %2$s Fix the code instead of weakening the gate; restore the required configuration. %3$s.',
			RuleManifest::EVERY_PATH_ANALYSED,
			$problem,
			RuleManifest::get_explanation( RuleManifest::EVERY_PATH_ANALYSED )
		);
	}
}
