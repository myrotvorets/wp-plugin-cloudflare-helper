<?php
// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase

namespace Myrotvorets\WordPress\CloudflareHelper;

use CF\Integration\DefaultConfig;
use CF\Integration\DefaultIntegration;
use CF\Integration\DefaultLogger;
use CF\WordPress\DataStore;
use CF\WordPress\WordPressAPI;
use CF\WordPress\WordPressClientAPI;
use WP_Error;
use WP_Post;
use WildWolf\Utils\Singleton;
use WpOrg\Requests\Utility\CaseInsensitiveDictionary;

/**
 * @psalm-type RequestArgsArray = array{method?: string, timeout?: float, redirection?: int, httpversion?: string, user-agent?: string, reject_unsafe_urls?: bool, blocking?: bool, headers?: string|array, cookies?: array, body?: string|array, compress?: bool, decompress?: bool, sslverify?: bool, sslcertificates?: string, stream?: bool, filename?: string, limit_response_size?: int}
 * @psalm-type ResponseArray = array{headers: \WpOrg\Requests\Utility\CaseInsensitiveDictionary, body: string, response: array{code: int, message: string}, cookies: \WP_Http_Cookie[], filename: string|null}
 */
final class Plugin {
	use Singleton;

	/** @var string[] */
	private $urls_to_purge = [];

	/**
	 * @codeCoverageIgnore
	 */
	private function __construct() {
		if ( defined( 'CLOUDFLARE_DOMAIN' ) && constant( 'CLOUDFLARE_DOMAIN' ) ) {
			add_action( 'init', [ $this, 'init' ] );
		}
	}

	/**
	 * @psalm-suppress UndefinedClass
	 * @psalm-suppress MixedReturnStatement
	 * @codeCoverageIgnore
	 */
	public static function get_api_client(): ?WordPressClientAPI {
		if ( defined( 'CLOUDFLARE_PLUGIN_DIR' ) ) {
			static $api_client = null;

			if ( null === $api_client ) {
				$config             = new DefaultConfig( file_get_contents( CLOUDFLARE_PLUGIN_DIR . 'config.json', true ) );
				$logger             = new DefaultLogger( $config->getValue( 'debug' ) );
				$dataStore          = new DataStore( $logger );
				$integrationAPI     = new WordPressAPI( $dataStore );
				$integrationContext = new DefaultIntegration( $config, $integrationAPI, $dataStore, $logger );
				$api_client         = new WordPressClientAPI( $integrationContext );
			}

			return $api_client;
		}

		return null;
	}

	/**
	 * @psalm-suppress UndefinedClass
	 * @codeCoverageIgnore
	 */
	public static function get_zone_id(): ?string {
		if ( defined( 'CLOUDFLARE_DOMAIN' ) && constant( 'CLOUDFLARE_DOMAIN' ) ) {
			$domain = (string) constant( 'CLOUDFLARE_DOMAIN' );

			$client = self::get_api_client();
			if ( $client ) {
				/** @var mixed $value */
				$value = $client->getZoneTag( $domain );
				return is_string( $value ) ? $value : null;
			}
		}

		return null;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function init(): void {
		if ( is_admin() ) {
			add_action( 'admin_init', [ $this, 'admin_init' ] );
		}

		HttpCache::instance();

		add_filter( 'cloudflare_purge_by_url', [ $this, 'cloudflare_purge_by_url' ], 10, 2 );
		add_filter( 'pre_http_request', [ $this, 'pre_http_request' ], 10, 3 );
		add_action( 'shutdown', [ $this, 'shutdown' ] );
		add_action( 'psb4ukr_do_deferred_cf_purge', [ $this, 'psb4ukr_do_deferred_cf_purge' ] );
		add_action( 'psb4ukr_purge_cf_urls', [ $this, 'psb4ukr_purge_cf_urls' ] );
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function admin_init(): void {
		// phpcs:ignore WordPressVIPMinimum.Hooks.RestrictedHooks.http_request_args -- false positive
		add_filter( 'http_request_args', [ $this, 'http_request_args' ], 10, 2 );
	}

	/**
	 * @psalm-param RequestArgsArray $args
	 */
	public function http_request_args( array $args, string $url ): array {
		assert( defined( 'CLOUDFLARE_DOMAIN' ) );

		$method = $args['method'] ?? '';
		$body   = $args['body'] ?? '';

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
	 * @param int $post_id
	 * @return string[]
	 */
	public function cloudflare_purge_by_url( array $urls, $post_id ): array {
		assert( defined( 'CLOUDFLARE_DOMAIN' ) );

		$domain = (string) constant( 'CLOUDFLARE_DOMAIN' );
		foreach ( $urls as &$url ) {
			if ( ! str_starts_with( $url, 'https://cdn.myrotvorets.center' ) ) {
				$url = preg_replace( '!^(https?:)//([^/]+)!', '\\1//' . $domain, $url );
			}
		}

		unset( $url );

		/** @var WP_Post|null */
		$post = get_post( $post_id );
		if ( $post && 'criminal' === $post->post_type && 'trash' === $post->post_status ) {
			$name   = str_ends_with( $post->post_name, '__trashed' ) ? substr( $post->post_name, 0, -strlen( '__trashed' ) ) : $post->post_name;
			$urls[] = "https://myrotvorets.center/criminal/{$name}/";
		}

		return $urls;
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
		$body   = $args['body'] ?? '';

		if ( 'DELETE' === $method && is_string( $body ) && ! empty( $body ) && preg_match( '!^https://api.cloudflare.com/client/v4/zones/[0-9a-f]{32}/purge_cache!', $url ) ) {
			/** @var mixed */
			$body = json_decode( $body, true );
			if ( is_array( $body ) && ! empty( $body['files'] ) && is_array( $body['files'] ) ) {
				/** @psalm-var string[] $body['files'] */
				$this->urls_to_purge = array_merge( $this->urls_to_purge, $body['files'] );
				$response            = [
					'headers'  => new CaseInsensitiveDictionary(),
					'body'     => (string) wp_json_encode( [ 'success' => true ] ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'cookies'  => [],
					'filename' => '',
				];
			}
		}

		return $response;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function shutdown( bool $now = false ): void {
		if ( ! empty( $this->urls_to_purge ) ) {
			$urls                = array_unique( $this->urls_to_purge );
			$this->urls_to_purge = [];

			if ( $now ) {
				do_action( 'psb4ukr_purge_cf_urls', $urls );
			} else {
				wp_schedule_single_event( time() + 1, 'psb4ukr_purge_cf_urls', [ $urls ] );
			}
		}
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function psb4ukr_do_deferred_cf_purge(): void {
		$this->shutdown( true );
	}

	/**
	 * @param string[] $urls
	 * @codeCoverageIgnore
	 * @psalm-suppress UndefinedClass
	 */
	public function psb4ukr_purge_cf_urls( array $urls ): void {
		$client = self::get_api_client();
		$zone   = (string) self::get_zone_id();

		if ( ! $client || ! $zone ) {
			return;
		}

		$has_filter = has_filter( 'pre_http_request', [ $this, 'pre_http_request' ] );
		if ( false !== $has_filter ) {
			remove_filter( 'pre_http_request', [ $this, 'pre_http_request' ], 10 );
		}

		$chunks = array_chunk( $urls, 30 );
		foreach ( $chunks as $chunk ) {
			$client->zonePurgeFiles( $zone, $chunk );
		}

		if ( false !== $has_filter ) {
			add_filter( 'pre_http_request', [ $this, 'pre_http_request' ], 10, 3 );
		}
	}
}
