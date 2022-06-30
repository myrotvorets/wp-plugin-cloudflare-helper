<?php

use Myrotvorets\Test\Constant_Mocker;
use Myrotvorets\WordPress\CloudflareHelper\Plugin;

class Test_Plugin extends WP_UnitTestCase {
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		Constant_Mocker::define( 'CLOUDFLARE_DOMAIN', 'my-domain.dev' );
	}

	public static function tearDownAfterClass(): void {
		Constant_Mocker::clear();
		parent::tearDownAfterClass();
	}

	/**
	 * @covers Myrotvorets\WordPress\CloudflareHelper\Plugin::cloudflare_purge_by_url
	 */
	public function test_cloudflare_purge_by_url(): void {
		$input = [
			'http://example.com/test-page',
		];

		$expected = [
			'http://my-domain.dev/test-page',
		];

		$actual = Plugin::instance()->cloudflare_purge_by_url( $input );
		self::assertEquals( $expected, $actual );
	}

	/**
	 * @dataProvider data_http_request_args
	 * @covers Myrotvorets\WordPress\CloudflareHelper\Plugin::http_request_args
	 */
	public function test_http_request_args( string $url, string $input_json, string $expected_json ): void {
		/** @var mixed[] */
		$input = json_decode( $input_json, true );
		/** @var mixed[] */
		$expected = json_decode( $expected_json, true );

		/** @var mixed[] */
		$actual = Plugin::instance()->http_request_args( $input, $url );
		self::assertEquals( $expected, $actual );
	}

	/**
	 * @psalm-return iterable<string,array{string,string,string}>
	 */
	public function data_http_request_args(): iterable {
		return [
			'no domain present' => [
				'https://api.cloudflare.com/client/v4/zones/00000000000000000000000000000000/settings/automatic_platform_optimization?action=cloudflare_proxy',
				'{"method":"PATCH","timeout":30,"redirection":5,"httpversion":"1.0","user-agent":"WordPress/6.0; https://example.org","reject_unsafe_urls":false,"blocking":true,"headers":{"Content-Type":"application/json","User-Agent":"wordpress/6.0; cloudflare-wordpress-plugin/4.10.1","X-Auth-Email":"redacted","X-Auth-Key":"redacted"},"cookies":[],"body":"{\"value\":{\"enabled\":true,\"cf\":true,\"wordpress\":true,\"wp_plugin\":true,\"hostnames\":[\"example.org\",\"www.example.org\"],\"cache_by_device_type\":false}}","compress":false,"decompress":true,"sslverify":true,"sslcertificates":"/wp-includes/certificates/ca-bundle.crt","stream":false,"filename":null,"limit_response_size":null,"_redirection":5}',
				'{"method":"PATCH","timeout":30,"redirection":5,"httpversion":"1.0","user-agent":"WordPress/6.0; https://example.org","reject_unsafe_urls":false,"blocking":true,"headers":{"Content-Type":"application/json","User-Agent":"wordpress/6.0; cloudflare-wordpress-plugin/4.10.1","X-Auth-Email":"redacted","X-Auth-Key":"redacted"},"cookies":[],"body":"{\"value\":{\"enabled\":true,\"cf\":true,\"wordpress\":true,\"wp_plugin\":true,\"hostnames\":[\"example.org\",\"www.example.org\",\"my-domain.dev\"],\"cache_by_device_type\":false}}","compress":false,"decompress":true,"sslverify":true,"sslcertificates":"/wp-includes/certificates/ca-bundle.crt","stream":false,"filename":null,"limit_response_size":null,"_redirection":5}',
			],
			'domain present' => [
				'https://api.cloudflare.com/client/v4/zones/00000000000000000000000000000000/settings/automatic_platform_optimization?action=cloudflare_proxy',
				'{"method":"PATCH","timeout":30,"redirection":5,"httpversion":"1.0","user-agent":"WordPress/6.0; https://example.org","reject_unsafe_urls":false,"blocking":true,"headers":{"Content-Type":"application/json","User-Agent":"wordpress/6.0; cloudflare-wordpress-plugin/4.10.1","X-Auth-Email":"redacted","X-Auth-Key":"redacted"},"cookies":[],"body":"{\"value\":{\"enabled\":true,\"cf\":true,\"wordpress\":true,\"wp_plugin\":true,\"hostnames\":[\"example.org\",\"www.example.org\",\"my-domain.dev\"],\"cache_by_device_type\":false}}","compress":false,"decompress":true,"sslverify":true,"sslcertificates":"/wp-includes/certificates/ca-bundle.crt","stream":false,"filename":null,"limit_response_size":null,"_redirection":5}',
				'{"method":"PATCH","timeout":30,"redirection":5,"httpversion":"1.0","user-agent":"WordPress/6.0; https://example.org","reject_unsafe_urls":false,"blocking":true,"headers":{"Content-Type":"application/json","User-Agent":"wordpress/6.0; cloudflare-wordpress-plugin/4.10.1","X-Auth-Email":"redacted","X-Auth-Key":"redacted"},"cookies":[],"body":"{\"value\":{\"enabled\":true,\"cf\":true,\"wordpress\":true,\"wp_plugin\":true,\"hostnames\":[\"example.org\",\"www.example.org\",\"my-domain.dev\"],\"cache_by_device_type\":false}}","compress":false,"decompress":true,"sslverify":true,"sslcertificates":"/wp-includes/certificates/ca-bundle.crt","stream":false,"filename":null,"limit_response_size":null,"_redirection":5}',
			],
			'wrong_url' => [
				'http://google.com/',
				'{"method":"PATCH","timeout":30,"redirection":5,"httpversion":"1.0","user-agent":"WordPress/6.0; https://example.org","reject_unsafe_urls":false,"blocking":true,"headers":{"Content-Type":"application/json","User-Agent":"wordpress/6.0; cloudflare-wordpress-plugin/4.10.1","X-Auth-Email":"redacted","X-Auth-Key":"redacted"},"cookies":[],"body":"","compress":false,"decompress":true,"sslverify":true,"sslcertificates":"/wp-includes/certificates/ca-bundle.crt","stream":false,"filename":null,"limit_response_size":null,"_redirection":5}',
				'{"method":"PATCH","timeout":30,"redirection":5,"httpversion":"1.0","user-agent":"WordPress/6.0; https://example.org","reject_unsafe_urls":false,"blocking":true,"headers":{"Content-Type":"application/json","User-Agent":"wordpress/6.0; cloudflare-wordpress-plugin/4.10.1","X-Auth-Email":"redacted","X-Auth-Key":"redacted"},"cookies":[],"body":"","compress":false,"decompress":true,"sslverify":true,"sslcertificates":"/wp-includes/certificates/ca-bundle.crt","stream":false,"filename":null,"limit_response_size":null,"_redirection":5}',
			],
			'wrong method' => [
				'https://api.cloudflare.com/client/v4/zones/00000000000000000000000000000000/settings/automatic_platform_optimization?action=cloudflare_proxy',
				'{"method":"POST","timeout":30,"redirection":5,"httpversion":"1.0","user-agent":"WordPress/6.0; https://example.org","reject_unsafe_urls":false,"blocking":true,"headers":{"Content-Type":"application/json","User-Agent":"wordpress/6.0; cloudflare-wordpress-plugin/4.10.1","X-Auth-Email":"redacted","X-Auth-Key":"redacted"},"cookies":[],"body":"{\"value\":{\"enabled\":true,\"cf\":true,\"wordpress\":true,\"wp_plugin\":true,\"hostnames\":[\"example.org\",\"www.example.org\"],\"cache_by_device_type\":false}}","compress":false,"decompress":true,"sslverify":true,"sslcertificates":"/wp-includes/certificates/ca-bundle.crt","stream":false,"filename":null,"limit_response_size":null,"_redirection":5}',
				'{"method":"POST","timeout":30,"redirection":5,"httpversion":"1.0","user-agent":"WordPress/6.0; https://example.org","reject_unsafe_urls":false,"blocking":true,"headers":{"Content-Type":"application/json","User-Agent":"wordpress/6.0; cloudflare-wordpress-plugin/4.10.1","X-Auth-Email":"redacted","X-Auth-Key":"redacted"},"cookies":[],"body":"{\"value\":{\"enabled\":true,\"cf\":true,\"wordpress\":true,\"wp_plugin\":true,\"hostnames\":[\"example.org\",\"www.example.org\"],\"cache_by_device_type\":false}}","compress":false,"decompress":true,"sslverify":true,"sslcertificates":"/wp-includes/certificates/ca-bundle.crt","stream":false,"filename":null,"limit_response_size":null,"_redirection":5}',
			],
			'bad body' => [
				'https://api.cloudflare.com/client/v4/zones/00000000000000000000000000000000/settings/automatic_platform_optimization?action=cloudflare_proxy',
				'{"method":"PATCH","timeout":30,"redirection":5,"httpversion":"1.0","user-agent":"WordPress/6.0; https://example.org","reject_unsafe_urls":false,"blocking":true,"headers":{"Content-Type":"application/json","User-Agent":"wordpress/6.0; cloudflare-wordpress-plugin/4.10.1","X-Auth-Email":"redacted","X-Auth-Key":"redacted"},"cookies":[],"body":"","compress":false,"decompress":true,"sslverify":true,"sslcertificates":"/wp-includes/certificates/ca-bundle.crt","stream":false,"filename":null,"limit_response_size":null,"_redirection":5}',
				'{"method":"PATCH","timeout":30,"redirection":5,"httpversion":"1.0","user-agent":"WordPress/6.0; https://example.org","reject_unsafe_urls":false,"blocking":true,"headers":{"Content-Type":"application/json","User-Agent":"wordpress/6.0; cloudflare-wordpress-plugin/4.10.1","X-Auth-Email":"redacted","X-Auth-Key":"redacted"},"cookies":[],"body":"","compress":false,"decompress":true,"sslverify":true,"sslcertificates":"/wp-includes/certificates/ca-bundle.crt","stream":false,"filename":null,"limit_response_size":null,"_redirection":5}',
			]
		];
	}
}
