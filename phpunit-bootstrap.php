<?php
/**
 * PHPUnit bootstrap for this standard's sniff unit tests.
 *
 * Loads PHP_CodeSniffer's test framework and registers this standard's
 * `*UnitTest.php` classes with the globals AbstractSniffUnitTest expects, so the
 * suite runs only this standard's tests (not every installed standard).
 *
 * @package StellarWP\CodingStandards
 */

require __DIR__ . '/vendor/squizlabs/php_codesniffer/tests/bootstrap.php';

$standard_dir = __DIR__ . '/StellarWP';
$tests_dir    = $standard_dir . '/Tests/';

foreach ( [ 'PHP_CODESNIFFER_STANDARD_DIRS', 'PHP_CODESNIFFER_TEST_DIRS', 'PHP_CODESNIFFER_SNIFF_CODES', 'PHP_CODESNIFFER_SNIFF_CASE_FILES', 'PHP_CODESNIFFER_FIXABLE_CODES', 'PHP_CODESNIFFER_RULESETS' ] as $global ) {
	if ( isset( $GLOBALS[ $global ] ) === false ) {
		$GLOBALS[ $global ] = [];
	}
}

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $tests_dir, FilesystemIterator::SKIP_DOTS )
);

foreach ( $iterator as $file ) {
	if ( substr( $file->getFilename(), -12 ) !== 'UnitTest.php' ) {
		continue;
	}

	$relative = substr( $file->getPathname(), strlen( $tests_dir ), -4 );
	$class    = 'StellarWP\\Tests\\' . str_replace( DIRECTORY_SEPARATOR, '\\', $relative );

	$GLOBALS['PHP_CODESNIFFER_STANDARD_DIRS'][ $class ] = $standard_dir;
	$GLOBALS['PHP_CODESNIFFER_TEST_DIRS'][ $class ]     = $tests_dir;
}
