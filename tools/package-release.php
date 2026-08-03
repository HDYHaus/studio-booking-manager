<?php
/**
 * Build an installable release ZIP using .distignore.
 *
 * @package StudioBookingManager
 */

declare(strict_types=1);

$root        = dirname( __DIR__ );
$plugin_file = $root . '/studio-booking-manager.php';
$plugin_slug = basename( $root );
$output_dir  = $root . '/build';

if ( ! class_exists( 'ZipArchive' ) ) {
	fwrite( STDERR, "The PHP zip extension is required to build a release package.\n" );
	exit( 1 );
}

if ( ! file_exists( $plugin_file ) ) {
	fwrite( STDERR, "Plugin file not found.\n" );
	exit( 1 );
}

$plugin_source = file_get_contents( $plugin_file );
$version       = '';

if ( is_string( $plugin_source ) && preg_match( '/^[ \t*]*Version:\s*([^\r\n]+)/mi', $plugin_source, $matches ) ) {
	$version = trim( $matches[1] );
}

if ( '' === $version ) {
	fwrite( STDERR, "Unable to detect plugin version.\n" );
	exit( 1 );
}

$distignore = sbm_package_distignore( $root . '/.distignore' );
$zip_path   = $output_dir . '/' . $plugin_slug . '-' . $version . '.zip';

if ( ! is_dir( $output_dir ) && ! mkdir( $output_dir, 0775, true ) ) {
	fwrite( STDERR, "Unable to create build directory.\n" );
	exit( 1 );
}

$zip = new ZipArchive();

if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
	fwrite( STDERR, "Unable to open release ZIP for writing.\n" );
	exit( 1 );
}

$files = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
	RecursiveIteratorIterator::SELF_FIRST
);

foreach ( $files as $file ) {
	if ( ! $file instanceof SplFileInfo ) {
		continue;
	}

	$path     = $file->getPathname();
	$relative = ltrim( str_replace( $root, '', $path ), DIRECTORY_SEPARATOR );
	$relative = str_replace( DIRECTORY_SEPARATOR, '/', $relative );

	if ( '' === $relative || sbm_package_is_ignored( $relative, $distignore ) ) {
		continue;
	}

	$zip_name = $plugin_slug . '/' . $relative;

	if ( $file->isDir() ) {
		$zip->addEmptyDir( $zip_name );
		continue;
	}

	if ( $file->isFile() ) {
		$zip->addFile( $path, $zip_name );
	}
}

$zip->close();

fwrite( STDOUT, $zip_path . "\n" );

/**
 * Read .distignore entries.
 *
 * @param string $path File path.
 * @return array<int,string>
 */
function sbm_package_distignore( string $path ): array {
	$lines = file_exists( $path ) ? file( $path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) : array();
	$lines = is_array( $lines ) ? $lines : array();

	return array_values(
		array_filter(
			array_map(
				static function ( string $line ): string {
					return trim( $line );
				},
				$lines
			),
			static function ( string $line ): bool {
				return '' !== $line && '#' !== $line[0];
			}
		)
	);
}

/**
 * Determine whether a relative path is ignored.
 *
 * @param string            $relative Relative path.
 * @param array<int,string> $patterns Ignore patterns.
 * @return bool
 */
function sbm_package_is_ignored( string $relative, array $patterns ): bool {
	foreach ( $patterns as $pattern ) {
		$pattern = trim( $pattern, '/' );

		if ( '' === $pattern ) {
			continue;
		}

		if ( fnmatch( $pattern, $relative, FNM_PATHNAME ) || fnmatch( $pattern, basename( $relative ) ) ) {
			return true;
		}

		if ( str_starts_with( $relative, $pattern . '/' ) ) {
			return true;
		}
	}

	return false;
}
