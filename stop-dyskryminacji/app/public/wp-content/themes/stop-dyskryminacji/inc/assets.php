<?php
/**
 * Kolejkowanie stylów i skryptów.
 *
 * Zasady przyjęte dla wyniku Lighthouse Performance ≥ 90 na urządzeniu mobilnym
 * (warunek odbioru, Załącznik nr 1 pkt 8):
 *   - zero webfontów i zero zasobów z domen trzecich w krytycznej ścieżce renderowania;
 *   - jeden arkusz stylów motywu, style bloków rdzenia ładowane tylko dla użytych bloków;
 *   - skrypty wyłącznie defer, żaden nie blokuje pierwszego renderowania.
 *
 * @package StopDyskryminacji
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wersja pliku oparta na czasie modyfikacji — poprawne unieważnianie cache
 * bez ręcznego podbijania numerów przy każdej poprawce.
 *
 * @param string $relative Ścieżka względna w katalogu motywu.
 * @return string
 */
function sd_asset_version( $relative ) {
	$path = SD_THEME_DIR . '/' . ltrim( $relative, '/' );
	return file_exists( $path ) ? (string) filemtime( $path ) : SD_THEME_VERSION;
}

/**
 * Style i skrypty front-endu.
 */
function sd_enqueue_assets() {
	// Nagłówek motywu — wymagany przez WordPress, sam w sobie nie zawiera stylów.
	wp_enqueue_style( 'stop-dyskryminacji', get_stylesheet_uri(), array(), SD_THEME_VERSION );

	wp_enqueue_style(
		'sd-main',
		SD_THEME_URI . '/assets/css/main.css',
		array( 'stop-dyskryminacji' ),
		sd_asset_version( 'assets/css/main.css' )
	);

	// Nawigacja mobilna + „Na tej stronie". Oba działają po załadowaniu DOM.
	wp_enqueue_script(
		'sd-ui',
		SD_THEME_URI . '/assets/js/ui.js',
		array(),
		sd_asset_version( 'assets/js/ui.js' ),
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);

	wp_localize_script(
		'sd-ui',
		'sdStrings',
		array(
			'onThis' => __( 'Na tej stronie', 'stop-dyskryminacji' ),
			'copied' => __( 'Skopiowano adres strony', 'stop-dyskryminacji' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'sd_enqueue_assets' );

/**
 * Style edytora bloków — ten sam arkusz, dzięki czemu redaktor widzi
 * dokładnie ten układ, który zobaczy użytkownik.
 */
function sd_enqueue_editor_assets() {
	wp_enqueue_style(
		'sd-editor',
		SD_THEME_URI . '/assets/css/editor.css',
		array(),
		sd_asset_version( 'assets/css/editor.css' )
	);
}
add_action( 'enqueue_block_editor_assets', 'sd_enqueue_editor_assets' );

/**
 * Ładowanie stylów bloków rdzenia osobno, tylko dla bloków faktycznie użytych
 * na danej stronie. Zauważalnie zmniejsza CSS na stronach prostych.
 */
add_filter( 'should_load_separate_core_block_assets', '__return_true' );
