<?php
declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Unit;

use LogicException;
use MWPD\BasicScaffold\Exception\BasicScaffoldException;
use MWPD\BasicScaffold\Infrastructure\Injector\InjectionChain;

final class InjectionChainTest extends TestCase {

	public function test_it_can_be_initialized(): void {
		$chain = new InjectionChain();

		$this->assertInstanceOf( InjectionChain::class, $chain );
	}

	public function test_it_accepts_new_resolutions(): void {
		$chain = ( new InjectionChain() )
			->add_resolution( 'something' );

		$this->assertTrue( $chain->has_resolution( 'something' ) );
		$this->assertFalse( $chain->has_resolution( 'something_else' ) );
	}

	public function test_it_accepts_new_chain_entries(): void {
		$chain = ( new InjectionChain() )
			->add_to_chain( 'something' );

		$this->assertEquals( 'something', $chain->get_class() );
	}

	public function test_it_returns_the_last_class_in_the_chain(): void {
		$chain = ( new InjectionChain() )
			->add_to_chain( 'first' )
			->add_to_chain( 'second' )
			->add_to_chain( 'third' );

		$this->assertEquals( 'third', $chain->get_class() );
	}

	public function test_it_retains_all_elements_in_the_chain(): void {
		$chain = ( new InjectionChain() )
			->add_to_chain( 'first' )
			->add_to_chain( 'second' )
			->add_to_chain( 'third' );

		$this->assertEquals( [ 'third', 'second', 'first' ], $chain->get_chain() );
	}

	public function test_reading_an_empty_chain_throws_a_scaffold_logic_exception(): void {
		try {
			( new InjectionChain() )->get_class();
			$this->fail( 'Reading the class of an empty injection chain did not throw.' );
		} catch ( LogicException $exception ) {
			$this->assertInstanceOf( BasicScaffoldException::class, $exception );
			$this->assertSame(
				'Access to injection chain before any resolution was made.',
				$exception->getMessage()
			);
		}
	}
}
