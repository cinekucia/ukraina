<?php
/**
 * Drobne funkcje pomocnicze używane w kilku miejscach wtyczki.
 *
 * @package StopDyskryminacjiCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ustawienie wtyczki z wartością domyślną.
 *
 * @param string $key     Klucz opcji bez przedrostka.
 * @param mixed  $default Wartość domyślna.
 * @return mixed
 */
function sdc_option( $key, $default = '' ) {
	$value = get_option( 'sdc_' . $key, $default );
	return ( '' === $value || null === $value ) ? $default : $value;
}

/**
 * Bezpieczne pobranie pola z $_POST.
 *
 * @param string $key      Nazwa pola.
 * @param string $sanitize Funkcja sanityzująca: text, textarea, email, key, int.
 * @return string|int
 */
function sdc_post( $key, $sanitize = 'text' ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce sprawdzany w funkcji wywołującej.
	$raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';

	if ( is_array( $raw ) ) {
		return array_map( 'sanitize_text_field', $raw );
	}

	switch ( $sanitize ) {
		case 'textarea':
			return sanitize_textarea_field( $raw );
		case 'email':
			return sanitize_email( $raw );
		case 'key':
			return sanitize_key( $raw );
		case 'int':
			return (int) $raw;
		default:
			return sanitize_text_field( $raw );
	}
}

/**
 * Pseudonimizowany identyfikator klienta — do ograniczania liczby zgłoszeń
 * z jednego źródła bez przechowywania adresu IP w postaci jawnej.
 *
 * Adres IP jest daną osobową (motyw 30 RODO). Do limitowania wystarczy
 * skrót z solą, z którego nie da się odtworzyć adresu.
 *
 * @return string
 */
function sdc_client_fingerprint() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	return hash_hmac( 'sha256', $ip . '|' . $ua, wp_salt( 'nonce' ) );
}

/**
 * Deklarowany czas reakcji na zgłoszenie, w dniach roboczych.
 *
 * @return int
 */
function sdc_response_days() {
	return max( 1, (int) sdc_option( 'response_days', 5 ) );
}

/**
 * Okres retencji zgłoszeń w dniach.
 *
 * @return int
 */
function sdc_retention_days() {
	return 90; // Approved case retention: 90 days from the last actual contact.
}

/**
 * Lista przesłanek dyskryminacji używana w formularzu i w taksonomii.
 *
 * Zakres odpowiada art. 18(3a) Kodeksu pracy oraz przesłankom wskazanym
 * we wniosku o dofinansowanie (płeć, wiek, narodowość, niepełnosprawność).
 *
 * @return array
 */
function sdc_grounds() {
	return array(
		'plec'               => __( 'Płeć', 'stop-dyskryminacji-core' ),
		'wiek'               => __( 'Wiek', 'stop-dyskryminacji-core' ),
		'narodowosc'         => __( 'Narodowość, pochodzenie etniczne lub rasa', 'stop-dyskryminacji-core' ),
		'niepelnosprawnosc'  => __( 'Niepełnosprawność lub stan zdrowia', 'stop-dyskryminacji-core' ),
		'religia'            => __( 'Religia, wyznanie lub światopogląd', 'stop-dyskryminacji-core' ),
		'orientacja'         => __( 'Orientacja seksualna lub tożsamość płciowa', 'stop-dyskryminacji-core' ),
		'zwiazkowa'          => __( 'Przynależność związkowa lub poglądy polityczne', 'stop-dyskryminacji-core' ),
		'zatrudnienie'       => __( 'Forma lub wymiar zatrudnienia', 'stop-dyskryminacji-core' ),
		'rodzicielstwo'      => __( 'Rodzicielstwo, ciąża lub opieka nad bliskimi', 'stop-dyskryminacji-core' ),
		'inne'               => __( 'Inna przyczyna', 'stop-dyskryminacji-core' ),
		'nie-wiem'           => __( 'Nie wiem', 'stop-dyskryminacji-core' ),
	);
}

/**
 * Role zgłaszającego — rekomendacja nr 2 z benchmarku.
 *
 * @return array
 */
function sdc_reporter_roles() {
	return array(
		'osoba'      => __( 'Osoba, której to dotyczy', 'stop-dyskryminacji-core' ),
		'swiadek'    => __( 'Świadek zdarzenia', 'stop-dyskryminacji-core' ),
		'pracodawca' => __( 'Pracodawca, HR lub przedstawiciel firmy', 'stop-dyskryminacji-core' ),
		'inna'       => __( 'Ktoś inny — np. osoba bliska', 'stop-dyskryminacji-core' ),
	);
}

/**
 * Ikona SVG z wbudowanego zestawu.
 *
 * Ikony są wstawiane w treść strony, a nie ładowane jako osobne pliki —
 * oszczędza to żądania HTTP i pozwala im dziedziczyć kolor tekstu,
 * co ma znaczenie w trybie wysokiego kontrastu.
 *
 * @param string $name Nazwa ikony.
 * @param int    $size Rozmiar w pikselach.
 * @return string
 */
function sdc_icon( $name, $size = 20 ) {
	$paths = array(
		'pdf'      => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 15h1.5a1.5 1.5 0 0 0 0-3H9v6M14.5 18v-6h1a2 2 0 0 1 0 4h-1"/>',
		'epub'     => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
		'doc'      => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h5"/>',
		'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/>',
		'link'     => '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
		'check'    => '<path d="M20 6 9 17l-5-5"/>',
		'alert'    => '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>',
		'shield'   => '<path d="M12 2 4 5v6c0 5 3.4 9.1 8 11 4.6-1.9 8-6 8-11V5z"/>',
		'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'external' => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
		'chevron'  => '<path d="m9 18 6-6-6-6"/>',
	);

	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}

	return sprintf(
		'<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
		(int) $size,
		$paths[ $name ]
	);
}

/**
 * Rozszerzenie listy dozwolonych znaczników w wp_kses o SVG używane przez ikony.
 *
 * @return array
 */
function sdc_kses_svg() {
	$common = array( 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'fill' => true, 'stroke' => true, 'd' => true );
	return array_merge(
		wp_kses_allowed_html( 'post' ),
		array(
			'svg'    => array( 'width' => true, 'height' => true, 'viewbox' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'aria-hidden' => true, 'focusable' => true, 'class' => true, 'role' => true ),
			'path'   => $common,
			'circle' => array_merge( $common, array( 'cx' => true, 'cy' => true, 'r' => true ) ),
			'rect'   => array_merge( $common, array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true ) ),
			'line'   => array_merge( $common, array( 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true ) ),
		)
	);
}
