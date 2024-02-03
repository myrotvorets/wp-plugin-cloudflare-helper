<?php

namespace Myrotvorets\WordPress\CloudflareHelper;

use WP_Error;
use WildWolf\Utils\Singleton;

/**
 * @psalm-import-type RequestArgsArray from Plugin
 * @psalm-import-type ResponseArray from Plugin
 */
final class HttpCache {
	use Singleton;

	private const CF_API_URL_PREFIX   = 'https://api.cloudflare.com/client/v4/zones/';
	private const RE_ALWAYS_USE_HTTPS = '!^https://api.cloudflare.com/client/v4/zones/([0-9a-f]{32})/settings/always_use_https!';
	private const RE_PAGERULES        = '!^https://api.cloudflare.com/client/v4/zones/([0-9a-f]{32})/pagerules\?status=([a-z]+)!';

	private const CACHE_GROUP = 'cloudflare-helper';

	public const CACHE_TTL_ALWAYS_USE_HTTPS = 3600;
	public const CACHE_TTL_PAGERULES        = 600;

	/**
	 * @codeCoverageIgnore
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function init(): void {
		add_filter( 'pre_http_request', [ $this, 'pre_http_request' ], 10, 3 );
		add_filter( 'http_response', [ $this, 'http_response' ], 10, 3 );
	}

	/**
	 * @param false|array|WP_Error $response
	 * @param array $args
	 * @param string $url
	 * @return false|array|WP_Error
	 * @codeCoverageIgnore
	 * @psalm-param false|WP_Error|ResponseArray $response
	 * @psalm-param RequestArgsArray $args
	 * @psalm-return false|WP_Error|ResponseArray
	 */
	public function pre_http_request( $response, $args, $url ) {
		$method = $args['method'] ?? '';
		if ( ! is_wp_error( $response ) && 'GET' === $method && str_starts_with( $url, self::CF_API_URL_PREFIX ) ) {
			$result = $this->get_cache_key_and_ttl( $url );
			if ( $result ) {
				/** @var mixed */
				$cached = wp_cache_get( $result[0], self::CACHE_GROUP );
				if ( is_array( $cached ) ) {
					/** @psalm-var ResponseArray */
					$response = $cached;
				}
			}
		}

		return $response;
	}

	/**
	 * @param array  $response    HTTP response.
	 * @param array  $parsed_args HTTP request arguments.
	 * @param string $url         The request URL.
	 * @return array
	 * @psalm-param ResponseArray $response
	 * @psalm-param RequestArgsArray $parsed_args
	 * @psalm-return ResponseArray
	 */
	public function http_response( $response, $parsed_args, $url ) {
		if ( 200 !== $response['response']['code'] || ! str_starts_with( $url, self::CF_API_URL_PREFIX ) ) {
			return $response;
		}

		$method = $parsed_args['method'] ?? '';
		if ( 'GET' === $method ) {
			$result = $this->get_cache_key_and_ttl( $url );
			if ( $result ) {
				list( $key, $ttl ) = $result;
				// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
				wp_cache_set( $key, $response, self::CACHE_GROUP, $ttl );
			}
		}

		return $response;
	}

	/**
	 * @psalm-return list{non-empty-string, positive-int}|null
	 */
	private function get_cache_key_and_ttl( string $url ): ?array {
		$matches = [];
		if ( preg_match( self::RE_ALWAYS_USE_HTTPS, $url, $matches ) ) {
			return [ self::get_always_use_https_key( $matches[1] ), self::CACHE_TTL_ALWAYS_USE_HTTPS ];
		}

		if ( preg_match( self::RE_PAGERULES, $url, $matches ) ) {
			return [ self::get_pagerules_key( $matches[1], $matches[2] ), self::CACHE_TTL_PAGERULES ];
		}

		return null;
	}

	/**
	 * @psalm-return non-empty-string
	 */
	public static function get_always_use_https_key( string $zone ): string {
		return sprintf( 'cloudflare:settings:%s:always_use_https', $zone );
	}

	/**
	 * @psalm-return non-empty-string
	 */
	public static function get_pagerules_key( string $zone, string $status ): string {
		return sprintf( 'cloudflare:pagerules:%s:%s', $zone, $status );
	}
}
