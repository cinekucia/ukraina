<?php
/**
 * Odchudzenie front-endu.
 *
 * Każde wyłączenie poniżej dotyczy rzeczy, z których serwis nie korzysta.
 * Nic tutaj nie usuwa funkcji widocznej dla redaktora ani dla użytkownika.
 *
 * @package StopDyskryminacji
 */

defined( 'ABSPATH' ) || exit;

/**
 * Usunięcie skryptu emoji (ok. 12 kB JS + obrazki zastępcze).
 * Serwis publikuje treści poradnikowe, nie korzysta z konwersji emoji.
 */
function sd_disable_emoji() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'emoji_svg_url', '__return_false' );
}
add_action( 'init', 'sd_disable_emoji' );

/**
 * Usunięcie znaczników w <head>, których serwis nie używa,
 * a które zwiększają powierzchnię ataku lub rozmiar dokumentu.
 */
function sd_clean_head() {
	remove_action( 'wp_head', 'wp_generator' );                 // wersja WordPressa
	remove_action( 'wp_head', 'wlwmanifest_link' );             // Windows Live Writer
	remove_action( 'wp_head', 'rsd_link' );                     // Really Simple Discovery
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head' );
}
add_action( 'init', 'sd_clean_head' );

/**
 * Wyłączenie XML-RPC — nieużywane, a jest częstym celem ataków słownikowych.
 */
add_filter( 'xmlrpc_enabled', '__return_false' );

/**
 * Leniwe ładowanie obrazów jest domyślne w WordPressie, ale pierwszy obraz
 * w treści (zwykle LCP) powinien ładować się natychmiast i z wysokim priorytetem.
 */
add_filter( 'wp_omit_loading_attr_threshold', function () {
	return 1;
} );

/**
 * Bezpieczne nagłówki HTTP wysyłane przez aplikację.
 *
 * Nagłówki związane z transportem (HSTS) ustawia serwer/WAF hostingu —
 * tutaj tylko te, które sensownie kontrolować z poziomu aplikacji.
 *
 * @param array $headers Nagłówki.
 * @return array
 */
function sd_security_headers( $headers ) {
	$headers['X-Content-Type-Options'] = 'nosniff';
	$headers['Referrer-Policy']        = 'strict-origin-when-cross-origin';
	$headers['X-Frame-Options']        = 'SAMEORIGIN';
	// Blokada API, których serwis nie używa, a które dotyczą prywatności.
	$headers['Permissions-Policy']     = 'geolocation=(), microphone=(), camera=(), payment=(), interest-cohort=()';
	return $headers;
}
add_filter( 'wp_headers', 'sd_security_headers' );
