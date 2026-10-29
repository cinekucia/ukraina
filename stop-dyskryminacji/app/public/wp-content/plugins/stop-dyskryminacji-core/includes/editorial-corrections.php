<?php
/** The six specific editorial corrections authorized by the operator on 23 October. */
defined( 'ABSPATH' ) || exit;

function sdc_editorial_corrections_data() {
	static $data;
	if ( null === $data ) { $data = json_decode( file_get_contents( SDC_PATH . 'data/editorial-corrections-2026-10-23.json' ), true ); }
	return $data;
}

/** Exact replacements, with already-corrected copies protected from repeated edits. */
function sdc_editorial_correct_field( $value, $changes ) {
	foreach ( $changes as $index => $change ) {
		$old = $change['old']; $new = $change['new']; $expected = (int) $change['count'];
		$existing = substr_count( $value, $new );
		$remaining = substr_count( str_replace( $new, '', $value ), $old );
		if ( ! $remaining && $existing >= $expected ) { continue; }
		if ( $remaining !== $expected ) { return new WP_Error( 'editorial_copy_changed', 'Tekst różni się od wersji objętej korektą. Niczego nie nadpisano; wymagana kontrola redakcyjna.' ); }
		$marker = '__SDC_CORRECTION_EXISTING_' . $index . '__';
		if ( false !== strpos( $value, $marker ) ) { return new WP_Error( 'editorial_marker', 'Konflikt znacznika korekty.' ); }
		$value = str_replace( $new, $marker, $value );
		$value = str_replace( $old, $new, $value );
		$value = str_replace( $marker, $new, $value );
	}
	return $value;
}

/** New imports receive the same approved text without changing original package identity. */
function sdc_editorial_correct_package( $package ) {
	$data = sdc_editorial_corrections_data();
	foreach ( $package['entries'] as &$entry ) {
		$patch = $data['entries'][ $entry['code'] ] ?? null;
		if ( ! $patch ) { continue; }
		foreach ( $patch['fields'] as $field => $changes ) {
			$value = sdc_editorial_correct_field( $entry[ $field ], $changes );
			if ( ! is_wp_error( $value ) ) { $entry[ $field ] = $value; }
		}
		$entry['source_file'] = $patch['source_file']; $entry['source_sha256'] = $patch['source_sha256'];
	}
	unset( $entry );
	return $package;
}

function sdc_editorial_corrections_plan() {
	$data = sdc_editorial_corrections_data(); $package = sdc_editorial_package(); $plan = array();
	foreach ( $data['entries'] as $code => $patch ) {
		$id = sdc_editorial_find( 'post', '_sdc_editorial_code', $code );
		if ( is_wp_error( $id ) ) { return $id; }
		if ( ! $id || 'publish' !== get_post_status( $id ) || sdc_editorial_pending( $id ) || '1' !== get_post_meta( $id, '_sdc_editorial_released', true ) ) {
			return new WP_Error( 'editorial_not_released', 'Brak zatwierdzonego i opublikowanego artykułu ' . $code );
		}
		if ( ! current_user_can( 'edit_post', $id ) ) { return new WP_Error( 'forbidden', 'Brak uprawnień do korekty.' ); }
		$post = get_post( $id ); $fields = array();
		foreach ( $patch['fields'] as $field => $changes ) {
			$before = 'lead_html' === $field ? get_post_meta( $id, '_sdc_editorial_lead_html', true ) : $post->{ array( 'title' => 'post_title', 'content' => 'post_content', 'excerpt' => 'post_excerpt' )[ $field ] };
			$after = sdc_editorial_correct_field( $before, $changes );
			if ( is_wp_error( $after ) ) { return new WP_Error( $after->get_error_code(), $code . ': ' . $after->get_error_message() ); }
			$fields[ $field ] = $after;
		}
		$pdf = (int) get_post_meta( $id, '_sdc_editorial_public_pdf_id', true ); $key = $code . '_PRINT';
		if ( ! $pdf || get_post_meta( $pdf, '_sdc_editorial_asset_key', true ) !== $key || (int) get_post_field( 'post_parent', $pdf ) !== $id || ! sdc_editorial_asset_is_public( $pdf ) ) {
			return new WP_Error( 'editorial_pdf_parent', 'Nieprawidłowy publiczny PDF ' . $code );
		}
		$asset = $package['assets'][ $key ]; $bytes = sdc_editorial_asset_bytes( $key );
		if ( ! is_string( $bytes ) || ! hash_equals( $asset['sha256'], hash( 'sha256', $bytes ) ) || strlen( $bytes ) !== (int) $asset['bytes'] ) { return new WP_Error( 'editorial_pdf_hash', 'Niezgodny plik PDF ' . $code ); }
		$plan[ $code ] = array( 'id' => $id, 'pdf' => $pdf, 'fields' => $fields, 'asset' => $asset, 'before' => $post->to_array(), 'source' => $patch );
	}
	return $plan;
}

function sdc_apply_editorial_corrections() {
	if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'publish_posts' ) ) { return new WP_Error( 'forbidden', 'Brak uprawnień do korekty.' ); }
	$plan = sdc_editorial_corrections_plan(); if ( is_wp_error( $plan ) ) { return $plan; }
	if ( get_option( 'sdc_content_corrections_20261023_done' ) ) { return array_keys( $plan ); }
	$lock = (int) get_option( 'sdc_content_corrections_20261023_lock' );
	if ( $lock && time() - $lock < 300 ) { return new WP_Error( 'busy', 'Korekta już trwa.' ); }
	if ( $lock ) { delete_option( 'sdc_content_corrections_20261023_lock' ); }
	if ( ! add_option( 'sdc_content_corrections_20261023_lock', time(), '', false ) ) { return new WP_Error( 'busy', 'Korekta już trwa.' ); }
	try {
		$backup = get_option( 'sdc_content_corrections_20261023_before', array() );
		foreach ( $plan as $code => $item ) {
			if ( ! isset( $backup[ $code ] ) ) { $backup[ $code ] = array( 'post' => $item['before'], 'meta' => get_post_custom( $item['id'] ), 'pdf_meta' => get_post_custom( $item['pdf'] ) ); }
		}
		update_option( 'sdc_content_corrections_20261023_before', $backup, false );
		foreach ( $plan as $code => $item ) {
			$id = $item['id']; $current = get_post( $id )->to_array();
			foreach ( array( 'post_content', 'post_title', 'post_excerpt', 'post_status', 'post_name', 'post_modified_gmt' ) as $field ) {
				if ( $current[ $field ] !== $item['before'][ $field ] ) { return new WP_Error( 'concurrent_edit', 'Artykuł zmienił się podczas korekty: ' . $code ); }
			}
			wp_save_post_revision( $id ); $update = array( 'ID' => $id );
			foreach ( array( 'title' => 'post_title', 'content' => 'post_content', 'excerpt' => 'post_excerpt' ) as $field => $column ) {
				if ( isset( $item['fields'][ $field ] ) ) { $update[ $column ] = $item['fields'][ $field ]; }
			}
			$saved = wp_update_post( wp_slash( $update ), true ); if ( is_wp_error( $saved ) ) { return $saved; }
			if ( isset( $item['fields']['lead_html'] ) ) { update_post_meta( $id, '_sdc_editorial_lead_html', $item['fields']['lead_html'] ); }
			update_post_meta( $id, '_sdc_editorial_updated_iso', '2026-10-23' );
			update_post_meta( $id, '_sdc_editorial_source_file', $item['source']['source_file'] );
			update_post_meta( $id, '_sdc_editorial_source_sha256', $item['source']['source_sha256'] );
			update_post_meta( $item['pdf'], '_sdc_editorial_asset_sha256', $item['asset']['sha256'] );
			update_post_meta( $item['pdf'], '_sdc_editorial_bytes', $item['asset']['bytes'] );
			clean_post_cache( $id ); clean_post_cache( $item['pdf'] );
		}
		update_option( 'sdc_content_corrections_20261023_done', array( 'date' => current_time( 'mysql' ), 'codes' => array_keys( $plan ) ), false );
		return array_keys( $plan );
	} finally { delete_option( 'sdc_content_corrections_20261023_lock' ); }
}

add_action( 'admin_menu', function () {
	add_management_page( 'Korekty artykułów 23.10', 'Korekty artykułów 23.10', 'manage_options', 'sdc-editorial-corrections', 'sdc_editorial_corrections_page' );
} );
function sdc_editorial_corrections_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$plan = sdc_editorial_corrections_plan();
	echo '<div class="wrap"><h1>Korekty merytoryczne artykułów — 23.10.2026</h1>';
	if ( is_wp_error( $plan ) ) { echo '<div class="notice notice-error"><p>' . esc_html( $plan->get_error_message() ) . '</p></div></div>'; return; }
	if ( get_option( 'sdc_content_corrections_20261023_done' ) ) { echo '<div class="notice notice-success"><p>Korekty zapisane. Sześć artykułów pozostaje opublikowanych; odświeżone PDF-y są dostępne pod dotychczasowymi adresami.</p></div>'; }
	echo '<p>Poprawki zgodne z audytem redakcyjnym: A02, A05, A09, A10, A11, A12. Kopia tekstów sprzed korekty jest zachowana w historii i kopii wdrożenia. Stałe adresy, status publikacji i daty publikacji pozostają zachowane.</p><ul>';
	foreach ( $plan as $code => $item ) { echo '<li><strong>' . esc_html( $code ) . '</strong> — <a href="' . esc_url( get_permalink( $item['id'] ) ) . '">' . esc_html( $item['fields']['title'] ?? get_the_title( $item['id'] ) ) . '</a> · <a href="' . esc_url( sdc_editorial_asset_url( $item['pdf'] ) ) . '">Pobierz aktualny PDF</a></li>'; }
	echo '</ul>';
	if ( ! get_option( 'sdc_content_corrections_20261023_done' ) ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="sdc_editorial_corrections">';
		wp_nonce_field( 'sdc_editorial_corrections' ); submit_button( 'Zapisz zatwierdzone korekty sześciu artykułów' ); echo '</form>';
	}
	echo '</div>';
}
add_action( 'admin_post_sdc_editorial_corrections', function () {
	check_admin_referer( 'sdc_editorial_corrections' ); $result = sdc_apply_editorial_corrections();
	if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); }
	wp_safe_redirect( admin_url( 'tools.php?page=sdc-editorial-corrections' ) ); exit;
} );

add_filter( 'the_content', function ( $content ) {
	if ( ! is_singular( 'post' ) || get_queried_object_id() !== get_the_ID() ) { return $content; }
	$date = get_post_meta( get_the_ID(), '_sdc_editorial_updated_iso', true );
	if ( '2026-10-23' !== $date ) { return $content; }
	return '<p class="sd-editorial-meta">Ostatnia aktualizacja treści: <time datetime="2026-10-23">23.10.2026</time></p>' . $content;
}, 24 );
