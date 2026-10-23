<?php

declare( strict_types=1 );

namespace MWPD\BasicScaffold\Tests\Unit;

use Brain\Monkey\Functions;
use MWPD\BasicScaffold\Exception\FailedToFindPost;
use MWPD\BasicScaffold\SampleSubsystem\WordPressMainLoop;
use stdClass;
use WP_Post;

/**
 * @covers \MWPD\BasicScaffold\SampleSubsystem\WordPressMainLoop
 * @covers \MWPD\BasicScaffold\Exception\FailedToFindPost
 */
final class WordPressMainLoopTest extends TestCase {

	/**
	 * The states the loop can be in when the_content runs, and whether each
	 * one is the queried post rendered as the content of the page.
	 *
	 * @return array<string, array{bool, bool, bool, bool, int|false, bool}>
	 */
	public static function loop_states(): array {
		return [
			'queried post in the main loop' => [ true, true, false, false, 42, true ],
			'outside the loop'              => [ false, true, false, false, 42, false ],
			'in a secondary query'          => [ true, false, false, false, 42, false ],
			'in a feed'                     => [ true, true, true, false, 42, false ],
			'building an excerpt'           => [ true, true, false, true, 42, false ],
			'another post in the main loop' => [ true, true, false, false, 7, false ],
			'no post in the loop'           => [ true, true, false, false, false, false ],
		];
	}

	/**
	 * @dataProvider loop_states
	 *
	 * @param int|false $current_id ID of the post the loop is on.
	 */
	public function test_it_tells_whether_the_queried_post_is_rendered(
		bool $in_the_loop,
		bool $is_main_query,
		bool $is_feed,
		bool $building_excerpt,
		$current_id,
		bool $expected
	): void {
		Functions\when( 'in_the_loop' )->justReturn( $in_the_loop );
		Functions\when( 'is_main_query' )->justReturn( $is_main_query );
		Functions\when( 'is_feed' )->justReturn( $is_feed );
		Functions\when( 'doing_filter' )->alias(
			static fn( string $hook ): bool => $hook === 'the_content'
				|| ( $building_excerpt && $hook === 'get_the_excerpt' )
		);
		Functions\when( 'get_the_ID' )->justReturn( $current_id );
		Functions\when( 'get_queried_object_id' )->justReturn( 42 );

		$this->assertSame( $expected, ( new WordPressMainLoop() )->is_rendering_queried_post() );
	}

	public function test_it_returns_the_queried_post(): void {
		$post = new WP_Post( new stdClass() );
		Functions\when( 'get_queried_object' )->justReturn( $post );

		$this->assertSame( $post, ( new WordPressMainLoop() )->queried_post() );
	}

	/**
	 * What the main query asks for when it does not ask for a post.
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function queried_objects_that_are_not_posts(): array {
		// The front page of posts queries nothing, an archive a term.
		return [
			'nothing' => [ null ],
			'a term'  => [ new stdClass() ],
		];
	}

	/**
	 * @dataProvider queried_objects_that_are_not_posts
	 *
	 * @param mixed $queried_object What get_queried_object() returns.
	 */
	public function test_it_fails_when_the_main_query_has_no_post( $queried_object ): void {
		Functions\when( 'get_queried_object' )->justReturn( $queried_object );

		$this->expectException( FailedToFindPost::class );
		$this->expectExceptionMessage( 'The main query did not ask for a post.' );

		( new WordPressMainLoop() )->queried_post();
	}
}
