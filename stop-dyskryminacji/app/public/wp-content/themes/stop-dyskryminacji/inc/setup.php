<?php
/**
 * Konfiguracja motywu.
 *
 * @package StopDyskryminacji
 */

defined( 'ABSPATH' ) || exit;

/**
 * Deklaracje wsparcia motywu.
 */
function sd_theme_setup() {
	load_theme_textdomain( 'stop-dyskryminacji', SD_THEME_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );

	// Motyw nie ładuje webfontów — kolejka czcionek jest systemowa (patrz theme.json).
	remove_theme_support( 'core-block-patterns' );

	add_editor_style( array( 'assets/css/main.css', 'assets/css/editor.css' ) );

	/*
	 * Rozmiary obrazów dopasowane do siatki (kafelek 3-kolumnowy w kontenerze 75rem).
	 * Ograniczone i przemyślane — każdy dodatkowy rozmiar to kolejne pliki na dysku.
	 */
	add_image_size( 'sd-card', 640, 360, true );   // karta artykułu / publikacji
	add_image_size( 'sd-wide', 1280, 640, true );  // obraz wyróżniający na wpisie
	add_image_size( 'sd-cover', 480, 678, false ); // okładka publikacji (proporcja A4)
}
add_action( 'after_setup_theme', 'sd_theme_setup' );

/**
 * Czytelne nazwy rozmiarów obrazów w bibliotece mediów.
 *
 * @param array $sizes Rozmiary.
 * @return array
 */
function sd_image_size_names( $sizes ) {
	return array_merge(
		$sizes,
		array(
			'sd-card'  => __( 'Karta (640×360)', 'stop-dyskryminacji' ),
			'sd-wide'  => __( 'Szeroki (1280×640)', 'stop-dyskryminacji' ),
			'sd-cover' => __( 'Okładka publikacji', 'stop-dyskryminacji' ),
		)
	);
}
add_filter( 'image_size_names_choose', 'sd_image_size_names' );

/**
 * Menu nawigacyjne obsługiwane klasycznie na wypadek, gdyby redaktor
 * wolał je konfigurować poza edytorem miejsc.
 */
function sd_register_menus() {
	register_nav_menus(
		array(
			'primary' => __( 'Menu główne', 'stop-dyskryminacji' ),
			'footer'  => __( 'Menu w stopce', 'stop-dyskryminacji' ),
			'legal'   => __( 'Menu prawne (RODO, dostępność)', 'stop-dyskryminacji' ),
		)
	);
}
add_action( 'init', 'sd_register_menus' );

/**
 * Język treści dla atrybutu lang — pozwala wtyczce wielojęzycznej nadpisać wartość.
 *
 * @return string
 */
function sd_content_language() {
	return apply_filters( 'sd_content_language', substr( get_locale(), 0, 2 ) ?: 'pl' );
}

/**
 * Tytuł archiwum bez przedrostka typu „Archiwum:" albo „Kategoria:".
 *
 * Przedrostek jest żargonem systemu, a nie informacją dla czytelnika —
 * na stronie z publikacjami ma stać „Publikacje", nie „Archives: Publikacje".
 * Kontekst niesie ścieżka nawigacji nad nagłówkiem.
 *
 * @param string $title Tytuł.
 * @return string
 */
function sd_clean_archive_title( $title ) {
	return wp_strip_all_tags( preg_replace( '/^[^:]+:\s*/u', '', $title ) );
}
add_filter( 'get_the_archive_title', 'sd_clean_archive_title' );

/**
 * Wyłączenie przedrostka jako osobnego elementu (WordPress 6.6+).
 *
 * @return string
 */
add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );
