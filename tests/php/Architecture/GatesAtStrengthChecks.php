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
use Nette\Neon\Neon;
use SimpleXMLElement;

/**
 * Checks that configuration changes cannot silently weaken the quality gates.
 */
final class GatesAtStrengthChecks {

	// Keep the promised strength here so changing it is an explicit review decision.
	private const PHPSTAN_LEVEL = 'max';

	private const PSALM_MAX_ERROR_LEVEL = 2;

	private const PHPCS_STANDARDS = [ 'WordPress', 'PHPCompatibility' ];

	private const PHPCS_TEST_VERSION = '7.4-';

	private const PHPUNIT_SUITES = [ 'unit', 'integration', 'architecture', 'phpstan-rules' ];

	private RepositoryLayout $layout;

	/**
	 * @param RepositoryLayout $layout Layout of the repository to check.
	 */
	public function __construct( RepositoryLayout $layout ) {
		$this->layout = $layout;
	}

	/**
	 * Check the configurations, including local PHPStan includes. Installed
	 * packages supply extensions, not the repository's quality policy.
	 *
	 * @return array<string> Problems found.
	 */
	public function check_strength(): array {
		return array_merge(
			$this->check_phpstan( $this->layout->get_phpstan_config(), [] ),
			$this->check_xml( 'psalm.xml', 'psalm' ),
			$this->check_xml( 'phpcs.xml.dist', 'ruleset' ),
			$this->check_xml( 'phpunit.xml.dist', 'phpunit' )
		);
	}

	/**
	 * @param string        $path    Configuration path.
	 * @param array<string> $visited Canonical paths already checked on this include chain.
	 * @return array<string> Problems found.
	 */
	private function check_phpstan( string $path, array $visited ): array {
		$file = $this->layout->relative( $path );
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return [ $this->unreadable( $file ) ];
		}

		$canonical = realpath( $path );
		if ( $canonical !== false && in_array( $canonical, $visited, true ) ) {
			return [];
		}
		$visited[] = $canonical !== false ? $canonical : $path;

		try {
			/**
			 * Nette declares a native mixed return type; Psalm targeting PHP 7.4
			 * reads it as a reserved word, as in SuppressionChecks.
			 *
			 * @psalm-suppress ReservedWord
			 */
			$decoded = Neon::decodeFile( $path );
		} catch ( NeonException $exception ) {
			return [ $this->unparseable( $file ) ];
		}

		if ( ! is_array( $decoded ) ) {
			return [ $this->unparseable( $file ) ];
		}

		$parameters = $decoded['parameters'] ?? $decoded['parameters!'] ?? [];
		$parameters = is_array( $parameters ) ? $parameters : [];
		$problems   = [];
		// An extension need not repeat the level, but it must not lower it.
		if ( ( $path === $this->layout->get_phpstan_config() || array_key_exists( 'level', $parameters ) )
			&& ( $parameters['level'] ?? null ) !== self::PHPSTAN_LEVEL ) {
			$problems[] = $this->problem(
				sprintf( '%1$s must set PHPStan level to %2$s.', $file, self::PHPSTAN_LEVEL )
			);
		}

		// PHPStan generates counted, path-specific ignores. Inspect local
		// includes as well as their names so renaming a baseline cannot hide it.
		$ignores = $parameters['ignoreErrors'] ?? $parameters['ignoreErrors!'] ?? [];
		foreach ( is_array( $ignores ) ? $ignores : [] as $ignore ) {
			if ( $path !== $this->layout->get_phpstan_config() && is_array( $ignore )
				&& isset( $ignore['count'], $ignore['message'], $ignore['path'] ) ) {
				$problems[] = $this->problem(
					$file . ' contains counted baseline errors. Remove the PHPStan baseline include.'
				);
				break;
			}
		}

		$includes = $decoded['includes'] ?? $decoded['includes!'] ?? [];
		foreach ( is_array( $includes ) ? $includes : [] as $include ) {
			if ( ! is_string( $include ) ) {
				$problems[] = $this->unparseable( $file );
				continue;
			}

			if ( stripos( basename( $include ), 'baseline' ) !== false ) {
				$problems[] = $this->problem(
					sprintf( '%1$s includes a PHPStan baseline (%2$s). Remove the baseline include.', $file, $include )
				);
				continue;
			}

			$root = dirname( $this->layout->get_phpstan_config() );
			$next = str_replace( '%currentWorkingDirectory%', $root, $include );
			if ( strpos( $next, '/' ) !== 0 ) {
				$next = dirname( $path ) . '/' . $next;
			}

			if ( strpos( $next, $root . '/vendor/' ) !== 0 ) {
				$problems = array_merge( $problems, $this->check_phpstan( $next, $visited ) );
			}
		}

		return $problems;
	}

	/**
	 * Parse XML rather than matching text: comments, quoting and namespaces
	 * must not change what the check sees. Keep libxml errors local so a bad
	 * configuration produces an actionable problem instead of a PHP warning.
	 *
	 * @param string $file Relative configuration path.
	 * @param string $root Expected root element.
	 * @return array<string> Problems found.
	 */
	private function check_xml( string $file, string $root ): array {
		$path = dirname( $this->layout->get_phpstan_config() ) . '/' . $file;
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return [ $this->unreadable( $file ) ];
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
			return [ $this->unparseable( $file ) ];
		}

		if ( $root === 'psalm' ) {
			return $this->check_psalm( $config, $file );
		}
		if ( $root === 'ruleset' ) {
			return $this->check_phpcs( $config, $file );
		}

		return $this->check_phpunit( $config, $file );
	}

	/**
	 * @param SimpleXMLElement $config Parsed Psalm configuration.
	 * @param string           $file   Relative configuration path.
	 * @return array<string> Problems found.
	 */
	private function check_psalm( SimpleXMLElement $config, string $file ): array {
		$problems = [];
		$level    = (string) $config['errorLevel'];
		if ( ! ctype_digit( $level ) || (int) $level < 1 || (int) $level > self::PSALM_MAX_ERROR_LEVEL ) {
			$problems[] = $this->problem(
				sprintf(
					'%1$s must set Psalm errorLevel to an integer from 1 to %2$d.',
					$file,
					self::PSALM_MAX_ERROR_LEVEL
				)
			);
		}

		if ( isset( $config['errorBaseline'] ) ) {
			$problems[] = $this->problem( $file . ' sets errorBaseline. Remove the Psalm baseline attribute.' );
		}

		return $problems;
	}

	/**
	 * @param SimpleXMLElement $config Parsed PHPCS configuration.
	 * @param string           $file   Relative configuration path.
	 * @return array<string> Problems found.
	 */
	private function check_phpcs( SimpleXMLElement $config, string $file ): array {
		$problems  = [];
		$standards = [];
		foreach ( $config->rule as $rule ) {
			$standards[] = (string) $rule['ref'];
		}
		foreach ( self::PHPCS_STANDARDS as $standard ) {
			if ( ! in_array( $standard, $standards, true ) ) {
				$problems[] = $this->problem( sprintf( '%1$s must reference the %2$s standard.', $file, $standard ) );
			}
		}

		$version = null;
		foreach ( $config->config as $setting ) {
			if ( (string) $setting['name'] === 'testVersion' ) {
				$version = (string) $setting['value'];
			}
		}
		if ( $version !== self::PHPCS_TEST_VERSION ) {
			$problems[] = $this->problem(
				sprintf( '%1$s must set testVersion to %2$s.', $file, self::PHPCS_TEST_VERSION )
			);
		}

		return $problems;
	}

	/**
	 * @param SimpleXMLElement $config Parsed PHPUnit configuration.
	 * @param string           $file   Relative configuration path.
	 * @return array<string> Problems found.
	 */
	private function check_phpunit( SimpleXMLElement $config, string $file ): array {
		$problems = [];
		$suites   = [];
		foreach ( $config->testsuites->testsuite as $suite ) {
			$suites[] = (string) $suite['name'];
		}
		foreach ( self::PHPUNIT_SUITES as $suite ) {
			if ( ! in_array( $suite, $suites, true ) ) {
				$problems[] = $this->problem( sprintf( '%1$s must declare the %2$s test suite.', $file, $suite ) );
			}
		}

		return $problems;
	}

	private function unreadable( string $file ): string {
		return $this->problem( $file . ' cannot be read. Restore a readable configuration file.' );
	}

	private function unparseable( string $file ): string {
		return $this->problem( $file . ' cannot be parsed. Restore a valid configuration file.' );
	}

	/**
	 * @param string $problem What is wrong, where, and any specific fix.
	 * @return string Complete message with guidance and the manifest explanation.
	 */
	private function problem( string $problem ): string {
		return sprintf(
			'[%1$s] %2$s Fix the code instead of weakening the gate; restore the required configuration. %3$s.',
			RuleManifest::GATES_AT_STRENGTH,
			$problem,
			RuleManifest::get_explanation( RuleManifest::GATES_AT_STRENGTH )
		);
	}
}
