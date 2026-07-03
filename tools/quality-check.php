<?php
/**
 * Local quality checks that do not require Composer dependencies.
 *
 * @package StudioBookingManager
 */

declare(strict_types=1);

$root      = dirname( __DIR__ );
$lint_only = in_array( '--lint-only', $argv, true );
$failures  = 0;

/**
 * Print a section label.
 *
 * @param string $label Section label.
 */
function sbm_quality_section( string $label ): void {
	fwrite( STDOUT, "\n== " . $label . " ==\n" );
}

/**
 * Run a command and report failure.
 *
 * @param array<int,string> $command Command parts.
 * @return int
 */
function sbm_quality_run( array $command ): int {
	$escaped = array_map( 'escapeshellarg', $command );
	$cmd     = implode( ' ', $escaped );

	passthru( $cmd, $status );

	return (int) $status;
}

/**
 * Recursively collect files by extension.
 *
 * @param string $root Root directory.
 * @param string $extension File extension.
 * @return array<int,string>
 */
function sbm_quality_files( string $root, string $extension ): array {
	$files    = array();
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
	);

	foreach ( $iterator as $file ) {
		if ( ! $file instanceof SplFileInfo || ! $file->isFile() ) {
			continue;
		}

		if ( $extension === $file->getExtension() ) {
			$files[] = $file->getPathname();
		}
	}

	sort( $files );

	return $files;
}

sbm_quality_section( 'PHP syntax' );

$php_files = array_merge(
	array(
		$root . '/studio-booking-manager.php',
		$root . '/uninstall.php',
		$root . '/tools/run-tests.php',
	),
	sbm_quality_files( $root . '/src', 'php' ),
	sbm_quality_files( $root . '/tests', 'php' )
);

foreach ( $php_files as $file ) {
	$status = sbm_quality_run( array( PHP_BINARY, '-l', $file ) );

	if ( 0 !== $status ) {
		++$failures;
	}
}

if ( ! $lint_only ) {
	sbm_quality_section( 'Lightweight tests' );

	if ( 0 !== sbm_quality_run( array( PHP_BINARY, $root . '/tools/run-tests.php' ) ) ) {
		++$failures;
	}

	sbm_quality_section( 'Release packaging' );

	$required_ignores = array( '.git', '.github', 'tests', 'tools', 'vendor', 'node_modules', 'phpunit.xml.dist', 'phpcs.xml.dist' );
	$distignore       = file_exists( $root . '/.distignore' ) ? file( $root . '/.distignore', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) : array();
	$distignore       = is_array( $distignore ) ? $distignore : array();

	foreach ( $required_ignores as $entry ) {
		if ( ! in_array( $entry, $distignore, true ) ) {
			++$failures;
			fwrite( STDERR, '.distignore is missing ' . $entry . "\n" );
		}
	}

	if ( file_exists( $root . '/vendor' ) ) {
		++$failures;
		fwrite( STDERR, "vendor/ exists locally. Remove it before packaging a release zip.\n" );
	}
}

if ( $failures > 0 ) {
	fwrite( STDERR, "\nQuality checks failed with " . $failures . " issue(s).\n" );
	exit( 1 );
}

fwrite( STDOUT, "\nQuality checks passed.\n" );
