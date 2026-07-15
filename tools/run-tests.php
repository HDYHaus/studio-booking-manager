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

$tests = array(
	$root . '/tests/unit/Access/AccessValidationResultTest.php',
	$root . '/tests/unit/Access/CreditRuleTest.php',
	$root . '/tests/unit/Access/GuestRuleTest.php',
	$root . '/tests/unit/Access/ExpiryRuleTest.php',
	$root . '/tests/unit/Commerce/BookingDateFieldTest.php',
	$root . '/tests/unit/Commerce/CheckoutFieldsTest.php',
	$root . '/tests/unit/Commerce/LoopAddToCartTest.php',
);

$failures = 0;

foreach ( $tests as $test_file ) {
	require $test_file;
	$namespace  = false !== strpos( $test_file, '/Commerce/' ) ? 'Commerce' : 'Access';
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

fwrite( STDOUT, "\n" );

if ( $failures > 0 ) {
	fwrite( STDERR, $failures . " test failure(s).\n" );
	exit( 1 );
}

fwrite( STDOUT, "All lightweight tests passed.\n" );
