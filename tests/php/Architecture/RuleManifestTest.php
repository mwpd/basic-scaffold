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

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * The explanation in every architecture failure links to a principle and to
 * a catalogue entry. A link that leads nowhere sends the reader searching, so
 * every link in the manifest has to resolve.
 */
final class RuleManifestTest extends TestCase {

	public function test_every_rule_links_to_an_existing_principle(): void {
		$anchors = $this->get_heading_anchors( RuleManifest::DESIGN_PRINCIPLES );

		foreach ( RuleManifest::get_rules() as $identifier => $rule ) {
			$this->assertContains(
				$rule['principle_anchor'],
				$anchors,
				sprintf(
					'%1$s links to #%2$s, which is not a heading in %3$s. '
					. 'Point principle_anchor in RuleManifest at the principle the rule enforces.',
					$identifier,
					$rule['principle_anchor'],
					RuleManifest::DESIGN_PRINCIPLES
				)
			);
		}
	}

	public function test_every_rule_has_a_section_in_the_catalogue(): void {
		$anchors = $this->get_heading_anchors( RuleManifest::CATALOGUE );

		foreach ( array_keys( RuleManifest::get_rules() ) as $identifier ) {
			$this->assertContains(
				RuleManifest::get_catalogue_anchor( $identifier ),
				$anchors,
				sprintf(
					'%1$s has no section in %2$s. Add a "### `%1$s`" heading '
					. 'that says what the rule checks, what it allows and how to configure it.',
					$identifier,
					RuleManifest::CATALOGUE
				)
			);
		}
	}

	/**
	 * Get the anchors GitHub generates for the headings of a Markdown file.
	 *
	 * @param string $file Path of the file, relative to the plugin root.
	 * @return array<string> Anchors, without the leading "#".
	 */
	private function get_heading_anchors( string $file ): array {
		$path     = dirname( __DIR__, 3 ) . '/' . $file;
		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		$this->assertIsString( $contents, "{$file} cannot be read." );

		preg_match_all( '/^#{1,6}\s+(.+?)\s*$/m', $contents, $matches );

		return array_map(
			static function ( string $heading ): string {
				$anchor = strtolower( $heading );
				$anchor = (string) preg_replace( '/[^a-z0-9 _-]/', '', $anchor );

				return str_replace( ' ', '-', $anchor );
			},
			$matches[1]
		);
	}
}
