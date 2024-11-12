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

namespace MWPD\BasicScaffold\Tests\Fixture;

final class DummyClassWithDependency implements DummyInterface {

	/** @var DummyClass */
	private $dummy;

	public function __construct( DummyClass $dummy ) {
		$this->dummy = $dummy;
	}

	public function get_dummy(): DummyClass {
		return $this->dummy;
	}
}
