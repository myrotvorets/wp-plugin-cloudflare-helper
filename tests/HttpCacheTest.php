<?php

use Myrotvorets\WordPress\CloudflareHelper\HttpCache;
use PHPUnit\Framework\ExpectationFailedException;
use WpOrg\Requests\Utility\CaseInsensitiveDictionary;

class HttpCacheTest extends WP_UnitTestCase {
	/**
	 * @dataProvider data_requests
	 * @covers Myrotvorets\WordPress\CloudflareHelper\HttpCache::pre_http_request
	 * @covers Myrotvorets\WordPress\CloudflareHelper\HttpCache::http_response
	 * @covers Myrotvorets\WordPress\CloudflareHelper\HttpCache::get_cache_key_and_ttl
	 * @uses Myrotvorets\WordPress\CloudflareHelper\HttpCache::get_always_use_https_key
	 * @uses Myrotvorets\WordPress\CloudflareHelper\HttpCache::get_pagerules_key
	 */
	public function test_requests( string $method, string $url, array $mocked_response, bool $cached ): void {
		$cache = HttpCache::instance();
		$cache->init();

		$request = 1;

		$request_args = [];
		$request_url  = '';

		add_filter( 'pre_http_request', static function ( $response, $args, $url ) use ( $cached, $mocked_response, &$request, &$request_args, &$request_url ) {
			self::assertEquals( $cached && 2 === $request, is_array( $response ) );
			$request_args = $args;
			$request_url  = $url;

			$result = 1 === $request ? $mocked_response : $response;
			++$request;
			return $result;
		}, 15, 3 );

		// This request populates the cache
		$response = wp_remote_request( $url, [ 'method' => $method ] );
		// We need to call this manually because we short-circuit the HTTP request; in this case, WordPress does not run the `http_response` filter
		$cache->http_response( $response, $request_args, $request_url );
		// This request returns the cached response
		wp_remote_request( $url, [ 'method' => $method ] );
	}

	public function data_requests(): iterable {
		static $response_200 = [
			'headers'  => new CaseInsensitiveDictionary(),
			'body'     => 'OK',
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'cookies'  => [],
			'filename' => null,
		];

		static $response_400 = [
			'headers'  => new CaseInsensitiveDictionary(),
			'body'     => 'Bad Request',
			'response' => [
				'code'    => 400,
				'message' => 'Bad Request',
			],
			'cookies'  => [],
			'filename' => null,
		];

		return [
			'irrelevant url'      => [
				'GET',
				'https://example.com/',
				$response_200,
				false,
			],
			'irrelevant method'   => [
				'DELETE',
				'https://api.cloudflare.com/client/v4/zones/',
				$response_200,
				false,
			],
			'wrong response code' => [
				'GET',
				'https://api.cloudflare.com/client/v4/zones/00000000000000000000000000000000/settings/always_use_https',
				$response_400,
				false,
			],
			'always_use_https'    => [
				'GET',
				'https://api.cloudflare.com/client/v4/zones/00000000000000000000000000000000/settings/always_use_https',
				$response_200,
				true,
			],
			'page rules'          => [
				'GET',
				'https://api.cloudflare.com/client/v4/zones/00000000000000000000000000000000/pagerules?status=active',
				$response_200,
				true,
			],
		];
	}

	/**
	 * @covers Myrotvorets\WordPress\CloudflareHelper\HttpCache::get_always_use_https_key
	 */
	public function test_get_always_use_https_key(): void {
		$zone     = '00000000000000000000000000000000';
		$expected = "cloudflare:settings:{$zone}:always_use_https";
		$actual   = HttpCache::get_always_use_https_key( $zone );

		self::assertEquals( $expected, $actual );
	}

	/**
	 * @covers Myrotvorets\WordPress\CloudflareHelper\HttpCache::get_pagerules_key
	 */
	public function test_get_pagerules_key(): void {
		$zone     = '00000000000000000000000000000000';
		$status   = 'active';
		$expected = "cloudflare:pagerules:{$zone}:{$status}";
		$actual   = HttpCache::get_pagerules_key( $zone, $status );

		self::assertEquals( $expected, $actual );
	}
}
