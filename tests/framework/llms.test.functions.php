<?php

/**
 * Plug llms_crrent_time() to allow mocking of the current time via the $llms_mock_time global
 * @param  string       $type   Type of time to retrieve. Accepts 'mysql', 'timestamp', or PHP date format string (e.g. 'Y-m-d').
 * @param  int|bool     $gmt    Optional. Whether to use GMT timezone. Default false.
 * @return int|string           Integer if $type is 'timestamp', string otherwise.
 * @since    3.4.0
 * @version  3.4.0
 */
function llms_current_time( $type, $gmt = 0 ) {
	global $llms_mock_time;
	if ( ! empty( $llms_mock_time ) ) {
		return $llms_mock_time;
	}
	return current_time( $type, $gmt );
}

/**
 * Set the mocked current time
 * @param    mixed     $time  date time string parsable by date()
 * @return   void
 * @since    3.4.0
 * @version  3.4.0
 */
function llms_mock_current_time( $time ) {
	global $llms_mock_time;
	$llms_mock_time = strtotime( $time );
}
