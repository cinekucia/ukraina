<?php
/** Four EPUB editions of the approved publication texts; explicit release. */
defined( 'ABSPATH' ) || exit;
function sdc_epub26_data() {
	static $data;
	if ( null === $data ) { $data = json_decode( file_get_contents( SDC_PATH . 'data/epub-2026-10-26.json' ), true ); }
	return $data;
}
add_filter( 'sdc_editorial_package', function ( $package ) { $data = sdc_epub26_data(); foreach ( $data['assets'] ?? array() as $key => $asset ) { $package['assets'][ $key ] = $asset; } return $package; }, 60 );
add_filter( 'sdc_editorial_asset_file', function ( $file, $key ) { return preg_match( '/^P[1-4]_EPUB$/D', $key ) ? SDC_PATH . 'data/epub-assets/' . $key . '.php' : $file; }, 60, 2 );
function sdc_apply_epub26() {
	if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'publish_posts' ) ) { return new WP_Error( 'forbidden', 'Brak uprawnień.' ); }
	if ( get_option( 'sdc_epub26_done' ) ) { return true; }
	$data = sdc_epub26_data(); $plan = array(); $backup = array();
	if ( 4 !== count( $data['assets'] ?? array() ) ) { return new WP_Error( 'files', 'Brak kompletu czterech EPUB-ów.' ); }
	foreach ( $data['assets'] as $key => $asset ) {
		$code = substr( $key, 0, 2 ); $id = sdc_editorial_find( 'publikacja', '_sdc_editorial_code', $code );
		if ( is_wp_error( $id ) || ! $id || 'publish' !== get_post_status( $id ) || sdc_editorial_pending( $id ) || '1' !== get_post_meta( $id, '_sdc_editorial_released', true ) || ! current_user_can( 'edit_post', $id ) ) { return new WP_Error( 'publication', 'Brak zatwierdzonej publicznej publikacji ' . $code ); }
		$bytes = sdc_editorial_asset_bytes( $key );
		if ( ! is_string( $bytes ) || strlen( $bytes ) !== $asset['bytes'] || ! hash_equals( $asset['sha256'], hash( 'sha256', $bytes ) ) || 'PK' !== substr( $bytes, 0, 2 ) ) { return new WP_Error( 'checksum', 'Niepoprawny plik ' . $key ); }
		$existing = sdc_editorial_find( 'attachment', '_sdc_editorial_asset_key', $key );
		if ( is_wp_error( $existing ) || ( $existing && ( get_post_meta( $existing, '_sdc_editorial_asset_sha256', true ) !== $asset['sha256'] || (int) get_post_field( 'post_parent', $existing ) !== $id ) ) ) { return new WP_Error( 'existing', 'Istnieje inna wersja ' . $key ); }
		$plan[ $key ] = array( 'parent' => $id, 'asset' => $asset ); $backup[ $id ] = get_post_meta( $id, '_sdc_epub_id', true );
	}
	update_option( 'sdc_epub26_before', $backup, false ); $done = array();
	foreach ( $plan as $key => $item ) {
		$id = sdc_editorial_register_attachment( $key, $item['parent'], $item['asset'] ); if ( is_wp_error( $id ) ) { return $id; }
		update_post_meta( $id, '_sdc_asset_released', '1' ); update_post_meta( $item['parent'], '_sdc_epub_id', $id ); $done[ $key ] = $id;
	}
	sdc_stats_invalidate_catalog(); update_option( 'sdc_epub26_done', array( 'date' => current_time( 'mysql' ), 'by' => get_current_user_id(), 'assets' => $done ), false );
	return true;
}
add_action( 'admin_post_sdc_epub26', function () { check_admin_referer( 'sdc_epub26' ); $result = sdc_apply_epub26(); if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); } wp_safe_redirect( admin_url( 'tools.php?page=sdc-privacy-epub' ) ); exit; } );
