<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Unit;

use MWPD\BasicScaffold\Exception\FailedToFindPost;
use MWPD\BasicScaffold\SampleSubsystem\MainLoop;
use MWPD\BasicScaffold\SampleSubsystem\QueriedPostHeader;
use stdClass;
use WP_Post;

/**
 * @covers \MWPD\BasicScaffold\SampleSubsystem\QueriedPostHeader
 */
final class QueriedPostHeaderTest extends TestCase {

	public function test_it_answers_with_the_fields_of_the_queried_post_unescaped(): void {
		$header = new QueriedPostHeader( $this->main_loop_on( 'A <em>title</em>' ) );

		// Escaping is the template scope's job, so the presenter returns text.
		$this->assertSame( 'A <em>title</em>', $header->title() );
		$this->assertSame( '2026-10-22 12:00:00', $header->date() );
	}

	public function test_it_asks_the_main_loop_each_time_it_is_called(): void {
		$main_loop = $this->createMock( MainLoop::class );
		$main_loop->expects( $this->exactly( 2 ) )
			->method( 'queried_post' )
			->willReturnOnConsecutiveCalls( $this->post( 'First' ), $this->post( 'Second' ) );

		$header = new QueriedPostHeader( $main_loop );

		$this->assertSame( 'First', $header->title() );
		$this->assertSame( 'Second', $header->title() );
	}

	public function test_it_passes_on_a_missing_post(): void {
		$main_loop = $this->createMock( MainLoop::class );
		$main_loop->method( 'queried_post' )
			->willThrowException( FailedToFindPost::for_main_query() );

		$this->expectException( FailedToFindPost::class );

		( new QueriedPostHeader( $main_loop ) )->title();
	}

	/**
	 * Create a main loop whose queried post has the given title.
	 *
	 * @param string $title Title of the queried post.
	 * @return MainLoop Main loop double.
	 */
	private function main_loop_on( string $title ): MainLoop {
		$main_loop = $this->createMock( MainLoop::class );
		$main_loop->method( 'queried_post' )->willReturn( $this->post( $title ) );

		return $main_loop;
	}

	/**
	 * Create a post with a title and a date.
	 *
	 * @param string $title Title of the post.
	 * @return WP_Post Post.
	 */
	private function post( string $title ): WP_Post {
		$fields             = new stdClass();
		$fields->post_title = $title;
		$fields->post_date  = '2026-10-22 12:00:00';

		return new WP_Post( $fields );
	}
}
