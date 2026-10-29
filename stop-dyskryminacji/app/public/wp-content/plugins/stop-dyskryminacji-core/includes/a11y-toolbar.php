<?php
/**
 * Panel ułatwień dostępu — pływający przycisk z ustawieniami wyświetlania i czytania.
 *
 * Własny, lekki panel zamiast nakładek firm trzecich (UserWay, accessiBe i podobne):
 * żadnego skryptu z cudzego serwera na stronie z formularzem zgłoszeń, brak wpływu
 * na wynik Lighthouse. Panel jest dodatkiem dla osób słabowidzących, z dysleksją
 * i z trudnościami w czytaniu. Nie zastępuje dostępnej treści ani pełnej oceny WCAG.
 *
 * Ustawienia zapisuje przeglądarka (localStorage „sd-a11y") dopiero wtedy, gdy
 * użytkownik coś wybierze. To zapis niezbędny do usługi, o którą sam prosi, więc
 * nie wymaga zgody (wyjątek z art. 5 ust. 3 dyrektywy 2002/58/WE). Nic nie trafia
 * na serwer.
 *
 * Zestaw znaków FE zostaje w każdym trybie pełnokolorowy i na białym tle
 * (Załącznik nr 10 rozdz. 4 i 5) — tryby nie używają filtrów na całej stronie.
 *
 * @package StopDyskryminacjiCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Czy panel jest włączony. Wyłączenie: add_filter( 'sdc_a11y_toolbar_enabled', '__return_false' ).
 *
 * @return bool
 */
function sdc_a11y_enabled() {
	return (bool) apply_filters( 'sdc_a11y_toolbar_enabled', true );
}

/**
 * Zapisane ustawienia nakładane przed pierwszym wyświetleniem strony — bez
 * mignięcia domyślnego wyglądu przy przejściu na kolejną podstronę.
 */
function sdc_a11y_head_script() {
	if ( is_admin() || ! sdc_a11y_enabled() ) {
		return;
	}
	echo "<script>(function(){try{var s=JSON.parse(localStorage.getItem('sd-a11y')||'{}'),c=document.documentElement.classList;"
		. "if(s.text>0&&s.text<5)c.add('sd-a11y-text-'+s.text);"
		. "if(s.contrast==='bw'||s.contrast==='yb')c.add('sd-a11y-contrast-'+s.contrast);"
		. "['links','dys','reading','cursor','motion'].forEach(function(k){if(s[k])c.add('sd-a11y-'+k)});}catch(e){}})();</script>\n";
}
add_action( 'wp_head', 'sdc_a11y_head_script', 1 );

/**
 * Style i skrypt panelu.
 */
function sdc_a11y_assets() {
	if ( ! sdc_a11y_enabled() ) {
		return;
	}
	wp_enqueue_style( 'sdc-a11y', SDC_URL . 'assets/css/a11y-toolbar.css', array(), SDC_VERSION );
	wp_enqueue_script(
		'sdc-a11y',
		SDC_URL . 'assets/js/a11y-toolbar.js',
		array(),
		SDC_VERSION,
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);

	$lang      = function_exists( 'sdc_current_language' ) ? sdc_current_language() : 'pl';
	$statement = get_page_by_path( 'deklaracja-dostepnosci' );
	$easy      = get_page_by_path( 'prosty-jezyk' );

	wp_add_inline_script(
		'sdc-a11y',
		'window.sdcA11y = ' . wp_json_encode(
			array(
				'lang'      => $lang,
				'statement' => $statement && 'publish' === $statement->post_status ? sdc_localized_content_url( get_permalink( $statement ), $lang ) : '',
				// Prosty język jest tylko po polsku — w innych wersjach odnośnik byłby mylący.
				'easy'      => 'pl' === $lang && $easy && 'publish' === $easy->post_status ? get_permalink( $easy ) : '',
			)
		) . ';',
		'before'
	);
}
add_action( 'wp_enqueue_scripts', 'sdc_a11y_assets' );
