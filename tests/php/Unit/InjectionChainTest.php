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

	public function test_adding_a_resolution_leaves_the_original_chain_unchanged(): void {
		$original = ( new InjectionChain() )->add_resolution( 'first' );
		$extended = $original->add_resolution( 'second' );

		$this->assertNotSame( $original, $extended );
		$this->assertFalse( $original->has_resolution( 'second' ) );
		$this->assertTrue( $extended->has_resolution( 'first' ) );
		$this->assertTrue( $extended->has_resolution( 'second' ) );
	}

	public function test_adding_to_the_chain_leaves_the_original_chain_unchanged(): void {
		$original = ( new InjectionChain() )->add_to_chain( 'first' );
		$extended = $original->add_to_chain( 'second' );

		$this->assertNotSame( $original, $extended );
		$this->assertSame( 'first', $original->get_class() );
		$this->assertSame( [ 'first' ], $original->get_chain() );
		$this->assertSame( [ 'second', 'first' ], $extended->get_chain() );
	}

	public function test_it_lists_resolutions_in_the_order_they_were_made(): void {
		$chain = ( new InjectionChain() )
			->add_resolution( 'first' )
			->add_resolution( 'second' )
			->add_resolution( 'third' );

		$this->assertSame( [ 'first', 'second', 'third' ], $chain->get_resolutions() );
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
