<?php
/** Approved help module changes of 27 October 2026. */
defined( 'ABSPATH' ) || exit;

/** Reject header injection, rather than repairing a malformed recipient. */
function sdc_help27_email( $value ) {
	if ( ! is_string( $value ) || preg_match( '/[\r\n]/', $value ) ) { return ''; }
	$value = trim( $value );
	return is_email( $value ) ? sanitize_email( $value ) : '';
}
function sdc_help27_recipients() {
	$emails = array();
	foreach ( array( sdc_option( 'notify_email', 'info@proukraina.org' ), sdc_option( 'notify_email_secondary', 'tetiana.wroblewska@proukraina.org' ) ) as $value ) {
		$email = sdc_help27_email( $value );
		if ( $email ) { $emails[ strtolower( $email ) ] = $email; }
	}
	return array_values( $emails );
}
/** Mail language follows the submitted form, not the administrator's interface locale. */
function sdc_help27_mail_copy( $key ) {
	static $data;
	if ( null === $data ) { $data = json_decode( file_get_contents( SDC_PATH . 'data/help-mail-2026-10-27.json' ), true ); }
	$lang = sdc_current_language();
	return $data[ $lang ][ $key ] ?? $data['pl'][ $key ];
}
function sdc_help27_urgent( $lang = null ) {
	$lang = $lang ?? sdc_current_language();
	$copy = array(
		'pl' => array(
			'Jeśli sprawa jest pilna',
			'Na odwołanie od wypowiedzenia lub rozwiązania umowy o pracę masz co do zasady 21 dni od doręczenia pisma pracodawcy. Nie czekaj na odpowiedź projektu, jeśli biegnie termin do sądu pracy.',
			'Kontakt z zespołem projektu: +48 889 744 607. Możesz też odpowiedzieć na tę wiadomość.',
			'Porady Państwowej Inspekcji Pracy: 801 002 006 / 459 599 000 (pn–pt, 9:00–15:00). Porady w języku ukraińskim: pn–śr 89 333 17 41, czw–pt 22 111 35 88 (9:00–15:00). Koszt połączenia według taryfy operatora.',
			'W razie bezpośredniego zagrożenia życia lub zdrowia dzwoń pod 112.',
		),
		'en' => array(
			'If the matter is urgent',
			'The deadline for challenging a notice of termination or dismissal in the labour court is generally 21 days from receipt of the employer’s letter. Do not wait for the project’s response if a court deadline is running.',
			'Contact the project team: +48 889 744 607. You can also reply to this message.',
			'National Labour Inspectorate advice in Polish: 801 002 006 / 459 599 000 (Mon–Fri, 9:00–15:00 Polish time). Advice in Ukrainian: Mon–Wed 89 333 17 41, Thu–Fri 22 111 35 88 (9:00–15:00 Polish time). Your operator’s call charges apply.',
			'In an immediate threat to life or health, call 112.',
		),
		'uk' => array(
			'Якщо справа термінова',
			'Строк оскарження повідомлення про припинення трудового договору або звільнення в суді праці зазвичай становить 21 день від отримання листа роботодавця. Не чекайте відповіді команди проєкту, якщо спливає строк звернення до суду.',
			'Контакт із командою проєкту: +48 889 744 607. Ви також можете відповісти на цей лист.',
			'Консультації Державної інспекції праці польською: 801 002 006 / 459 599 000 (пн–пт, 9:00–15:00 за польським часом). Українською: пн–ср 89 333 17 41, чт–пт 22 111 35 88 (9:00–15:00 за польським часом). Вартість дзвінка — за тарифом оператора.',
			'У разі безпосередньої загрози життю або здоров’ю телефонуйте 112.',
		),
	);
	return implode( "\n", $copy[ $lang ] ?? $copy['pl'] );
}

/** Only literal public copy is replaced; expert articles and case payloads are excluded. */
function sdc_help27_public_replacements() {
	return array(
		'Prawnik lub psycholog projektu wskazuje możliwe kroki' => 'Zespół projektu wskazuje możliwe kroki',
		'A project lawyer or psychologist outlines possible steps' => 'The project team outlines possible steps',
		'Юрист або психолог проєкту пропонує можливі кроки' => 'Команда проєкту пропонує можливі кроки',
		'zarządzanie różnorodnością — nabór otwarty' => 'zarządzanie różnorodnością — nabór od grudnia 2026',
		'— zarządzanie różnorodnością, nabór otwarty' => '— zarządzanie różnorodnością, nabór od grudnia 2026',
		'diversity management — recruitment open' => 'diversity management — recruitment from December 2026',
		'— diversity management, with recruitment open' => '— diversity management, with recruitment from December 2026',
		'управління різноманіттям — набір відкрито' => 'управління різноманіттям — набір із грудня 2026 року',
		'— управління різноманіттям, набір відкрито' => '— управління різноманіттям, набір із грудня 2026 року',
	);
}
add_filter( 'render_block', function ( $html ) { return strtr( $html, sdc_help27_public_replacements() ); }, 80 );

/** No second-factor gate on cases: the project team withdrew that item on 29 October 2026. */

/** Explicit, repeat-safe migration; it never decrypts a case or guesses a legacy contact date. */
function sdc_apply_help27() {
	if ( ! current_user_can( 'manage_options' ) ) { return new WP_Error( 'forbidden', 'Brak uprawnień.' ); }
	if ( get_option( 'sdc_help27_done' ) ) { return true; }
	$data = sdc_privacy26_data(); $plan = array(); $backup = array( 'pages' => array(), 'options' => array() );
	foreach ( get_posts( array( 'post_type' => array( 'page', 'wp_template', 'wp_template_part', 'wp_block' ), 'post_status' => array( 'publish', 'draft' ), 'numberposts' => -1 ) ) as $post ) {
		$source = get_post_meta( $post->ID, '_sdc_source_path', true ) ?: '/' . trim( $post->post_name, '/' ) . '/';
		$lang = get_post_meta( $post->ID, '_sdc_page_lang', true ) ?: 'pl';
		if ( '/polityka-prywatnosci/' === $source && isset( $data['policies'][ $lang ]['privacy'] ) ) {
			$content = $data['policies'][ $lang ]['privacy'];
		} else { $content = strtr( $post->post_content, sdc_help27_public_replacements() ); }
		if ( $content === $post->post_content ) { continue; }
		if ( ! current_user_can( 'edit_post', $post->ID ) ) { return new WP_Error( 'forbidden', 'Brak dostępu do dokumentu.' ); }
		$backup['pages'][ $post->ID ] = $post->to_array(); $plan[ $post->ID ] = $content;
	}
	$options = array( 'sdc_notify_email' => 'info@proukraina.org', 'sdc_notify_email_secondary' => 'tetiana.wroblewska@proukraina.org', 'sdc_confirmation_reply_to' => 'tetiana.wroblewska@proukraina.org', 'sdc_contact_email' => 'tetiana.wroblewska@proukraina.org', 'sdc_contact_phone' => '+48 889 744 607', 'sdc_retention_days' => 90, 'sdc_privacy_clause' => $data['clause']['pl'] );
	foreach ( $options as $key => $value ) { $backup['options'][ $key ] = get_option( $key, null ); }
	update_option( 'sdc_help27_before', $backup, false );
	foreach ( $plan as $id => $content ) {
		wp_save_post_revision( $id ); $result = wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $content ) ), true );
		if ( is_wp_error( $result ) ) { return $result; }
	}
	foreach ( $options as $key => $value ) { update_option( $key, $value, false ); }
	// Existing timestamps are preserved. Unknown legacy dates require the operator's verification.
	$unknown = 0;
	foreach ( get_posts( array( 'post_type' => 'zgloszenie', 'post_status' => array( 'private', 'draft', 'trash' ), 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
		$expiry = sdc_submission_expiry( $id );
		if ( $expiry ) { update_post_meta( $id, '_sdc_expires_at', $expiry ); }
		else { delete_post_meta( $id, '_sdc_expires_at' ); ++$unknown; }
	}
	update_option( 'sdc_help27_done', array( 'date' => current_time( 'mysql' ), 'by' => get_current_user_id(), 'pages' => array_keys( $plan ), 'legacy_dates_required' => $unknown ), false );
	return true;
}
add_action( 'admin_menu', function () { add_management_page( 'Moduł pomocy — 27.10', 'Moduł pomocy — 27.10', 'manage_options', 'sdc-help-updates', 'sdc_help27_admin' ); } );
function sdc_help27_admin() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	echo '<div class="wrap"><h1>Moduł pomocy — zmiany z 27 października 2026</h1><p>Dwa adresy powiadomień: info@proukraina.org i tetiana.wroblewska@proukraina.org. Reply-To: tetiana.wroblewska@proukraina.org. Retencja zgłoszeń: 90 dni od ostatniego kontaktu, niezależnie od statusu. Potwierdzenia PL/EN/UA zawierają blok pilnej pomocy. Stopka wskazuje Tetianę Wróblewską.</p><p>Logowanie dwuskładnikowe nie jest wymagane do obsługi zgłoszeń (decyzja zespołu projektu z 29.10.2026).</p>';
	$done = get_option( 'sdc_help27_done' );
	if ( $done ) { echo '<p><strong>Zapisano wdrożenie: ' . esc_html( $done['date'] ) . '.</strong> Starsze sprawy bez potwierdzonej daty kontaktu w chwili wdrożenia: ' . (int) $done['legacy_dates_required'] . ' (liczba historyczna). Wpisz rzeczywiste daty w panelu zgłoszeń.</p>'; }
	else {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="sdc_help27">'; wp_nonce_field( 'sdc_help27' ); submit_button( 'Wdróż poprawki modułu pomocy' ); echo '</form>';
	}
	echo '<p>Ostatni kontakt oznacza rzeczywistą wiadomość lub rozmowę. Zespół odnotowuje kontakt spoza formularza w panelu. Sam odczyt sprawy lub zmiana statusu nie przesuwa terminu. Dla starszych spraw nie zgadujemy dat. Usuwanie odbywa się podczas najbliższego uruchomienia zaplanowanego zadania retencji po upływie 90 dni; hosting musi uruchamiać WP-Cron.</p></div>';
}
add_action( 'admin_post_sdc_help27', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Brak uprawnień.', '', array( 'response' => 403 ) ); }
	check_admin_referer( 'sdc_help27' ); $result = sdc_apply_help27();
	if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); }
	wp_safe_redirect( admin_url( 'tools.php?page=sdc-help-updates' ) ); exit;
} );
