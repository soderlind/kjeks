<?php
/**
 * PageSampler tests.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Soderlind\Kjeks\Scan\PageSampler;

it( 'flags embed and inline-script signals in content', function (): void {
	expect( PageSampler::has_embed_signal( '<p><iframe src="https://x"></iframe></p>' ) )->toBeTrue()
		->and( PageSampler::has_embed_signal( 'Watch https://youtu.be/abc123' ) )->toBeTrue()
		->and( PageSampler::has_embed_signal( '<!-- wp:core-embed/youtube -->' ) )->toBeTrue()
		->and( PageSampler::has_embed_signal( '<script>ga()</script>' ) )->toBeTrue()
		->and( PageSampler::has_embed_signal( 'Just plain prose, nothing embedded.' ) )->toBeFalse()
		->and( PageSampler::has_embed_signal( '' ) )->toBeFalse();
} );

it( 'relativizes urls for a root install', function (): void {
	expect( PageSampler::relative_path( 'https://ex.com/', 'https://ex.com/about/' ) )->toBe( '/about/' )
		->and( PageSampler::relative_path( 'https://ex.com/', 'https://ex.com/' ) )->toBe( '/' );
} );

it( 'relativizes urls for a subdirectory multisite', function (): void {
	expect( PageSampler::relative_path( 'https://ex.com/sub/', 'https://ex.com/sub/hello/' ) )->toBe( '/hello/' )
		->and( PageSampler::relative_path( 'https://ex.com/sub/', 'https://ex.com/sub/' ) )->toBe( '/' );
} );

describe( 'paths()', function (): void {
	beforeEach(
		function (): void {
			Functions\when( 'home_url' )->justReturn( 'https://ex.com/' );
			Functions\when( 'get_post_types' )->justReturn( array( 'post' => 'post', 'page' => 'page' ) );
			Functions\when( 'get_permalink' )->alias(
				static function ( $post ): string {
					$id = is_object( $post ) ? (int) $post->ID : (int) $post;
					return 'https://ex.com/p/' . $id . '/';
				}
			);
			Functions\when( 'get_posts' )->alias(
				static function ( array $args ): array {
					if ( 'ids' === ( $args['fields'] ?? '' ) ) {
						return array( 'post' === $args['post_type'] ? 11 : 22 );
					}
					return array(
						(object) array( 'ID' => 11, 'post_content' => 'plain prose' ),
						(object) array( 'ID' => 101, 'post_content' => '<iframe src="https://youtube.com/x"></iframe>' ),
						(object) array( 'ID' => 102, 'post_content' => 'hello world' ),
					);
				}
			);
		}
	);

	it( 'returns the template set, embed-flagged pages, then fill, deduped', function (): void {
		$paths = ( new PageSampler( 10 ) )->paths();

		expect( $paths )->toBe(
			array( '/', '/p/11/', '/p/22/', '/p/101/', '/p/102/' )
		);
	} );

	it( 'honors the cap', function (): void {
		$paths = ( new PageSampler( 2 ) )->paths();

		expect( $paths )->toBe( array( '/', '/p/11/' ) );
	} );
} );
