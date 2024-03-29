<?php

// phpcs:disable Universal.Namespaces.DisallowCurlyBraceSyntax.Forbidden
// phpcs:disable Universal.Namespaces.OneDeclarationPerFile.MultipleFound
// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed

namespace Myrotvorets\Test {
	use InvalidArgumentException;

	abstract class Constant_Mocker {
		private static $constants = [
			'ABSPATH' => '/tmp/wordpress',
		];

		public static function clear(): void {
			self::$constants = [
				'ABSPATH' => '/tmp/wordpress',
			];
		}

		public static function define( string $constant, $value ): void {
			if ( isset( self::$constants[ $constant ] ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- tests/CLI
				throw new InvalidArgumentException( sprintf( 'Constant "%s" is already defined', $constant ) );
			}

			self::$constants[ $constant ] = $value;
		}

		public static function defined( string $constant ): bool {
			return isset( self::$constants[ $constant ] );
		}

		public static function constant( string $constant ) {
			if ( ! isset( self::$constants[ $constant ] ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- tests/CLI
				throw new InvalidArgumentException( sprintf( 'Constant "%s" is not defined', $constant ) );
			}

			return self::$constants[ $constant ];
		}
	}
}

namespace Myrotvorets\WordPress\CloudflareHelper {
	use Myrotvorets\Test\Constant_Mocker;

	function defined( $constant ) {
		return Constant_Mocker::defined( $constant );
	}

	function constant( $constant ) {
		return Constant_Mocker::constant( $constant );
	}
}
