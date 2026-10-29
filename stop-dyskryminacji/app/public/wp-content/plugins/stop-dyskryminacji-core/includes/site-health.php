<?php
/**
 * Testy w narzędziu „Stan witryny".
 *
 * Powód istnienia tego pliku: warunki odbioru z Załącznika nr 2 trzeba
 * udowodnić zrzutami ekranu i raportami. Wbudowany ekran „Narzędzia →
 * Stan witryny" jest naturalnym miejscem, żeby te warunki sprawdzać
 * jednym kliknięciem — i przed odbiorem, i później przy utrzymaniu.
 *
 * @package StopDyskryminacjiCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rejestracja testów.
 *
 * @param array $tests Testy.
 * @return array
 */
function sdc_site_health_tests( $tests ) {
	$tests['direct']['sdc_encryption'] = array(
		'label' => __( 'Szyfrowanie zgłoszeń', 'stop-dyskryminacji-core' ),
		'test'  => 'sdc_test_encryption',
	);
	$tests['direct']['sdc_branding'] = array(
		'label' => __( 'Oznakowanie projektu', 'stop-dyskryminacji-core' ),
		'test'  => 'sdc_test_branding',
	);
	$tests['direct']['sdc_retention'] = array(
		'label' => __( 'Retencja zgłoszeń i rejestr czynności', 'stop-dyskryminacji-core' ),
		'test'  => 'sdc_test_retention',
	);
	$tests['direct']['sdc_privacy'] = array(
		'label' => __( 'Dokumenty RODO platformy', 'stop-dyskryminacji-core' ),
		'test'  => 'sdc_test_privacy',
	);
	$tests['direct']['sdc_two_factor'] = array(
		'label' => __( 'Konta imienne i uwierzytelnianie dwuskładnikowe', 'stop-dyskryminacji-core' ),
		'test'  => 'sdc_test_two_factor',
	);

	return $tests;
}
add_filter( 'site_status_tests', 'sdc_site_health_tests' );

/**
 * Test: konta imienne i uwierzytelnianie dwuskładnikowe.
 *
 * Umowa powierzenia (Załącznik nr 3, § 3 ust. 1): „dostęp wyłącznie przez konta
 * imienne, z uwierzytelnianiem dwuskładnikowym dla dostępu administracyjnego
 * do panelu". Sprawdzane są konta, które mają dostęp administracyjny albo
 * dostęp do zgłoszeń z danymi z art. 9 RODO.
 *
 * Drugi składnik zapewnia wtyczka „Two Factor" rozwijana przez zespół
 * WordPressa (kody z aplikacji uwierzytelniającej, klucze sprzętowe, kody
 * zapasowe) — własna implementacja logowania byłaby ryzykiem, nie oszczędnością.
 * 2FA do bazy danych i panelu hostingu włącza się u dostawcy hostingu.
 *
 * @return array
 */
function sdc_test_two_factor() {
	$label = __( 'Konta imienne i uwierzytelnianie dwuskładnikowe', 'stop-dyskryminacji-core' );
	$users = get_users(
		array(
			'capability__in' => array( 'manage_options', 'read_private_zgloszenia' ),
			'fields'         => array( 'ID', 'user_login', 'display_name' ),
		)
	);

	// Loginy wspólne albo domyślne — zaprzeczenie „konta imiennego".
	$generic = array( 'admin', 'administrator', 'root', 'test', 'tester', 'demo', 'redakcja', 'redaktor', 'biuro', 'office', 'webmaster', 'user', 'projekt', 'stowarzyszenie' );
	$shared  = array_filter( $users, function ( $u ) use ( $generic ) {
		return in_array( strtolower( $u->user_login ), $generic, true );
	} );

	$names = function ( $list ) {
		return implode( ', ', array_map( function ( $u ) {
			return esc_html( $u->user_login );
		}, $list ) );
	};

	$problems = array();
	if ( get_option( 'users_can_register' ) ) {
		$problems[] = __( 'Publiczna rejestracja kont jest włączona (Ustawienia → Ogólne → „Każdy może się zarejestrować"). Na tej platformie konta zakłada wyłącznie administrator — wyłącz tę opcję.', 'stop-dyskryminacji-core' );
	}
	if ( $shared ) {
		$problems[] = sprintf(
			/* translators: %s: lista loginów */
			__( 'Konta o nazwach wspólnych, a nie imiennych: %s. Załóż każdej osobie osobne konto na jej imię i nazwisko, a te usuń.', 'stop-dyskryminacji-core' ),
			$names( $shared )
		);
	}

	if ( ! class_exists( 'Two_Factor_Core' ) ) {
		$problems[] = sprintf(
			/* translators: %s: lista loginów */
			__( 'Brak uwierzytelniania dwuskładnikowego. Zainstaluj wtyczkę „Two Factor" (autor: WordPress.org Contributors) i włącz drugi składnik dla kont: %s.', 'stop-dyskryminacji-core' ),
			$names( $users )
		);
		$action = current_user_can( 'install_plugins' )
			? sprintf( '<p><a href="%s">%s</a></p>', esc_url( admin_url( 'plugin-install.php?s=two-factor&tab=search&type=term' ) ), esc_html__( 'Znajdź wtyczkę „Two Factor"', 'stop-dyskryminacji-core' ) )
			: '';
		return sdc_health_result( $label, 'critical', implode( '</p><p>', $problems ), $action );
	}

	$without = array_filter( $users, function ( $u ) {
		return ! Two_Factor_Core::is_user_using_two_factor( $u->ID );
	} );
	if ( $without ) {
		$problems[] = sprintf(
			/* translators: %s: lista loginów */
			__( 'Konta bez skonfigurowanego drugiego składnika: %s. Każda z tych osób włącza go w swoim profilu (Użytkownicy → Profil → Opcje dwuskładnikowe).', 'stop-dyskryminacji-core' ),
			$names( $without )
		);
	}

	if ( $problems ) {
		return sdc_health_result( $label, 'critical', implode( '</p><p>', $problems ) );
	}

	return sdc_health_result(
		$label,
		'good',
		sprintf(
			/* translators: %d: liczba kont */
			esc_html__( 'Wszystkie konta z dostępem administracyjnym albo do zgłoszeń (%d) są imienne i mają włączony drugi składnik logowania.', 'stop-dyskryminacji-core' ),
			count( $users )
		)
	);
}

/**
 * Szkielet wyniku testu.
 *
 * @param string $label  Nagłówek.
 * @param string $status good | recommended | critical.
 * @param string $body   Treść HTML.
 * @param string $action Odnośnik działania.
 * @return array
 */
function sdc_health_result( $label, $status, $body, $action = '' ) {
	$colors = array( 'good' => 'green', 'recommended' => 'orange', 'critical' => 'red' );

	return array(
		'label'       => $label,
		'status'      => $status,
		'badge'       => array( 'label' => __( 'STOP DYSKRYMINACJI', 'stop-dyskryminacji-core' ), 'color' => $colors[ $status ] ?? 'gray' ),
		'description' => '<p>' . $body . '</p>',
		'actions'     => $action,
		'test'        => 'sdc',
	);
}

/**
 * Test: klucz szyfrowania i dostępny algorytm.
 *
 * @return array
 */
function sdc_test_encryption() {
	$action = sprintf(
		'<p><a href="%s">%s</a></p>',
		esc_url( admin_url( 'options-general.php?page=stop-dyskryminacji' ) ),
		esc_html__( 'Otwórz ustawienia platformy', 'stop-dyskryminacji-core' )
	);

	if ( ! sdc_encryption_key_configured() ) {
		return sdc_health_result(
			__( 'Moduł zgłoszeniowy nie przyjmuje zgłoszeń', 'stop-dyskryminacji-core' ),
			'critical',
			esc_html__( 'Brak stałej SDC_ENCRYPTION_KEY w pliku wp-config.php. Zgłoszenia mogą zawierać dane z art. 9 RODO, więc formularz jest wyłączony do czasu skonfigurowania klucza.', 'stop-dyskryminacji-core' ),
			$action
		);
	}

	$algo = function_exists( 'sodium_crypto_secretbox' )
		? 'XSalsa20-Poly1305 (sodium)'
		: 'AES-256-GCM (OpenSSL)';

	// Test praktyczny: szyfrujemy i odszyfrowujemy kontrolny ciąg znaków.
	$probe   = 'kontrola-' . wp_generate_password( 12, false );
	$cipher  = sdc_encrypt( $probe );
	$back    = is_wp_error( $cipher ) ? $cipher : sdc_decrypt( $cipher );
	$working = ! is_wp_error( $back ) && hash_equals( $probe, (string) $back );

	if ( ! $working ) {
		return sdc_health_result(
			__( 'Szyfrowanie nie działa poprawnie', 'stop-dyskryminacji-core' ),
			'critical',
			esc_html__( 'Klucz jest ustawiony, ale próba zaszyfrowania i odszyfrowania danych kontrolnych nie powiodła się.', 'stop-dyskryminacji-core' ),
			$action
		);
	}

	return sdc_health_result(
		__( 'Zgłoszenia są szyfrowane', 'stop-dyskryminacji-core' ),
		'good',
		sprintf(
			/* translators: %s: nazwa algorytmu */
			esc_html__( 'Szyfrowanie uwierzytelnione działa. Algorytm: %s. Klucz jest wczytywany z wp-config.php, poza bazą danych.', 'stop-dyskryminacji-core' ),
			esc_html( $algo )
		)
	);
}

/**
 * Test: pliki oznakowania na miejscu.
 *
 * @return array
 */
function sdc_test_branding() {
	$required = array(
		'zestawienie-znakow-pl-original.png',
		'zestawienie-znakow-pl-900.png',
		'zestawienie-znakow-pl-1800.png',
		'og-image-pl.png',
	);

	$missing = array();
	foreach ( $required as $file ) {
		if ( ! file_exists( SDC_PATH . 'assets/brand/' . $file ) ) {
			$missing[] = $file;
		}
	}

	if ( $missing ) {
		return sdc_health_result(
			__( 'Brakuje plików oznakowania', 'stop-dyskryminacji-core' ),
			'critical',
			esc_html__( 'Nie znaleziono plików: ', 'stop-dyskryminacji-core' ) . '<code>' . esc_html( implode( ', ', $missing ) ) . '</code>. ' .
			esc_html__( 'Brak zestawu znaków oznacza pomniejszenie dofinansowania o 1 956,47 zł.', 'stop-dyskryminacji-core' )
		);
	}

	return sdc_health_result(
		__( 'Oznakowanie projektu jest kompletne', 'stop-dyskryminacji-core' ),
		'good',
		esc_html__( 'Zestaw czterech znaków w wersji pełnokolorowej jest serwowany z serwera projektu i dołączony do obrazu podglądu Open Graph 1200 × 630 px.', 'stop-dyskryminacji-core' )
	);
}

/**
 * Test: harmonogram retencji i tabela rejestru.
 *
 * @return array
 */
function sdc_test_retention() {
	global $wpdb;

	$scheduled = wp_next_scheduled( 'sdc_daily_maintenance' );
	$table     = sdc_audit_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;

	if ( ! $exists ) {
		return sdc_health_result(
			__( 'Brak tabeli rejestru czynności', 'stop-dyskryminacji-core' ),
			'critical',
			esc_html__( 'Tabela rejestru nie istnieje. Wyłącz i włącz ponownie wtyczkę, aby ją utworzyć.', 'stop-dyskryminacji-core' )
		);
	}

	if ( ! $scheduled ) {
		return sdc_health_result(
			__( 'Automatyczna retencja nie jest zaplanowana', 'stop-dyskryminacji-core' ),
			'recommended',
			esc_html__( 'Zadanie cykliczne usuwające zgłoszenia po okresie retencji nie jest zaplanowane. Sprawdź, czy WP-Cron działa na tym hostingu.', 'stop-dyskryminacji-core' )
		);
	}

	return sdc_health_result(
		__( 'Retencja i rejestr działają', 'stop-dyskryminacji-core' ),
		'good',
		sprintf(
			/* translators: 1: liczba dni retencji zgłoszeń, 2: data następnego uruchomienia */
			esc_html__( 'Zgłoszenia są usuwane 90 dni od ostatniego kontaktu, niezależnie od statusu. Starsze sprawy bez daty kontaktu wymagają weryfikacji operatora. Rejestr czynności: minimum 12 miesięcy. Następne uruchomienie: %2$s.', 'stop-dyskryminacji-core' ),
			(int) sdc_retention_days(),
			esc_html( wp_date( 'j F Y, H:i', $scheduled ) )
		)
	);
}

/**
 * Test: opublikowane dokumenty RODO.
 *
 * @return array
 */
function sdc_test_privacy() {
	$problems = array();

	if ( '' === trim( (string) sdc_option( 'privacy_clause', '' ) ) ) {
		$problems[] = __( 'nie wprowadzono klauzuli pod modułem zgłoszeniowym', 'stop-dyskryminacji-core' );
	}

	foreach ( array( 'privacy_url' => __( 'polityka prywatności', 'stop-dyskryminacji-core' ), 'cookie_policy_url' => __( 'polityka plików cookie', 'stop-dyskryminacji-core' ) ) as $key => $label ) {
		$url = (string) sdc_option( $key, '' );
		if ( '' === $url || ! get_page_by_path( trim( wp_parse_url( $url, PHP_URL_PATH ) ?? '', '/' ) ) ) {
			/* translators: %s: nazwa dokumentu */
			$problems[] = sprintf( __( 'nie znaleziono strony: %s', 'stop-dyskryminacji-core' ), $label );
		}
	}

	if ( $problems ) {
		return sdc_health_result(
			__( 'Dokumenty RODO wymagają uzupełnienia', 'stop-dyskryminacji-core' ),
			'recommended',
			esc_html__( 'Do odbioru potrzebne są: polityka prywatności, klauzula pod modułem zgłoszeniowym w pełnym brzmieniu i polityka plików cookie. Braki: ', 'stop-dyskryminacji-core' ) . esc_html( implode( '; ', $problems ) ) . '.',
			sprintf( '<p><a href="%s">%s</a></p>', esc_url( admin_url( 'options-general.php?page=stop-dyskryminacji' ) ), esc_html__( 'Uzupełnij w ustawieniach', 'stop-dyskryminacji-core' ) )
		);
	}

	return sdc_health_result(
		__( 'Dokumenty RODO są opublikowane', 'stop-dyskryminacji-core' ),
		'good',
		esc_html__( 'Polityka prywatności, klauzula informacyjna i polityka plików cookie są na miejscu.', 'stop-dyskryminacji-core' )
	);
}
