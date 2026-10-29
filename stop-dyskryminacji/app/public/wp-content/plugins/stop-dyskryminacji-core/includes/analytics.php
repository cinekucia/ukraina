<?php
/**
 * Google Analytics 4 — trzy zdarzenia wymagane umową.
 *
 * Etap 7 zakresu obowiązków oraz kryterium odbioru:
 * „GA4 wdrożone; zdarzenia: pobranie publikacji, wysłanie zgłoszenia,
 * unikalny użytkownik".
 *
 * WSKAŹNIKI ROZLICZENIOWE ZADANIA 1 (wniosek o dofinansowanie):
 *   - minimum 2 000 unikalnych użytkowników  → zdarzenie „uzytkownik_unikalny"
 *   - minimum 1 000 pobrań publikacji        → zdarzenie „pobranie_publikacji"
 *   - zgłoszenia w module                    → zdarzenie „wyslanie_zgloszenia"
 * Rekomendacja nr 12 z benchmarku: wpisać te wskaźniki w GA4 od pierwszego dnia,
 * bo to one decydują o rozliczeniu kwoty ryczałtowej.
 *
 * Cały kod GA4 uruchamia się WYŁĄCZNIE przez window.sdInitAnalytics(), które
 * wywołuje moduł zgody. Bez zgody skrypt Google nie trafia do dokumentu.
 *
 * @package StopDyskryminacjiCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Zweryfikowany identyfikator pomiaru GA4 albo pusty ciąg.
 *
 * Jedno miejsce prawdy dla analityki i dla okna zgody — gdyby te dwa moduły
 * oceniały konfigurację niezależnie, dałoby się doprowadzić do stanu, w którym
 * banner pyta o zgodę na analitykę, której i tak nie ma.
 *
 * @return string
 */
function sdc_ga4_measurement_id() {
	$ga4 = trim( (string) sdc_option( 'ga4_id', '' ) );

	// Identyfikator pomiaru ma postać G-XXXXXXXXXX.
	return preg_match( '/^G-[A-Z0-9]{6,20}$/i', $ga4 ) ? $ga4 : '';
}

/**
 * Skrypt analityczny — uzbrojony, ale nieuruchomiony.
 */
function sdc_analytics_script() {
	$ga4 = is_user_logged_in() ? '' : sdc_ga4_measurement_id();
	if ( is_admin() || ( '' === $ga4 && ! sdc_stats_enabled() ) ) { return; }
	$enabled = ! is_search() && ! is_404() && ! is_preview();
	$downloads = array(); $assets = array(); $package = sdc_editorial_package();
	foreach ( get_posts( array( 'post_type' => 'attachment', 'post_status' => array( 'private', 'inherit', 'publish' ), 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_sdc_asset_released', 'meta_value' => '1' ) ) as $id ) {
		if ( ! sdc_editorial_asset_is_public( $id ) ) { continue; }
		$key = get_post_meta( $id, '_sdc_editorial_asset_key', true ); $asset = $package['assets'][ $key ] ?? array();
		if ( ! preg_match( '/^(P[1-4]|A(?:0[1-9]|1[0-2]))_(PDF|PRINT|EPUB)$/D', $key, $m ) || empty( $asset['filename'] ) ) { continue; }
		$value = array( 'file' => $asset['filename'], 'format' => strtolower( pathinfo( $asset['filename'], PATHINFO_EXTENSION ) ), 'code' => $m[1] );
		$downloads[ sdc_editorial_asset_url( $id ) ] = $value; $assets[ $id ] = $value;
	}
	$help = is_singular() && has_block( 'stopdysk/help-form', get_post_field( 'post_content', get_queried_object_id() ) );
	$thankyou = is_page() && ( (int) sdc_option( 'thankyou_page_id', 0 ) === get_queried_object_id() || '/dziekujemy/' === get_post_meta( get_queried_object_id(), '_sdc_source_path', true ) || is_page( 'dziekujemy' ) );
	$path = wp_parse_url( home_url( strtok( $_SERVER['REQUEST_URI'] ?? '/', '?' ) ), PHP_URL_PATH );
	$catalog = sdc_stats_catalog();
	$url = ( $help || $thankyou ) ? home_url( '/' ) : ( is_singular() ? get_permalink() : ( isset( $catalog['paths'][ $path ] ) ? $catalog['items'][ $catalog['paths'][ $path ] ]['url'] : home_url( '/' ) ) );
	$config = array( 'id' => $ga4, 'enabled' => $enabled, 'pageUrl' => $enabled ? $url : home_url( '/' ), 'pageTitle' => ( $help || $thankyou || ! $enabled ) ? get_bloginfo( 'name' ) : wp_get_document_title(), 'isHelp' => $help, 'isThankyou' => $thankyou, 'downloads' => $downloads, 'assets' => $assets );
	wp_enqueue_script( 'sdc-analytics', SDC_URL . 'assets/js/analytics.js', array(), SDC_VERSION, array( 'in_footer' => true ) );
	wp_add_inline_script( 'sdc-analytics', 'window.sdAnalyticsConfig = ' . wp_json_encode( $config ) . ';', 'before' );
}
add_action( 'wp_enqueue_scripts', 'sdc_analytics_script' );

/** Essential, browser-bound receipt. No case ID, email or contents enter analytics. */
function sdc_analytics_set_receipt() {
	$session = sdc_form_session( true );
	if ( $session ) { set_transient( 'sdc_ga_receipt_' . hash_hmac( 'sha256', $session, wp_salt( 'nonce' ) ), '1', 10 * MINUTE_IN_SECONDS ); }
}
function sdc_analytics_submitted_marker() {
	if ( ! is_page() || ! isset( $_GET['zgloszenie'] ) || 'ok' !== $_GET['zgloszenie'] ) { return; }
	$source = get_post_meta( get_queried_object_id(), '_sdc_source_path', true );
	if ( ! is_page( 'dziekujemy' ) && '/dziekujemy/' !== $source && (int) sdc_option( 'thankyou_page_id', 0 ) !== get_queried_object_id() ) { return; }
	$session = sdc_form_session(); if ( ! $session ) { return; }
	$key = 'sdc_ga_receipt_' . hash_hmac( 'sha256', $session, wp_salt( 'nonce' ) );
	if ( '1' === get_transient( $key ) ) { delete_transient( $key ); echo '<span data-sd-submitted hidden></span>'; }
}
add_action( 'wp_footer', 'sdc_analytics_submitted_marker', 5 );
