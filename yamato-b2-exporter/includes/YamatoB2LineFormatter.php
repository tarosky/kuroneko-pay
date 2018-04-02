<?php
/**
 * Change CSV line format
 *
 * @package yb2
 */
class YamatoB2LineFormatter extends php_user_filter {

	/**
	 * Change line ending
	 *
	 * @param resource $in
	 * @param resource $out
	 * @param int      $consumed
	 * @param bool     $closing
	 *
	 * @return int
	 */
	public function filter( $in, $out, &$consumed, $closing ) {
		while ( $bucket = stream_bucket_make_writeable( $in ) ) {
			$bucket->data = preg_replace( '/\n$/', '', $bucket->data );
			$bucket->data = preg_replace( '/\r$/', '', $bucket->data );
			$bucket->data = $bucket->data . "\r\n";
			$consumed += $bucket->datalen;
			stream_bucket_append( $out, $bucket );
		}
		return PSFS_PASS_ON;
	}

}