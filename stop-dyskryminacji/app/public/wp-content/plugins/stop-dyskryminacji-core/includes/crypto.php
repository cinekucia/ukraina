<?php
/**
 * Szyfrowanie treści zgłoszeń w spoczynku.
 *
 * Wymaganie: § 3 ust. 1 umowy powierzenia przetwarzania danych osobowych
 * („szyfrowanie treści zgłoszeń w spoczynku") oraz kryterium odbioru
 * „Moduł zgłoszeniowy — szyfrowanie w spoczynku i w transmisji".
 * Opis potrzeby może ujawniać dane szczególnych kategorii z art. 9 RODO,
 * dlatego stosujemy szyfrowanie uwierzytelnione.
 *
 * DECYZJE PROJEKTOWE
 * ------------------
 * 1. Szyfrowanie uwierzytelnione (AEAD), nie samo AES-CBC. Bez znacznika
 *    uwierzytelniającego osoba z dostępem do bazy mogłaby modyfikować
 *    szyfrogram, a my nie mielibyśmy jak tego wykryć.
 * 2. Klucz mieszka w wp-config.php, nigdy w bazie danych. Klucz w tabeli
 *    opcji oznacza, że jeden zrzut bazy zawiera i szyfrogram, i klucz —
 *    czyli szyfrowanie nie chroni przed niczym realnym.
 * 3. Brak klucza = moduł nie przyjmuje zgłoszeń. Utrata jednego zgłoszenia
 *    jest mniejszą szkodą niż zapisanie danych z art. 9 RODO otwartym tekstem.
 * 4. Prefiks wersji w szyfrogramie pozwala w przyszłości zmienić algorytm
 *    bez utraty dostępu do starszych zgłoszeń.
 *
 * @package StopDyskryminacjiCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Czy klucz szyfrowania jest poprawnie skonfigurowany.
 *
 * @return bool
 */
function sdc_encryption_key_configured() {
	return null !== sdc_get_encryption_key();
}

/**
 * Klucz szyfrowania (32 bajty) albo null, jeżeli nie skonfigurowano.
 *
 * @return string|null
 */
function sdc_get_encryption_key() {
	static $key = false;

	if ( false !== $key ) {
		return $key;
	}

	$key = null;

	if ( defined( 'SDC_ENCRYPTION_KEY' ) && is_string( SDC_ENCRYPTION_KEY ) ) {
		$decoded = base64_decode( SDC_ENCRYPTION_KEY, true );
		if ( false !== $decoded && 32 === strlen( $decoded ) ) {
			$key = $decoded;
		}
	}

	return $key;
}

/**
 * Wygenerowanie nowego klucza w postaci base64 — do wklejenia w wp-config.php.
 *
 * @return string
 */
function sdc_generate_key_base64() {
	return base64_encode( random_bytes( 32 ) );
}

/**
 * Zaszyfrowanie ciągu znaków.
 *
 * @param string $plaintext Tekst jawny.
 * @return string|WP_Error Szyfrogram w postaci „wersja.base64" albo błąd.
 */
function sdc_encrypt( $plaintext ) {
	$key = sdc_get_encryption_key();

	if ( null === $key ) {
		return new WP_Error(
			'sdc_no_key',
			__( 'Moduł zgłoszeniowy nie ma skonfigurowanego klucza szyfrowania.', 'stop-dyskryminacji-core' )
		);
	}

	// Wariant preferowany: XSalsa20-Poly1305 z rozszerzenia sodium (PHP 7.2+).
	if ( function_exists( 'sodium_crypto_secretbox' ) ) {
		$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = sodium_crypto_secretbox( $plaintext, $nonce, $key );
		return 'v1.' . base64_encode( $nonce . $cipher );
	}

	// Wariant zapasowy: AES-256-GCM, również uwierzytelniony.
	if ( function_exists( 'openssl_encrypt' ) && in_array( 'aes-256-gcm', openssl_get_cipher_methods(), true ) ) {
		$iv     = random_bytes( 12 );
		$tag    = '';
		$cipher = openssl_encrypt( $plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16 );
		if ( false === $cipher ) {
			return new WP_Error( 'sdc_encrypt_failed', __( 'Szyfrowanie nie powiodło się.', 'stop-dyskryminacji-core' ) );
		}
		return 'v2.' . base64_encode( $iv . $tag . $cipher );
	}

	return new WP_Error(
		'sdc_no_cipher',
		__( 'Serwer nie udostępnia ani rozszerzenia sodium, ani AES-256-GCM.', 'stop-dyskryminacji-core' )
	);
}

/**
 * Odszyfrowanie ciągu znaków.
 *
 * @param string $payload Szyfrogram zwrócony przez sdc_encrypt().
 * @return string|WP_Error
 */
function sdc_decrypt( $payload ) {
	$key = sdc_get_encryption_key();

	if ( null === $key ) {
		return new WP_Error( 'sdc_no_key', __( 'Brak klucza szyfrowania — nie można odczytać zgłoszenia.', 'stop-dyskryminacji-core' ) );
	}

	if ( ! is_string( $payload ) || strlen( $payload ) < 4 ) {
		return new WP_Error( 'sdc_bad_payload', __( 'Nieprawidłowa postać zaszyfrowanych danych.', 'stop-dyskryminacji-core' ) );
	}

	$version = substr( $payload, 0, 3 );
	$raw     = base64_decode( substr( $payload, 3 ), true );

	if ( false === $raw ) {
		return new WP_Error( 'sdc_bad_payload', __( 'Nieprawidłowa postać zaszyfrowanych danych.', 'stop-dyskryminacji-core' ) );
	}

	if ( 'v1.' === $version && function_exists( 'sodium_crypto_secretbox_open' ) ) {
		$n = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
		if ( strlen( $raw ) <= $n ) {
			return new WP_Error( 'sdc_bad_payload', __( 'Zaszyfrowane dane są niekompletne.', 'stop-dyskryminacji-core' ) );
		}
		$plain = sodium_crypto_secretbox_open( substr( $raw, $n ), substr( $raw, 0, $n ), $key );
		if ( false === $plain ) {
			// Fałsz oznacza albo zły klucz, albo naruszenie integralności danych.
			return new WP_Error( 'sdc_auth_failed', __( 'Nie udało się zweryfikować integralności zgłoszenia.', 'stop-dyskryminacji-core' ) );
		}
		return $plain;
	}

	if ( 'v2.' === $version && function_exists( 'openssl_decrypt' ) ) {
		if ( strlen( $raw ) <= 28 ) {
			return new WP_Error( 'sdc_bad_payload', __( 'Zaszyfrowane dane są niekompletne.', 'stop-dyskryminacji-core' ) );
		}
		$iv    = substr( $raw, 0, 12 );
		$tag   = substr( $raw, 12, 16 );
		$ct    = substr( $raw, 28 );
		$plain = openssl_decrypt( $ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
		if ( false === $plain ) {
			return new WP_Error( 'sdc_auth_failed', __( 'Nie udało się zweryfikować integralności zgłoszenia.', 'stop-dyskryminacji-core' ) );
		}
		return $plain;
	}

	return new WP_Error( 'sdc_unknown_version', __( 'Nieobsługiwana wersja szyfrogramu.', 'stop-dyskryminacji-core' ) );
}

/**
 * Zaszyfrowanie tablicy danych zgłoszenia.
 *
 * @param array $data Dane.
 * @return string|WP_Error
 */
function sdc_encrypt_array( array $data ) {
	return sdc_encrypt( wp_json_encode( $data ) );
}

/**
 * Odszyfrowanie tablicy danych zgłoszenia.
 *
 * @param string $payload Szyfrogram.
 * @return array|WP_Error
 */
function sdc_decrypt_array( $payload ) {
	$json = sdc_decrypt( $payload );

	if ( is_wp_error( $json ) ) {
		return $json;
	}

	$data = json_decode( $json, true );

	return is_array( $data )
		? $data
		: new WP_Error( 'sdc_bad_json', __( 'Odszyfrowane dane mają nieprawidłową strukturę.', 'stop-dyskryminacji-core' ) );
}

/**
 * Indeks wyszukiwania adresu e-mail (tzw. ślepy indeks).
 *
 * Umowa powierzenia (§ 2) wymaga funkcji wyszukania pojedynczego zgłoszenia —
 * osoba, która prosi o dostęp do danych albo ich usunięcie, zwykle podaje sam
 * adres e-mail, a ten jest w bazie wyłącznie zaszyfrowany. Zapisujemy więc
 * obok skrót HMAC-SHA256 znormalizowanego adresu. Skrót pozwala znaleźć
 * zgłoszenie po dokładnym adresie, ale nie pozwala odtworzyć adresu, a bez
 * klucza z wp-config.php nie da się go nawet policzyć dla zgadywanych adresów.
 *
 * Klucz indeksu jest wyprowadzony z klucza szyfrowania (HKDF) — to inny klucz,
 * więc skrót nie ujawnia niczego o kluczu szyfrującym treść.
 *
 * @param string $email Adres e-mail.
 * @return string Skrót szesnastkowy albo pusty ciąg, gdy brak klucza lub adresu.
 */
function sdc_email_index( $email ) {
	$key   = sdc_get_encryption_key();
	$email = strtolower( trim( (string) $email ) );

	if ( null === $key || '' === $email ) {
		return '';
	}

	$index_key = hash_hkdf( 'sha256', $key, 32, 'sdc-email-index-v1' );

	return hash_hmac( 'sha256', $email, $index_key );
}

/**
 * Usunięcie proponowanego klucza z bazy, gdy klucz jest już w wp-config.php.
 *
 * Ekran ustawień podpowiada gotową linię z kluczem i zapamiętuje ją w opcji,
 * żeby nie zmieniała się przy każdym odświeżeniu. Po wklejeniu tej linii do
 * wp-config.php opcja zawierałaby jednak PRAWDZIWY klucz — a klucz z założenia
 * nie może leżeć w bazie obok szyfrogramów. Dlatego znika przy pierwszym
 * wejściu do panelu po skonfigurowaniu klucza.
 */
function sdc_forget_suggested_key() {
	if ( sdc_encryption_key_configured() && false !== get_option( 'sdc_suggested_key', false ) ) {
		delete_option( 'sdc_suggested_key' );
	}
}
add_action( 'admin_init', 'sdc_forget_suggested_key' );
