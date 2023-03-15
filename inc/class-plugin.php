<?php
// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase

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
		$this->patch_cloudflare_hooks();
	}

	private function patch_cloudflare_hooks(): void {
		global $cloudflareHooks;
		if ( isset( $cloudflareHooks ) && is_object( $cloudflareHooks ) ) {
			remove_action( 'transition_post_status', [ $cloudflareHooks, 'purgeCacheOnPostStatusChange' ], PHP_INT_MAX );
			add_action( 'transition_post_status', [ $this, 'transition_post_status' ], PHP_INT_MAX, 3 );
			add_action( 'purge_post_cf_cache', [ $this, 'purge_post_cf_cache' ] );
		}

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

	/**
	 * @param string $new_status
	 * @param string $old_status
	 * @param \WP_Post $post
	 */
	public function transition_post_status( $new_status, $old_status, $post ): void {
		if ( 'publish' === $new_status || 'publish' === $old_status ) {
			wp_schedule_single_event( time() + 1, 'purge_post_cf_cache', [ $post->ID ] );
		}
	}

	/**
	 * @param int $post_id
	 * @psalm-suppress UndefinedDocblockClass
	 */
	public function purge_post_cf_cache( $post_id ): void {
		/** @var \CF\WordPress\Hooks $cloudflareHooks */
		global $cloudflareHooks;
		$cloudflareHooks->purgeCacheByRelevantURLs( $post_id );
	}
}
