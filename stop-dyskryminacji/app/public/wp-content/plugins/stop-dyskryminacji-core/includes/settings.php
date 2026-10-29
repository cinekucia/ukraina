<?php
/**
 * Ekran ustawień platformy.
 *
 * Wszystko, co przy odbiorze trzeba pokazać audytorowi albo Zamawiającemu,
 * jest w jednym miejscu: klucz szyfrowania, GA4, reCAPTCHA, retencja,
 * klauzula RODO i dane kontaktowe.
 *
 * @package StopDyskryminacjiCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pozycja w menu.
 */
function sdc_settings_menu() {
	add_options_page(
		__( 'STOP DYSKRYMINACJI', 'stop-dyskryminacji-core' ),
		__( 'STOP DYSKRYMINACJI', 'stop-dyskryminacji-core' ),
		'manage_options',
		'stop-dyskryminacji',
		'sdc_settings_page'
	);
}
add_action( 'admin_menu', 'sdc_settings_menu' );

/**
 * Definicja pól ustawień.
 *
 * @return array
 */
function sdc_settings_fields() {
	return array(
		'moduł' => array(
			'title'  => __( 'Moduł zgłoszeniowy', 'stop-dyskryminacji-core' ),
			'fields' => array(
				'response_days'   => array( 'label' => __( 'Deklarowany czas odpowiedzi (dni robocze)', 'stop-dyskryminacji-core' ), 'type' => 'number', 'desc' => __( 'Podawany użytkownikowi na stronie i w potwierdzeniu e-mail.', 'stop-dyskryminacji-core' ) ),
				// Retention is fixed by the approved policy: 90 days from last contact; dates are managed per case.
				'notify_email'    => array( 'label' => __( 'Adres powiadomień o nowych zgłoszeniach', 'stop-dyskryminacji-core' ), 'type' => 'email', 'desc' => __( 'Powiadomienie zawiera wyłącznie sygnaturę i odnośnik do panelu — nigdy treści zgłoszenia.', 'stop-dyskryminacji-core' ) ),
				'notify_email_secondary' => array( 'label' => 'Drugi adres powiadomień o zgłoszeniach', 'type' => 'email', 'desc' => 'Ta sama wiadomość bez treści sprawy trafia do obu upoważnionych odbiorców.' ),
				'confirmation_reply_to' => array( 'label' => 'Reply-To w potwierdzeniu dla zgłaszającego', 'type' => 'email', 'desc' => 'Odpowiedź na potwierdzenie trafia na ten adres.' ),
				'contact_email'   => array( 'label' => __( 'Zapasowy adres kontaktowy', 'stop-dyskryminacji-core' ), 'type' => 'email', 'desc' => __( 'Pokazywany, gdy formularz jest niedostępny.', 'stop-dyskryminacji-core' ) ),
				'contact_phone'   => array( 'label' => __( 'Zapasowy telefon kontaktowy', 'stop-dyskryminacji-core' ), 'type' => 'text' ),
				'rate_limit'      => array( 'label' => __( 'Maksimum zgłoszeń z jednego urządzenia na godzinę', 'stop-dyskryminacji-core' ), 'type' => 'number' ),
				'thankyou_page_id'=> array( 'label' => __( 'Strona podziękowania', 'stop-dyskryminacji-core' ), 'type' => 'page' ),
			),
		),
		'rodo' => array(
			'title'  => __( 'Dokumenty RODO', 'stop-dyskryminacji-core' ),
			'fields' => array(
				'privacy_clause'    => array( 'label' => __( 'Klauzula pod modułem zgłoszeniowym', 'stop-dyskryminacji-core' ), 'type' => 'textarea', 'desc' => __( 'Pełne klauzule PL/EN/UA zaktualizowano 27.10.2026. Aktualizacje treści należy uzgadniać z administratorem i wdrażać we wszystkich językach.', 'stop-dyskryminacji-core' ) ),
				'privacy_url'       => array( 'label' => __( 'Adres polityki prywatności', 'stop-dyskryminacji-core' ), 'type' => 'text' ),
				'cookie_policy_url' => array( 'label' => __( 'Adres polityki plików cookie', 'stop-dyskryminacji-core' ), 'type' => 'text' ),
			),
		),
		'jezyki' => array(
			'title'  => __( 'Wersje językowe', 'stop-dyskryminacji-core' ),
			'fields' => array(
				'lang_url_uk'       => array( 'label' => __( 'Adres wersji ukraińskiej', 'stop-dyskryminacji-core' ), 'type' => 'text', 'desc' => __( 'Np. /ua/. Puste pole ukrywa ten język w przełączniku — lepiej nie pokazywać wersji, której nie ma, niż prowadzić do strony 404.', 'stop-dyskryminacji-core' ) ),
				'lang_url_en'       => array( 'label' => __( 'Adres wersji angielskiej', 'stop-dyskryminacji-core' ), 'type' => 'text', 'desc' => __( 'Np. /en/. Puste pole ukrywa ten język.', 'stop-dyskryminacji-core' ) ),
				// „Prosty język" nie jest wersją językową — odnośnik do niego jest zwykłym
				// odnośnikiem w stopce, edytowanym w edytorze witryny, więc nie ma tu pola.
			),
		),
		'analityka' => array(
			'title'  => __( 'Analityka i zabezpieczenia', 'stop-dyskryminacji-core' ),
			'fields' => array(
				'ga4_id'              => array( 'label' => __( 'Identyfikator pomiaru GA4', 'stop-dyskryminacji-core' ), 'type' => 'text', 'desc' => 'Postać G-XXXXXXXXXX. Puste pole wyłącza Google Analytics. Lokalne statystyki i zgodę ustawisz w menu Statystyki.' ),
				// Klucze Google: litery, cyfry, „-" i „_". Wzorzec odrzuca adres e-mail i hasło,
				// którymi przeglądarka potrafi sama wypełnić pole tekstowe stojące przed polem hasła.
				'recaptcha_site_key'  => array( 'label' => __( 'reCAPTCHA v3 — klucz witryny', 'stop-dyskryminacji-core' ), 'type' => 'text', 'pattern' => '/^[A-Za-z0-9_-]{20,100}$/' ),
				'recaptcha_secret'    => array( 'label' => __( 'reCAPTCHA v3 — klucz tajny', 'stop-dyskryminacji-core' ), 'type' => 'password', 'pattern' => '/^[A-Za-z0-9_-]{20,100}$/' ),
				'recaptcha_threshold' => array( 'label' => __( 'Próg akceptacji reCAPTCHA', 'stop-dyskryminacji-core' ), 'type' => 'text', 'desc' => __( 'Od 0.0 do 1.0. Domyślnie 0.5. Wyższa wartość odrzuca więcej zgłoszeń, także prawdziwych.', 'stop-dyskryminacji-core' ) ),
			),
		),
	);
}

/**
 * Rejestracja ustawień.
 */
function sdc_register_settings() {
	foreach ( sdc_settings_fields() as $section ) {
		foreach ( $section['fields'] as $key => $field ) {
			if ( ! empty( $field['pattern'] ) ) {
				$sanitize = function ( $value ) use ( $key, $field ) {
					return sdc_sanitize_pattern_field( $value, 'sdc_' . $key, $field );
				};
			} elseif ( 'email' === $field['type'] ) {
				$sanitize = function ( $value ) use ( $key ) {
					$email = sdc_help27_email( $value );
					if ( '' === $value || $email ) { return $email; }
					add_settings_error( 'sdc_' . $key, 'sdc_invalid_email', 'Niepoprawny adres e-mail. Zostawiono poprzednią wartość.' );
					return (string) get_option( 'sdc_' . $key, '' );
				};
			} else {
				$sanitize = 'textarea' === $field['type'] ? 'wp_kses_post' : 'sanitize_text_field';
			}
			register_setting(
				'sdc_settings',
				'sdc_' . $key,
				array(
					'type'              => 'string',
					'sanitize_callback' => $sanitize,
					'default'           => '',
				)
			);
		}
	}
}
add_action( 'admin_init', 'sdc_register_settings' );

/**
 * Pole o ustalonej postaci. Wartość w innej postaci nie jest zapisywana — zostaje
 * poprzednia, a ekran ustawień pokazuje komunikat.
 *
 * @param mixed  $value  Wartość z formularza.
 * @param string $option Nazwa opcji.
 * @param array  $field  Opis pola z sdc_settings_fields().
 * @return string
 */
function sdc_sanitize_pattern_field( $value, $option, $field ) {
	static $reported = array();

	$value = trim( sanitize_text_field( (string) $value ) );
	if ( '' === $value || preg_match( $field['pattern'], $value ) ) {
		return $value;
	}

	// WordPress wywołuje sanityzację dwa razy, gdy opcji jeszcze nie ma — jeden komunikat wystarczy.
	if ( empty( $reported[ $option ] ) ) {
		$reported[ $option ] = true;
		add_settings_error(
			$option,
			$option . '_invalid',
			sprintf(
				/* translators: %s: nazwa pola */
				__( '%s: to nie jest klucz reCAPTCHA (przeglądarka mogła wpisać tu adres e-mail albo hasło). Zostawiono poprzednią wartość.', 'stop-dyskryminacji-core' ),
				$field['label']
			)
		);
	}
	return (string) get_option( $option, '' );
}

/**
 * Widok ekranu ustawień.
 */
function sdc_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'STOP DYSKRYMINACJI — ustawienia platformy', 'stop-dyskryminacji-core' ); ?></h1>

		<?php sdc_render_key_status(); ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'sdc_settings' ); ?>

			<?php foreach ( sdc_settings_fields() as $section ) : ?>
				<h2><?php echo esc_html( $section['title'] ); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
					<?php foreach ( $section['fields'] as $key => $field ) : ?>
						<?php $name = 'sdc_' . $key; $value = get_option( $name, '' ); ?>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
							<td>
								<?php if ( 'textarea' === $field['type'] ) : ?>
									<textarea id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="8" class="large-text code"><?php echo esc_textarea( $value ); ?></textarea>
								<?php elseif ( 'page' === $field['type'] ) : ?>
									<?php
									wp_dropdown_pages(
										array(
											'name'             => $name,
											'id'               => $name,
											'selected'         => (int) $value,
											'show_option_none' => __( '— domyślna: /dziekujemy/ —', 'stop-dyskryminacji-core' ),
											'option_none_value'=> '',
										)
									);
									?>
								<?php else : ?>
									<?php // Bez autouzupełniania: przeglądarka wpisałaby dane logowania w pole przed polem hasła. ?>
									<input type="<?php echo esc_attr( $field['type'] ); ?>"
										id="<?php echo esc_attr( $name ); ?>"
										name="<?php echo esc_attr( $name ); ?>"
										value="<?php echo esc_attr( $value ); ?>"
										class="regular-text"
										autocomplete="<?php echo 'password' === $field['type'] ? 'new-password' : 'off'; ?>"
										data-1p-ignore data-lpignore="true" data-bwignore="true"
										<?php echo 'number' === $field['type'] ? 'min="1" step="1"' : ''; ?>>
								<?php endif; ?>

								<?php if ( ! empty( $field['desc'] ) ) : ?>
									<p class="description"><?php echo esc_html( $field['desc'] ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endforeach; ?>

			<?php submit_button(); ?>
		</form>

		<hr>
		<h2><?php esc_html_e( 'Oznakowanie projektu', 'stop-dyskryminacji-core' ); ?></h2>
		<?php sdc_render_branding_status(); ?>
	</div>
	<?php
}

/**
 * Stan klucza szyfrowania — z gotową linią do wklejenia w wp-config.php.
 */
function sdc_render_key_status() {
	if ( sdc_encryption_key_configured() ) {
		printf(
			'<div class="notice notice-success"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'Szyfrowanie działa.', 'stop-dyskryminacji-core' ),
			esc_html__( 'Klucz jest wczytany z pliku wp-config.php. Moduł zgłoszeniowy przyjmuje zgłoszenia.', 'stop-dyskryminacji-core' )
		);
		return;
	}

	$suggested = get_option( 'sdc_suggested_key' );
	if ( ! $suggested ) {
		$suggested = sdc_generate_key_base64();
		update_option( 'sdc_suggested_key', $suggested );
	}
	?>
	<div class="notice notice-error">
		<h2 style="margin-top:.6em"><?php esc_html_e( 'Moduł zgłoszeniowy jest wyłączony — brak klucza szyfrowania', 'stop-dyskryminacji-core' ); ?></h2>
		<p>
			<?php esc_html_e( 'Zgłoszenia mogą zawierać dane szczególnych kategorii w rozumieniu art. 9 RODO, dlatego formularz nie przyjmuje ich bez szyfrowania. Dodaj poniższą linię do pliku wp-config.php, powyżej wiersza „That\'s all, stop editing!”:', 'stop-dyskryminacji-core' ); ?>
		</p>
		<p>
			<code style="display:block;padding:12px;background:#1d2327;color:#fff;overflow:auto;user-select:all">
				define( 'SDC_ENCRYPTION_KEY', '<?php echo esc_html( $suggested ); ?>' );
			</code>
		</p>
		<p>
			<strong><?php esc_html_e( 'Zapisz ten klucz w menedżerze haseł Zamawiającego.', 'stop-dyskryminacji-core' ); ?></strong>
			<?php esc_html_e( 'Jego utrata oznacza bezpowrotną utratę dostępu do wszystkich zaszyfrowanych zgłoszeń — odzyskanie ich nie będzie możliwe.', 'stop-dyskryminacji-core' ); ?>
		</p>
		<p class="description">
			<?php esc_html_e( 'Klucz celowo nie jest przechowywany w bazie danych: gdyby tam był, jeden zrzut bazy zawierałby i szyfrogram, i klucz.', 'stop-dyskryminacji-core' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Podgląd i status oznakowania — do zrzutów ekranu przy odbiorze.
 */
function sdc_render_branding_status() {
	$manifest_path = SDC_PATH . 'assets/brand/PROWENIENCJA.json';
	$manifest      = file_exists( $manifest_path ) ? json_decode( file_get_contents( $manifest_path ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions
	?>
	<p><?php esc_html_e( 'Tak wygląda obowiązkowy zestaw znaków renderowany przez platformę:', 'stop-dyskryminacji-core' ); ?></p>
	<div style="background:#fff;border:1px solid #c3c4c7;padding:16px;max-width:900px">
		<?php echo wp_kses( sdc_lockup_img( '600px', 'sd-eu-bar__img', false ), sdc_kses_svg() ); ?>
	</div>

	<table class="widefat striped" style="max-width:900px;margin-top:16px">
		<tbody>
			<tr>
				<th scope="row" style="width:280px"><?php esc_html_e( 'Wersja', 'stop-dyskryminacji-core' ); ?></th>
				<td><?php esc_html_e( 'Pełnokolorowa, pozioma, cztery znaki (wersja achromatyczna na stronie internetowej jest zakazana)', 'stop-dyskryminacji-core' ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Tekst alternatywny', 'stop-dyskryminacji-core' ); ?></th>
				<td><code><?php echo esc_html( SDC_LOCKUP_ALT ); ?></code></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Źródło pliku', 'stop-dyskryminacji-core' ); ?></th>
				<td>
					<?php
					if ( $manifest && isset( $manifest['pl'] ) ) {
						printf(
							'%s<br><code>SHA-256: %s</code>',
							esc_html( $manifest['pl']['source_path'] ),
							esc_html( $manifest['pl']['sha256'] )
						);
					} else {
						esc_html_e( 'Brak pliku proweniencji.', 'stop-dyskryminacji-core' );
					}
					?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Serwowany z', 'stop-dyskryminacji-core' ); ?></th>
				<td><code><?php echo esc_html( SDC_URL . 'assets/brand/' ); ?></code> — <?php esc_html_e( 'serwer projektu, nie adres zewnętrzny', 'stop-dyskryminacji-core' ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Obraz podglądu Open Graph', 'stop-dyskryminacji-core' ); ?></th>
				<td><a href="<?php echo esc_url( SDC_URL . 'assets/brand/og-image-pl.png' ); ?>" target="_blank" rel="noopener">og-image-pl.png</a> — 1200 × 630 px</td>
			</tr>
		</tbody>
	</table>
	<?php
}

/**
 * Odnośnik do ustawień na liście wtyczek.
 *
 * @param array $links Odnośniki.
 * @return array
 */
function sdc_plugin_action_links( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=stop-dyskryminacji' ) ) . '">' . esc_html__( 'Ustawienia', 'stop-dyskryminacji-core' ) . '</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( SDC_FILE ), 'sdc_plugin_action_links' );

/**
 * Stałe ostrzeżenie w panelu, dopóki moduł nie jest gotowy.
 */
function sdc_admin_key_notice() {
	if ( sdc_encryption_key_configured() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( $screen && 'settings_page_stop-dyskryminacji' === $screen->id ) {
		return;   // na ekranie ustawień jest już pełny komunikat
	}

	printf(
		'<div class="notice notice-error"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
		esc_html__( 'STOP DYSKRYMINACJI: moduł zgłoszeniowy jest wyłączony.', 'stop-dyskryminacji-core' ),
		esc_html__( 'Brakuje klucza szyfrowania, więc formularz nie przyjmuje zgłoszeń.', 'stop-dyskryminacji-core' ),
		esc_url( admin_url( 'options-general.php?page=stop-dyskryminacji' ) ),
		esc_html__( 'Dokończ konfigurację', 'stop-dyskryminacji-core' )
	);
}
add_action( 'admin_notices', 'sdc_admin_key_notice' );
