<?php

namespace tubalmartin\CssMin\Tests;

use PHPUnit\Framework\TestCase;
use tubalmartin\CssMin\Utils;

require_once dirname( __DIR__, 3 ) . '/assets/admin/CssMin/cssmin.php';

class UtilsTest extends TestCase {

	/**
	 * @covers \tubalmartin\CssMin\Utils::clampNumber()
	 * @dataProvider clamp_number__data
	 */
	public function test__clamp_number_limits_value_to_range( $number, $min, $max, $expected ): void {
		$this->assertSame( $expected, Utils::clampNumber( $number, $min, $max ) );
	}

	public function clamp_number__data(): array {
		return [
			'below minimum' => [ -2, 0, 10, 0 ],
			'inside range'  => [ 4.5, 0, 10, 4.5 ],
			'above maximum' => [ 12, 0, 10, 10 ],
		];
	}

	/**
	 * @covers \tubalmartin\CssMin\Utils::clampNumberSrgb()
	 * @dataProvider srgb_number__data
	 */
	public function test__clamp_number_srgb_limits_value_to_color_channel( $number, $expected ): void {
		$this->assertSame( $expected, Utils::clampNumberSrgb( $number ) );
	}

	public function srgb_number__data(): array {
		return [
			'below color space'  => [ -1, 0 ],
			'inside color space' => [ 127.5, 127.5 ],
			'above color space'  => [ 256, 255 ],
		];
	}

	/**
	 * @covers \tubalmartin\CssMin\Utils::normalizeInt()
	 * @dataProvider normalized_integer__data
	 */
	public function test__normalize_int_converts_php_size_values( $value, int $expected ): void {
		$this->assertSame( $expected, Utils::normalizeInt( $value ) );
	}

	public function normalized_integer__data(): array {
		return [
			'integer'   => [ 42, 42 ],
			'numeric'   => [ '30', 30 ],
			'kilobytes' => [ '2K', 2048 ],
			'megabytes' => [ '2m', 2097152 ],
			'gigabytes' => [ '2G', 2147483648 ],
			'unlimited' => [ '-1', -1 ],
		];
	}

	/**
	 * @covers \tubalmartin\CssMin\Utils::hslToRgb()
	 * @dataProvider hsl_to_rgb__data
	 */
	public function test__hsl_to_rgb_converts_and_normalizes_color_values( array $hsl, array $expected ): void {
		$this->assertSame( $expected, Utils::hslToRgb( $hsl ) );
	}

	public function hsl_to_rgb__data(): array {
		return [
			'red'                => [ [ 0, '100%', '50%' ], [ 255, 0, 0 ] ],
			'green'              => [ [ 120, '100%', '50%' ], [ 0, 255, 0 ] ],
			'gray'               => [ [ 0, '0%', '50%' ], [ 128, 128, 128 ] ],
			'wrapped hue'        => [ [ -240, '100%', '50%' ], [ 0, 255, 0 ] ],
			'clamped saturation' => [ [ 240, '200%', '50%' ], [ 0, 0, 255 ] ],
		];
	}

	/**
	 * @covers \tubalmartin\CssMin\Utils::hueToRgb()
	 * @dataProvider hue_to_rgb__data
	 */
	public function test__hue_to_rgb_selects_channel_value( float $hue, float $expected ): void {
		$this->assertEqualsWithDelta( $expected, Utils::hueToRgb( 0.2, 0.8, $hue ), 0.00001 );
	}

	public function hue_to_rgb__data(): array {
		return [
			'wraps below zero' => [ -0.1, 0.2 ],
			'wraps above one' => [ 1.1, 0.56 ],
			'first segment' => [ 0.1, 0.56 ],
			'second segment' => [ 0.25, 0.8 ],
			'third segment' => [ 0.5, 0.8 ],
			'last segment' => [ 0.8, 0.2 ],
		];
	}

	/**
	 * @covers \tubalmartin\CssMin\Utils::rgbPercentageToRgbInteger()
	 * @dataProvider rgb_percentage__data
	 */
	public function test__rgb_percentage_to_rgb_integer_normalizes_channel( $channel, int $expected ): void {
		$this->assertSame( $expected, Utils::rgbPercentageToRgbInteger( $channel ) );
	}

	public function rgb_percentage__data(): array {
		return [
			'percentage' => [ '50%', 128 ],
			'integer string' => [ '127', 127 ],
			'decimal channel' => [ 127.9, 127 ],
		];
	}

	/**
	 * @covers \tubalmartin\CssMin\Utils::rgbToHex()
	 * @dataProvider rgb_to_hex__data
	 */
	public function test__rgb_to_hex_converts_percentages_and_clips_channels( array $rgb, array $expected ): void {
		$this->assertSame( $expected, Utils::rgbToHex( $rgb ) );
	}

	public function rgb_to_hex__data(): array {
		return [
			'integers'   => [ [ 51, 102, 153 ], [ '33', '66', '99' ] ],
			'percentages' => [ [ '100%', '50%', '0%' ], [ 'ff', '80', '00' ] ],
			'clipped'     => [ [ -10, 255, 300 ], [ '00', 'ff', 'ff' ] ],
		];
	}

	/**
	 * @covers \tubalmartin\CssMin\Utils::roundNumber()
	 * @dataProvider rounded_number__data
	 */
	public function test__round_number_uses_nearest_integer( $number, int $expected ): void {
		$this->assertSame( $expected, Utils::roundNumber( $number ) );
	}

	public function rounded_number__data(): array {
		return [
			'round down' => [ 1.4, 1 ],
			'round up'   => [ '1.5', 2 ],
			'negative'   => [ -1.5, -2 ],
		];
	}
}
