<?php
/**
 * Lightweight local test runner.
 *
 * @package StudioBookingManager
 */

declare(strict_types=1);

$root = dirname( __DIR__ );

require $root . '/tests/bootstrap.php';
require $root . '/tests/unit/Access/TestCase.php';
require $root . '/tests/unit/Commerce/TestCase.php';
require $root . '/tests/unit/Integrations/TestCase.php';

$tests = array(
	$root . '/tests/unit/Access/AccessValidationResultTest.php',
	$root . '/tests/unit/Access/CreditRuleTest.php',
	$root . '/tests/unit/Access/GuestRuleTest.php',
	$root . '/tests/unit/Access/ExpiryRuleTest.php',
	$root . '/tests/unit/Commerce/BookingDateFieldTest.php',
	$root . '/tests/unit/Commerce/CheckoutFieldsTest.php',
	$root . '/tests/unit/Commerce/LoopAddToCartTest.php',
	$root . '/tests/unit/Commerce/ProductPanelTest.php',
	$root . '/tests/unit/Integrations/GravityFormsIntegrationTest.php',
	$root . '/tests/unit/Integrations/IntegrationSettingsTest.php',
);

$failures = 0;

foreach ( $tests as $test_file ) {
	require $test_file;
	$namespace  = sbm_test_namespace( $test_file );
	$class_name = 'StudioBookingManager\\Tests\\Unit\\' . $namespace . '\\' . basename( $test_file, '.php' );
	$test       = new $class_name();

	foreach ( get_class_methods( $test ) as $method ) {
		if ( 0 !== strpos( $method, 'test_' ) ) {
			continue;
		}

		try {
			$test->{$method}();
			fwrite( STDOUT, '.' );
		} catch ( Throwable $throwable ) {
			++$failures;
			fwrite( STDOUT, "F\n" . $class_name . '::' . $method . "\n" . $throwable->getMessage() . "\n" );
		}
	}
}

/**
 * Resolve the namespace segment for a test file.
 *
 * @param string $test_file Test file path.
 * @return string
 */
function sbm_test_namespace( string $test_file ): string {
	if ( false !== strpos( $test_file, '/Commerce/' ) ) {
		return 'Commerce';
	}

	if ( false !== strpos( $test_file, '/Integrations/' ) ) {
		return 'Integrations';
	}

	return 'Access';
}

fwrite( STDOUT, "\n" );

if ( $failures > 0 ) {
	fwrite( STDERR, $failures . " test failure(s).\n" );
	exit( 1 );
}

fwrite( STDOUT, "All lightweight tests passed.\n" );
