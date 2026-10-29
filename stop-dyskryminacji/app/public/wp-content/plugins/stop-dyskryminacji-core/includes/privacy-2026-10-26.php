<?php
/** Privacy implementation requested by the operator on 26 October 2026. */
defined( 'ABSPATH' ) || exit;

function sdc_privacy26_data() {
	static $data;
	if ( null === $data ) { $data = json_decode( file_get_contents( SDC_PATH . 'data/privacy-2026-10-26.json' ), true ); }
	return $data;
}
function sdc_privacy26_copy( $key ) {
	$data = sdc_privacy26_data(); $lang = sdc_current_language();
	return $data['copy'][ $lang ][ $key ] ?? $data['copy']['pl'][ $key ] ?? $key;
}
function sdc_privacy26_clause() {
	$data = sdc_privacy26_data(); $lang = sdc_current_language();
	return $data['clause'][ $lang ] ?? $data['clause']['pl'];
}
function sdc_privacy26_contact_errors( array $data ) {
	$errors = array();
	if ( '' !== $data['email'] && ! is_email( $data['email'] ) ) { $errors['email'] = sdc_privacy26_copy( 'invalid_email' ); }
	$digits = preg_replace( '/\D/', '', $data['telefon'] );
	if ( '' !== $data['telefon'] && ( ! preg_match( '/^\+?[0-9 ()\-.]{7,30}$/D', $data['telefon'] ) || strlen( $digits ) < 7 || strlen( $digits ) > 15 ) ) { $errors['telefon'] = sdc_privacy26_copy( 'invalid_phone' ); }
	if ( '' === $data['email'] && '' === $data['telefon'] ) { $errors['email'] = sdc_privacy26_copy( 'contact_required' ); }
	if ( mb_strlen( $data['imie'] ) > 80 || mb_strlen( $data['email'] ) > 150 || mb_strlen( $data['telefon'] ) > 30 ) { $errors['ogolny'] = sdc_privacy26_copy( 'contact_too_long' ); }
	if ( in_array( $data['przeslanka'], array( 'niepelnosprawnosc', 'religia', 'orientacja', 'narodowosc', 'zwiazkowa', 'rodzicielstwo' ), true ) && '1' !== ( $data['sensitive'] ?? '' ) ) { $errors['sensitive'] = sdc_privacy26_copy( 'sensitive_required' ); }
	return $errors;
}

/** Ninety elapsed days from the last actual contact, independent of case status. */
function sdc_submission_default_expiry( $last_contact ) {
	return (int) $last_contact + 90 * DAY_IN_SECONDS;
}
function sdc_submission_expiry( $id ) {
	$last = (int) get_post_meta( $id, '_sdc_last_contact_at', true );
	return $last > 0 && $last <= time() ? sdc_submission_default_expiry( $last ) : 0;
}
function sdc_submission_retention_label( $id ) {
	$expiry = sdc_submission_expiry( $id );
	return $expiry ? wp_date( 'j F Y, H:i', $expiry ) : 'Wymaga potwierdzenia daty ostatniego kontaktu';
}
function sdc_submission_retention_fields( $id ) {
	$last = (int) get_post_meta( $id, '_sdc_last_contact_at', true );
	echo '<p><label for="sdc-last-contact"><strong>Ostatni kontakt</strong></label><br><input id="sdc-last-contact" type="datetime-local" name="sdc_last_contact" step="1" max="' . esc_attr( wp_date( 'Y-m-d\\TH:i:s' ) ) . '" value="' . esc_attr( $last ? wp_date( 'Y-m-d\\TH:i:s', $last ) : '' ) . '"></p><p><label><input type="checkbox" name="sdc_contact_now" value="1"> Odnotuj rzeczywisty kontakt teraz</label></p><p class="description">90 dni od ostatniej wiadomości lub rozmowy, także dla spraw otwartych. Czas lokalny witryny. Kontakt przez e-mail lub telefon odnotuj tutaj. Sam odczyt i zmiana statusu nie wydłużają terminu. Starszej sprawie wpisz rzeczywistą datę po sprawdzeniu historii kontaktu.</p>';
}
function sdc_save_retention_dates( $id, $old_status, $new_status ) {
	$old = (int) get_post_meta( $id, '_sdc_last_contact_at', true ); $last = $old;
	if ( '1' === sdc_post( 'sdc_contact_now' ) ) { $last = time(); }
	elseif ( isset( $_POST['sdc_last_contact'] ) ) {
		$raw = sdc_post( 'sdc_last_contact' );
		if ( ! is_string( $raw ) ) { return new WP_Error( 'last_contact', 'Nie zapisano zmian: niepoprawna data ostatniego kontaktu.' ); }
		if ( '' !== $raw ) {
			$format = strlen( $raw ) === 16 ? 'Y-m-d\\TH:i' : 'Y-m-d\\TH:i:s';
			$date = DateTimeImmutable::createFromFormat( '!' . $format, $raw, wp_timezone() );
			if ( ! $date || $date->format( $format ) !== $raw || $date->getTimestamp() > time() || $date->getTimestamp() <= 0 ) { return new WP_Error( 'last_contact', 'Nie zapisano zmian: wpisz poprawną datę ostatniego kontaktu, nie późniejszą niż teraz.' ); }
			$last = $date->getTimestamp();
		} elseif ( $old ) { return new WP_Error( 'last_contact', 'Nie zapisano zmian: nie można usunąć daty ostatniego kontaktu. Wpisz poprawną datę.' ); }
	}
	if ( $last !== $old ) {
		update_post_meta( $id, '_sdc_last_contact_at', $last );
		sdc_audit_log( 'zgloszenie_kontakt', $id, 'Ustalono datę ostatniego kontaktu (UTC): ' . gmdate( 'Y-m-d H:i:s', $last ) );
	}
	$expiry = sdc_submission_expiry( $id );
	if ( $expiry ) { update_post_meta( $id, '_sdc_expires_at', $expiry ); } else { delete_post_meta( $id, '_sdc_expires_at' ); }
	return true;
}

/** Explicit migration: public documents only; never decrypts or closes existing cases. */
function sdc_apply_privacy26() {
	if ( ! current_user_can( 'manage_options' ) ) { return new WP_Error( 'forbidden', 'Brak uprawnień.' ); }
	if ( get_option( 'sdc_privacy26_done' ) ) { return true; }
	$data = sdc_privacy26_data(); $plan = array(); $backup = array();
	foreach ( get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => -1 ) ) as $post ) {
		$source = get_post_meta( $post->ID, '_sdc_source_path', true ) ?: '/' . trim( $post->post_name, '/' ) . '/';
		$lang = get_post_meta( $post->ID, '_sdc_page_lang', true ) ?: 'pl';
		$type = '/polityka-prywatnosci/' === $source ? 'privacy' : ( '/polityka-cookies/' === $source ? 'cookies' : '' );
		if ( ! $type || ! isset( $data['policies'][ $lang ][ $type ] ) ) { continue; }
		if ( ! current_user_can( 'edit_post', $post->ID ) ) { return new WP_Error( 'forbidden', 'Brak dostępu do dokumentu.' ); }
		$privacy_page = 'pl' === $lang ? get_page_by_path( 'polityka-prywatnosci' ) : null;
		$privacy_url = home_url( '/polityka-prywatnosci/' );
		if ( 'pl' !== $lang && function_exists( 'sdc_translation_id' ) ) {
			$original = get_page_by_path( 'polityka-prywatnosci' );
			$translated = $original ? sdc_translation_id( $original->ID, $lang ) : 0;
			if ( $translated ) { $privacy_url = get_permalink( $translated ); }
		}
		$plan[ $post->ID ] = str_replace( '__PRIVACY_URL__', esc_url( $privacy_url ), $data['policies'][ $lang ][ $type ] );
		$backup['pages'][ $post->ID ] = $post->to_array();
	}
	if ( 6 !== count( $plan ) ) { return new WP_Error( 'pages', 'Oczekiwano sześciu stron polityk PL/EN/UA; znaleziono ' . count( $plan ) . '. Niczego nie zmieniono.' ); }
	foreach ( array( 'sdc_privacy_clause', 'sdc_retention_days', 'sdc_notify_email', 'sdc_contact_email', 'sdc_privacy_url', 'sdc_cookie_policy_url' ) as $key ) { $backup['options'][ $key ] = get_option( $key, null ); }
	update_option( 'sdc_privacy26_before', $backup, false );
	foreach ( $plan as $id => $content ) {
		wp_save_post_revision( $id ); $result = wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $content ) ), true );
		if ( is_wp_error( $result ) ) { return $result; }
		update_post_meta( $id, '_sdc_privacy_version', '2026-10-26' );
	}
	update_option( 'sdc_privacy_clause', $data['clause']['pl'], false );
	update_option( 'sdc_privacy_url', '/polityka-prywatnosci/' ); update_option( 'sdc_cookie_policy_url', '/polityka-cookies/' );
	update_option( 'sdc_notify_email', 'info@proukraina.org' ); update_option( 'sdc_contact_email', 'info@proukraina.org' );
	update_option( 'sdc_privacy26_done', array( 'date' => current_time( 'mysql' ), 'by' => get_current_user_id(), 'pages' => array_keys( $plan ) ), false );
	return true;
}
add_action( 'admin_menu', function () { add_management_page( 'RODO i EPUB — 26.10', 'RODO i EPUB — 26.10', 'manage_options', 'sdc-privacy-epub', 'sdc_privacy26_admin' ); } );
function sdc_privacy26_admin() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	echo '<div class="wrap"><h1>RODO i EPUB — 26 października 2026</h1><p>Pełne polityki PL/EN/UA i informacja pod formularzem, dane Stowarzyszenia, hosting nazwa.pl / EOG, kontakt info@proukraina.org. Aktualna retencja zgłoszeń: 90 dni od ostatniego kontaktu (aktualizacja 27.10). Istniejące sprawy nie są otwierane, zamykane ani usuwane przez wdrożenie.</p>';
	foreach ( array( 'sdc_privacy26_done' => array( 'RODO wdrożone.', 'sdc_privacy26', 'Wdroż polityki i klauzulę RODO' ), 'sdc_epub26_done' => array( 'Cztery EPUB-y udostępnione.', 'sdc_epub26', 'Udostępnij cztery zatwierdzone EPUB-y' ) ) as $option => $copy ) {
		if ( get_option( $option ) ) { echo '<div class="notice notice-success inline"><p>' . esc_html( $copy[0] ) . '</p></div>'; }
		else { echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( $copy[1] ) . '">'; wp_nonce_field( $copy[1] ); submit_button( $copy[2] ); echo '</form>'; }
	}
	echo '<p>Umowę powierzenia z hostingiem, upoważnienia i 2FA należy potwierdzić odrębnie. Publikacja polityk nie stanowi dowodu tych czynności.</p></div>';
}
add_action( 'admin_post_sdc_privacy26', function () { check_admin_referer( 'sdc_privacy26' ); $result = sdc_apply_privacy26(); if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); } wp_safe_redirect( admin_url( 'tools.php?page=sdc-privacy-epub' ) ); exit; } );
