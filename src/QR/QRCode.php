<?php
/**
 * Small SVG QR code generator.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\QR;

defined( 'ABSPATH' ) || exit;

/**
 * Generates fixed-version QR codes for admin output.
 */
final class QRCode {
	private const VERSION = 10;
	private const SIZE = 57;
	private const DATA_CODEWORDS = 216;
	private const ECC_CODEWORDS_PER_BLOCK = 26;
	private const DATA_BLOCKS = array( 43, 43, 43, 43, 44 );

	/**
	 * Render SVG.
	 *
	 * @param string $text Text to encode.
	 * @param int    $scale Module scale.
	 * @param int    $margin Quiet zone modules.
	 */
	public static function svg( string $text, int $scale = 6, int $margin = 4 ): string {
		$modules = self::encode( $text );
		$count   = count( $modules );
		$size    = ( $count + ( 2 * $margin ) ) * $scale;
		$paths   = array();

		for ( $y = 0; $y < $count; $y++ ) {
			for ( $x = 0; $x < $count; $x++ ) {
				if ( $modules[ $y ][ $x ] ) {
					$paths[] = 'M' . ( ( $x + $margin ) * $scale ) . ',' . ( ( $y + $margin ) * $scale ) . 'h' . $scale . 'v' . $scale . 'h-' . $scale . 'z';
				}
			}
		}

		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" width="%1$d" height="%1$d" role="img" aria-label="%2$s"><rect width="100%%" height="100%%" fill="#fff"/><path fill="#111" d="%3$s"/></svg>',
			$size,
			esc_attr__( 'QR code', 'studio-booking-manager' ),
			esc_attr( implode( '', $paths ) )
		);
	}

	/**
	 * Encode text to module matrix.
	 *
	 * @param string $text Text.
	 * @return array<int,array<int,bool>>
	 */
	private static function encode( string $text ): array {
		$data = self::data_codewords( $text );
		$codewords = self::interleave_blocks( $data );
		$modules   = array_fill( 0, self::SIZE, array_fill( 0, self::SIZE, false ) );
		$is_func   = array_fill( 0, self::SIZE, array_fill( 0, self::SIZE, false ) );

		self::draw_function_patterns( $modules, $is_func );
		self::draw_codewords( $modules, $is_func, $codewords );
		self::apply_mask( $modules, $is_func, 0 );
		self::draw_format_bits( $modules, $is_func, 0 );
		self::draw_version_bits( $modules, $is_func );

		return $modules;
	}

	/**
	 * Build data codewords.
	 *
	 * @param string $text Text.
	 * @return array<int,int>
	 */
	private static function data_codewords( string $text ): array {
		$bytes = array_values( unpack( 'C*', $text ) ?: array() );
		if ( count( $bytes ) > 213 ) {
			$bytes = array_slice( $bytes, 0, 213 );
		}

		$bits = array( 0, 1, 0, 0 );
		self::append_bits( $bits, count( $bytes ), 16 );
		foreach ( $bytes as $byte ) {
			self::append_bits( $bits, $byte, 8 );
		}

		$capacity = self::DATA_CODEWORDS * 8;
		self::append_bits( $bits, 0, min( 4, $capacity - count( $bits ) ) );
		while ( 0 !== count( $bits ) % 8 ) {
			$bits[] = 0;
		}

		$codewords = array();
		for ( $i = 0; $i < count( $bits ); $i += 8 ) {
			$value = 0;
			for ( $j = 0; $j < 8; $j++ ) {
				$value = ( $value << 1 ) | $bits[ $i + $j ];
			}
			$codewords[] = $value;
		}

		$pad = 0xec;
		while ( count( $codewords ) < self::DATA_CODEWORDS ) {
			$codewords[] = $pad;
			$pad = 0xec === $pad ? 0x11 : 0xec;
		}

		return $codewords;
	}

	/**
	 * Append bits.
	 *
	 * @param array<int,int> $bits Bit buffer.
	 * @param int            $value Value.
	 * @param int            $length Length.
	 */
	private static function append_bits( array &$bits, int $value, int $length ): void {
		for ( $i = $length - 1; $i >= 0; $i-- ) {
			$bits[] = ( $value >> $i ) & 1;
		}
	}

	/**
	 * Interleave data and error correction blocks.
	 *
	 * @param array<int,int> $data Data codewords.
	 * @return array<int,int>
	 */
	private static function interleave_blocks( array $data ): array {
		$blocks = array();
		$offset = 0;
		foreach ( self::DATA_BLOCKS as $length ) {
			$block    = array_slice( $data, $offset, $length );
			$offset  += $length;
			$blocks[] = array(
				'data' => $block,
				'ecc'  => self::reed_solomon_remainder( $block, self::ECC_CODEWORDS_PER_BLOCK ),
			);
		}

		$result = array();
		for ( $i = 0; $i < 44; $i++ ) {
			foreach ( $blocks as $block ) {
				if ( isset( $block['data'][ $i ] ) ) {
					$result[] = $block['data'][ $i ];
				}
			}
		}

		for ( $i = 0; $i < self::ECC_CODEWORDS_PER_BLOCK; $i++ ) {
			foreach ( $blocks as $block ) {
				$result[] = $block['ecc'][ $i ];
			}
		}

		return $result;
	}

	/**
	 * Reed-Solomon remainder.
	 *
	 * @param array<int,int> $data Data.
	 * @param int            $degree Degree.
	 * @return array<int,int>
	 */
	private static function reed_solomon_remainder( array $data, int $degree ): array {
		$generator = self::reed_solomon_generator( $degree );
		$result    = array_fill( 0, $degree, 0 );

		foreach ( $data as $byte ) {
			$factor = $byte ^ $result[0];
			array_shift( $result );
			$result[] = 0;
			for ( $i = 0; $i < $degree; $i++ ) {
				$result[ $i ] ^= self::gf_multiply( $generator[ $i ], $factor );
			}
		}

		return $result;
	}

	/**
	 * Reed-Solomon generator.
	 *
	 * @param int $degree Degree.
	 * @return array<int,int>
	 */
	private static function reed_solomon_generator( int $degree ): array {
		$result = array( 1 );
		for ( $i = 0; $i < $degree; $i++ ) {
			$result[] = 0;
			for ( $j = count( $result ) - 1; $j > 0; $j-- ) {
				$result[ $j ] = self::gf_multiply( $result[ $j ], self::gf_pow( $i ) ) ^ $result[ $j - 1 ];
			}
			$result[0] = self::gf_multiply( $result[0], self::gf_pow( $i ) );
		}

		return array_slice( $result, 1 );
	}

	/**
	 * GF(256) exponent for alpha^power.
	 */
	private static function gf_pow( int $power ): int {
		$result = 1;
		for ( $i = 0; $i < $power; $i++ ) {
			$result <<= 1;
			if ( 0 !== ( $result & 0x100 ) ) {
				$result ^= 0x11d;
			}
		}

		return $result;
	}

	/**
	 * GF(256) multiply.
	 */
	private static function gf_multiply( int $x, int $y ): int {
		$result = 0;
		for ( $i = 7; $i >= 0; $i-- ) {
			$result = ( $result << 1 ) ^ ( ( $result >> 7 ) * 0x11d );
			if ( ( ( $y >> $i ) & 1 ) !== 0 ) {
				$result ^= $x;
			}
		}

		return $result & 0xff;
	}

	/**
	 * Draw function patterns.
	 *
	 * @param array<int,array<int,bool>> $modules Matrix.
	 * @param array<int,array<int,bool>> $is_func Function mask.
	 */
	private static function draw_function_patterns( array &$modules, array &$is_func ): void {
		self::draw_finder( $modules, $is_func, 3, 3 );
		self::draw_finder( $modules, $is_func, self::SIZE - 4, 3 );
		self::draw_finder( $modules, $is_func, 3, self::SIZE - 4 );

		for ( $i = 0; $i < self::SIZE; $i++ ) {
			if ( ! $is_func[ $i ][6] ) {
				self::set_function( $modules, $is_func, 6, $i, 0 === $i % 2 );
			}
			if ( ! $is_func[6][ $i ] ) {
				self::set_function( $modules, $is_func, $i, 6, 0 === $i % 2 );
			}
		}

		foreach ( array( 6, 28, 50 ) as $x ) {
			foreach ( array( 6, 28, 50 ) as $y ) {
				if ( ( 6 === $x && 6 === $y ) || ( 6 === $x && 50 === $y ) || ( 50 === $x && 6 === $y ) ) {
					continue;
				}
				self::draw_alignment( $modules, $is_func, $x, $y );
			}
		}

		self::set_function( $modules, $is_func, 8, self::SIZE - 8, true );

		for ( $i = 0; $i < 9; $i++ ) {
			self::reserve( $is_func, 8, $i );
			self::reserve( $is_func, $i, 8 );
			self::reserve( $is_func, self::SIZE - 1 - $i, 8 );
			self::reserve( $is_func, 8, self::SIZE - 1 - $i );
		}

		for ( $i = 0; $i < 6; $i++ ) {
			self::reserve( $is_func, self::SIZE - 11 + $i, 0 );
			self::reserve( $is_func, self::SIZE - 11 + $i, 1 );
			self::reserve( $is_func, self::SIZE - 11 + $i, 2 );
			self::reserve( $is_func, 0, self::SIZE - 11 + $i );
			self::reserve( $is_func, 1, self::SIZE - 11 + $i );
			self::reserve( $is_func, 2, self::SIZE - 11 + $i );
		}
	}

	/**
	 * Draw finder.
	 */
	private static function draw_finder( array &$modules, array &$is_func, int $cx, int $cy ): void {
		for ( $dy = -4; $dy <= 4; $dy++ ) {
			for ( $dx = -4; $dx <= 4; $dx++ ) {
				$x = $cx + $dx;
				$y = $cy + $dy;
				if ( $x < 0 || $x >= self::SIZE || $y < 0 || $y >= self::SIZE ) {
					continue;
				}
				$dark = max( abs( $dx ), abs( $dy ) ) !== 2 && max( abs( $dx ), abs( $dy ) ) !== 4;
				self::set_function( $modules, $is_func, $x, $y, $dark );
			}
		}
	}

	/**
	 * Draw alignment.
	 */
	private static function draw_alignment( array &$modules, array &$is_func, int $cx, int $cy ): void {
		for ( $dy = -2; $dy <= 2; $dy++ ) {
			for ( $dx = -2; $dx <= 2; $dx++ ) {
				self::set_function( $modules, $is_func, $cx + $dx, $cy + $dy, max( abs( $dx ), abs( $dy ) ) !== 1 );
			}
		}
	}

	/**
	 * Draw data codewords.
	 *
	 * @param array<int,array<int,bool>> $modules Matrix.
	 * @param array<int,array<int,bool>> $is_func Function mask.
	 * @param array<int,int>             $codewords Codewords.
	 */
	private static function draw_codewords( array &$modules, array $is_func, array $codewords ): void {
		$bits = array();
		foreach ( $codewords as $byte ) {
			self::append_bits( $bits, $byte, 8 );
		}

		$i = 0;
		for ( $right = self::SIZE - 1; $right >= 1; $right -= 2 ) {
			if ( 6 === $right ) {
				$right--;
			}
			for ( $vert = 0; $vert < self::SIZE; $vert++ ) {
				for ( $j = 0; $j < 2; $j++ ) {
					$x      = $right - $j;
					$upward = ( ( $right + 1 ) & 2 ) === 0;
					$y      = $upward ? self::SIZE - 1 - $vert : $vert;
					if ( ! $is_func[ $y ][ $x ] && $i < count( $bits ) ) {
						$modules[ $y ][ $x ] = 1 === $bits[ $i ];
						$i++;
					}
				}
			}
		}
	}

	/**
	 * Apply mask.
	 */
	private static function apply_mask( array &$modules, array $is_func, int $mask ): void {
		for ( $y = 0; $y < self::SIZE; $y++ ) {
			for ( $x = 0; $x < self::SIZE; $x++ ) {
				if ( ! $is_func[ $y ][ $x ] && self::mask_bit( $mask, $x, $y ) ) {
					$modules[ $y ][ $x ] = ! $modules[ $y ][ $x ];
				}
			}
		}
	}

	/**
	 * Mask bit.
	 */
	private static function mask_bit( int $mask, int $x, int $y ): bool {
		return 0 === ( ( $x + $y ) % 2 );
	}

	/**
	 * Draw format bits.
	 */
	private static function draw_format_bits( array &$modules, array &$is_func, int $mask ): void {
		$bits = self::format_bits( $mask );
		for ( $i = 0; $i <= 5; $i++ ) {
			self::set_function( $modules, $is_func, 8, $i, 1 === ( ( $bits >> $i ) & 1 ) );
		}
		self::set_function( $modules, $is_func, 8, 7, 1 === ( ( $bits >> 6 ) & 1 ) );
		self::set_function( $modules, $is_func, 8, 8, 1 === ( ( $bits >> 7 ) & 1 ) );
		self::set_function( $modules, $is_func, 7, 8, 1 === ( ( $bits >> 8 ) & 1 ) );
		for ( $i = 9; $i < 15; $i++ ) {
			self::set_function( $modules, $is_func, 14 - $i, 8, 1 === ( ( $bits >> $i ) & 1 ) );
		}
		for ( $i = 0; $i < 8; $i++ ) {
			self::set_function( $modules, $is_func, self::SIZE - 1 - $i, 8, 1 === ( ( $bits >> $i ) & 1 ) );
		}
		for ( $i = 8; $i < 15; $i++ ) {
			self::set_function( $modules, $is_func, 8, self::SIZE - 15 + $i, 1 === ( ( $bits >> $i ) & 1 ) );
		}
	}

	/**
	 * Format bits for EC level M and given mask.
	 */
	private static function format_bits( int $mask ): int {
		$data = $mask;
		$rem  = $data;
		for ( $i = 0; $i < 10; $i++ ) {
			$rem = ( $rem << 1 ) ^ ( ( ( $rem >> 9 ) & 1 ) * 0x537 );
		}

		return ( ( $data << 10 ) | $rem ) ^ 0x5412;
	}

	/**
	 * Draw version information.
	 */
	private static function draw_version_bits( array &$modules, array &$is_func ): void {
		$rem = self::VERSION;
		for ( $i = 0; $i < 12; $i++ ) {
			$rem = ( $rem << 1 ) ^ ( ( ( $rem >> 11 ) & 1 ) * 0x1f25 );
		}
		$bits = ( self::VERSION << 12 ) | $rem;
		for ( $i = 0; $i < 18; $i++ ) {
			$bit = 1 === ( ( $bits >> $i ) & 1 );
			$x   = self::SIZE - 11 + ( $i % 3 );
			$y   = (int) floor( $i / 3 );
			self::set_function( $modules, $is_func, $x, $y, $bit );
			self::set_function( $modules, $is_func, $y, $x, $bit );
		}
	}

	/**
	 * Set function module.
	 */
	private static function set_function( array &$modules, array &$is_func, int $x, int $y, bool $dark ): void {
		if ( $x < 0 || $x >= self::SIZE || $y < 0 || $y >= self::SIZE ) {
			return;
		}
		$modules[ $y ][ $x ] = $dark;
		$is_func[ $y ][ $x ] = true;
	}

	/**
	 * Reserve function module.
	 */
	private static function reserve( array &$is_func, int $x, int $y ): void {
		if ( $x >= 0 && $x < self::SIZE && $y >= 0 && $y < self::SIZE ) {
			$is_func[ $y ][ $x ] = true;
		}
	}
}
