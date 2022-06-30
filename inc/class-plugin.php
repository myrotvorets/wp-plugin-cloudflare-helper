<?php

namespace Myrotvorets\WordPress\CloudflareHelper;

use WildWolf\Utils\Singleton;

class Plugin {
	use Singleton;

	/**
	 * @codeCoverageIgnore
	 */
	private function __construct() {
		if ( defined( 'CLOUDFLARE_DOMAIN' ) && constant( 'CLOUDFLARE_DOMAIN' ) ) {
			add_action( 'init', [ $this, 'init' ] );
		}
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function init(): void {
		if ( is_admin() ) {
			add_action( 'admin_init', [ $this, 'admin_init' ] );
		}

		add_filter( 'cloudflare_purge_by_url', [ $this, 'cloudflare_purge_by_url' ] );
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function admin_init(): void {
		// phpcs:ignore WordPressVIPMinimum.Hooks.RestrictedHooks.http_request_args -- false positive
		add_filter( 'http_request_args', [ $this, 'http_request_args' ], 10, 2 );
	}

	/**
	 * @psalm-param mixed[] $args
	 */
	public function http_request_args( array $args, string $url ): array {
		assert( defined( 'CLOUDFLARE_DOMAIN' ) );

		/** @var mixed */
		$method = $args['method'] ?? '';
		/** @var mixed */
		$body = $args['body'] ?? '';

		if ( 'PATCH' === $method && is_string( $body ) && ! empty( $body ) && preg_match( '!^https://api.cloudflare.com/client/v4/zones/[0-9a-f]{32}/settings/automatic_platform_optimization!', $url ) ) {
			/** @var mixed */
			$body = json_decode( $body, true );
			if ( is_array( $body ) && ! empty( $body['value']['enabled'] ) && isset( $body['value']['hostnames'] ) && is_array( $body['value']['hostnames'] ) ) {
				$domain = (string) constant( 'CLOUDFLARE_DOMAIN' );
				if ( ! in_array( $domain, $body['value']['hostnames'], true ) ) {
					$body['value']['hostnames'][] = $domain;
				}

				$args['body'] = wp_json_encode( $body );
			}
		}

		return $args;
	}

	/**
	 * @param string[] $urls
	 * @return string[]
	 */
	public function cloudflare_purge_by_url( array $urls ): array {
		assert( defined( 'CLOUDFLARE_DOMAIN' ) );

		$domain = (string) constant( 'CLOUDFLARE_DOMAIN' );
		foreach ( $urls as &$url ) {
			$url = preg_replace( '!^(https?:)//([^/]+)!', '\\1//' . $domain, $url );
		}

		unset( $url );
		return $urls;
	}
}
