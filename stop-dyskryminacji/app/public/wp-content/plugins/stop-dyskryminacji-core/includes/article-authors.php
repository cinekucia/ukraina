<?php
/** Authorship correction explicitly requested by the operator on 26 October 2026. */
defined( 'ABSPATH' ) || exit;

function sdc_article_authors_text() {
	return 'Tetiana Wróblewska, Krzysztof Martyniak – ekspert ds. równego traktowania';
}
function sdc_article_authors_content( $content ) {
	return preg_replace( '/Autor:\s*Krzysztof Martyniak(?:\s*–\s*ekspert ds\. równego traktowania)?/u', 'Autorzy: ' . sdc_article_authors_text(), $content );
}
function sdc_article_has_coauthors( $id ) {
	return 'post' === get_post_type( $id ) && preg_match( '/^A(?:0[1-9]|1[0-2])$/D', get_post_meta( $id, '_sdc_editorial_code', true ) )
		&& sdc_article_authors_text() === get_post_meta( $id, '_sdc_editorial_author', true );
}
function sdc_article_authors_data() {
	static $data;
	if ( null === $data ) { $data = json_decode( file_get_contents( SDC_PATH . 'data/article-authors-2026-10-26.json' ), true ); }
	return $data;
}
function sdc_article_author_people() {
	return array( array( '@type' => 'Person', '@id' => home_url( '/#tetiana-wroblewska' ), 'name' => 'Tetiana Wróblewska' ), sdc_seo_person() );
}
add_filter( 'sdc_editorial_package', function ( $package ) {
	if ( ! get_option( 'sdc_article_authors_20261026_done' ) ) { return $package; }
	$data = sdc_article_authors_data();
	foreach ( $data['articles'] as $code => $item ) { $package['assets'][ $code . '_PRINT' ] = $item['asset']; }
	foreach ( $package['entries'] as &$entry ) {
		if ( isset( $data['articles'][ $entry['code'] ] ) && 'post' === $entry['type'] ) { $entry['author'] = sdc_article_authors_text(); $entry['content'] = sdc_article_authors_content( $entry['content'] ); }
	}
	unset( $entry ); return $package;
}, 50 );
add_filter( 'sdc_editorial_asset_file', function ( $file, $key ) {
	if ( get_option( 'sdc_article_authors_20261026_done' ) && preg_match( '/^A(?:0[1-9]|1[0-2])_PRINT$/D', $key ) ) {
		return SDC_PATH . 'data/article-authors-assets/' . $key . '.php';
	}
	return $file;
}, 50, 2 );

function sdc_apply_article_authors() {
	if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'publish_posts' ) ) { return new WP_Error( 'forbidden', 'Brak uprawnień.' ); }
	if ( get_option( 'sdc_article_authors_20261026_done' ) ) { return true; }
	$data = sdc_article_authors_data(); $plan = array();
	if ( 12 !== count( $data['articles'] ?? array() ) ) { return new WP_Error( 'package', 'Brak kompletu 12 plików.' ); }
	// Check every live source and replacement before changing any metadata.
	foreach ( $data['articles'] as $code => $item ) {
		$id = sdc_editorial_find( 'post', '_sdc_editorial_code', $code );
		if ( is_wp_error( $id ) || ! $id || 'publish' !== get_post_status( $id ) || ! current_user_can( 'edit_post', $id ) ) { return new WP_Error( 'article', 'Nieprawidłowy artykuł ' . $code ); }
		$pdf = (int) get_post_meta( $id, '_sdc_editorial_public_pdf_id', true ); $key = $code . '_PRINT';
		if ( ! $pdf || get_post_meta( $pdf, '_sdc_editorial_asset_key', true ) !== $key || (int) get_post_field( 'post_parent', $pdf ) !== $id || ! sdc_editorial_asset_is_public( $pdf ) ) { return new WP_Error( 'pdf', 'Nieprawidłowe powiązanie PDF ' . $code ); }
		$old = sdc_editorial_asset_bytes( $key ); $new = require SDC_PATH . 'data/article-authors-assets/' . $key . '.php';
		if ( ! is_string( $old ) || ! hash_equals( $item['before_sha256'], hash( 'sha256', $old ) ) || ! is_string( $new ) || ! hash_equals( $item['asset']['sha256'], hash( 'sha256', $new ) ) ) { return new WP_Error( 'changed', 'PDF zmienił się od przygotowania poprawki: ' . $code ); }
		if ( 'Krzysztof Martyniak – ekspert ds. równego traktowania' !== get_post_meta( $id, '_sdc_editorial_author', true ) ) { return new WP_Error( 'author', 'Podpis zmienił się od przygotowania poprawki: ' . $code ); }
		$plan[ $code ] = array( 'id' => $id, 'pdf' => $pdf, 'asset' => $item['asset'], 'content' => sdc_article_authors_content( get_post_field( 'post_content', $id ) ) );
	}
	$backup = array();
	foreach ( $plan as $code => $item ) { $backup[ $code ] = array( 'id' => $item['id'], 'post' => get_post( $item['id'] )->to_array(), 'meta' => get_post_custom( $item['id'] ), 'pdf_meta' => get_post_custom( $item['pdf'] ) ); }
	update_option( 'sdc_article_authors_20261026_before', $backup, false );
	foreach ( $plan as $item ) {
		global $wpdb;
		// Only the literal attribution clause changes; publication and legal-update dates stay intact.
		if ( false === $wpdb->update( $wpdb->posts, array( 'post_content' => $item['content'] ), array( 'ID' => $item['id'] ), array( '%s' ), array( '%d' ) ) ) { return new WP_Error( 'save', 'Nie udało się zapisać podpisu w artykule.' ); }
		clean_post_cache( $item['id'] );
		update_post_meta( $item['id'], '_sdc_editorial_author', sdc_article_authors_text() );
		update_post_meta( $item['id'], '_sdc_editorial_authors', array( 'Tetiana Wróblewska', 'Krzysztof Martyniak' ) );
		update_post_meta( $item['pdf'], '_sdc_editorial_asset_sha256', $item['asset']['sha256'] );
		update_post_meta( $item['pdf'], '_sdc_editorial_bytes', $item['asset']['bytes'] );
	}
	update_option( 'sdc_article_authors_20261026_done', array( 'date' => current_time( 'mysql' ), 'by' => get_current_user_id(), 'codes' => array_keys( $plan ) ), false );
	return true;
}
add_action( 'admin_menu', function () { add_management_page( 'Autorzy 12 artykułów', 'Autorzy 12 artykułów', 'manage_options', 'sdc-article-authors', 'sdc_article_authors_admin' ); } );
function sdc_article_authors_admin() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	echo '<div class="wrap"><h1>Autorzy 12 artykułów</h1><p>Autorzy: ' . esc_html( sdc_article_authors_text() ) . '</p><p>Zmiana dotyczy wyłącznie artykułów A01–A12: podpisów, metadanych i PDF-ów. Adresy, tekst merytoryczny, daty i publikacje P1–P4 pozostają zachowane.</p>';
	if ( get_option( 'sdc_article_authors_20261026_done' ) ) { echo '<div class="notice notice-success inline"><p>Zaktualizowano autorów i 12 PDF-ów.</p></div>'; }
	else { echo '<form action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post"><input type="hidden" name="action" value="sdc_article_authors">'; wp_nonce_field( 'sdc_article_authors' ); submit_button( 'Zastosuj podpis autorów i odśwież 12 PDF-ów' ); echo '</form>'; }
	echo '</div>';
}
add_action( 'admin_post_sdc_article_authors', function () {
	check_admin_referer( 'sdc_article_authors' ); $result = sdc_apply_article_authors();
	if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); }
	wp_safe_redirect( admin_url( 'tools.php?page=sdc-article-authors' ) ); exit;
} );
