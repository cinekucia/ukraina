<?php
/**
 * Moduł zgłoszeniowy „Potrzebuję pomocy".
 *
 * Etap 6 zakresu obowiązków. Kryterium odbioru: „Pola: imię, e-mail, telefon,
 * opis potrzeby. Szyfrowanie w spoczynku i w transmisji, reCAPTCHA,
 * zdefiniowana retencja, rejestr czynności".
 *
 * Ponad wymagane minimum wdrożone są elementy rekomendacji nr 2 z benchmarku
 * (Antidiskriminierungsstelle DE, Unia BE, LDO NO): wybór roli zgłaszającego,
 * możliwość zgłoszenia bez danych osobowych, automatyczne potwierdzenie
 * i podany wprost czas reakcji.
 *
 * ZAŁOŻENIA, KTÓRE WARTO ZNAĆ PRZED ZMIANĄ TEGO PLIKU
 * ---------------------------------------------------
 * 1. Formularz działa bez JavaScriptu. Osoba w kryzysie może korzystać ze
 *    starej przeglądarki, czytnika ekranu albo trybu oszczędzania danych.
 * 2. Brak klucza szyfrowania blokuje przyjmowanie zgłoszeń. Zapisanie danych
 *    z art. 9 RODO otwartym tekstem jest gorsze niż odrzucenie formularza —
 *    użytkownik dostaje wtedy alternatywne drogi kontaktu.
 * 3. Adres IP nie jest zapisywany. Do ograniczania nadużyć wystarczy skrót
 *    z solą (helpers.php), z którego nie da się odtworzyć adresu.
 * 4. Treść zgłoszenia nie trafia do tytułu wpisu ani do żadnego pola
 *    przeszukiwanego przez panel — tytuł to sama sygnatura.
 *
 * @package StopDyskryminacjiCore
 */

defined( 'ABSPATH' ) || exit;

const SDC_FORM_ACTION = 'sdc_submit_help';

/**
 * Czy moduł jest gotowy do przyjmowania zgłoszeń.
 *
 * @return bool
 */
function sdc_submissions_ready() {
	return sdc_encryption_key_configured();
}

/* -------------------------------------------------------------------------
 * 1. Renderowanie formularza
 * ---------------------------------------------------------------------- */

/**
 * Formularz modułu zgłoszeniowego.
 *
 * @param array $attributes Atrybuty bloku.
 * @return string
 */
function sdc_render_help_form( $attributes = array() ) {
	$token  = sdc_form_state_token( $_GET['sdc'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$state  = $attributes['_sdc_state'] ?? ( $token ? sdc_load_form_state( $token ) : false );
	$errors = ( is_array( $state ) && isset( $state['errors'] ) ) ? $state['errors'] : array();
	$old    = ( is_array( $state ) && isset( $state['old'] ) ) ? $state['old'] : array();
	if ( isset( $_GET['sdc'] ) && ! $state ) {
		$errors['ogolny'] = __( 'Nie można odtworzyć zapisanych danych w tej przeglądarce. W razie wcześniejszej wysyłki sprawdź potwierdzenie przed ponownym zgłoszeniem.', 'stop-dyskryminacji-core' );
	}

	$val = function ( $key, $default = '' ) use ( $old ) {
		return isset( $old[ $key ] ) ? $old[ $key ] : $default;
	};
	$err = function ( $key ) use ( $errors ) {
		return isset( $errors[ $key ] ) ? $errors[ $key ] : '';
	};

	ob_start();

	if ( ! sdc_submissions_ready() && ! isset( $attributes['_sdc_state'] ) ) {
		sdc_render_form_unavailable();
		return ob_get_clean();
	}
	?>
	<div class="sd-help-form-wrap">

		<?php if ( $errors ) : ?>
			<?php /* Podsumowanie błędów jako pierwszy element i z fokusem — WCAG 3.3.1. */ ?>
			<div class="sd-error-summary" role="alert" tabindex="-1" id="sd-errors">
				<h2><?php esc_html_e( 'Formularza nie udało się wysłać', 'stop-dyskryminacji-core' ); ?></h2>
				<ul>
					<?php foreach ( $errors as $field => $message ) : ?>
						<?php
						// Błąd ogólny (np. wygasła sesja) nie ma swojego pola — prowadzi do formularza.
						$anchor = 'ogolny' === $field ? 'sd-help-form' : 'sdc-' . $field;
						?>
						<li><a href="#<?php echo esc_attr( $anchor ); ?>"><?php echo esc_html( $message ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<form class="sd-form" id="sd-help-form" method="post" novalidate
			action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

			<input type="hidden" name="action" value="<?php echo esc_attr( SDC_FORM_ACTION ); ?>">
			<input type="hidden" name="sdc_from" value="<?php echo esc_attr( home_url( add_query_arg( array() ) ) ); ?>">
			<input type="hidden" name="sdc_ts" value="<?php echo esc_attr( time() ); ?>">
			<input type="hidden" name="sdc_state" value="<?php echo esc_attr( $token ); ?>">
			<?php wp_nonce_field( SDC_FORM_ACTION, 'sdc_nonce' ); ?>
			<?php if ( current_user_can( 'manage_options' ) && '1' === ( $_GET['sdc_test'] ?? '' ) ) { wp_nonce_field( 'sdc_admin_form_test', 'sdc_test_nonce' ); } ?>

			<?php /* Pułapka na roboty. Ukryta wizualnie i przed czytnikami ekranu. */ ?>
			<div class="sd-sr-only" aria-hidden="true">
				<label for="sdc-website"><?php esc_html_e( 'Zostaw to pole puste', 'stop-dyskryminacji-core' ); ?></label>
				<input type="text" id="sdc-website" name="sdc_website" tabindex="-1" autocomplete="off" value="">
			</div>

			<?php /* WCAG 3.3.2: znaczenie gwiazdki musi być napisane, nie tylko domyślne. */ ?>
			<p class="sd-form__required-note"><?php esc_html_e( 'Pola oznaczone gwiazdką (*) są obowiązkowe.', 'stop-dyskryminacji-core' ); ?></p>

			<!-- Rola zgłaszającego -->
			<fieldset class="sd-fieldset">
				<legend><?php esc_html_e( 'Kim jesteś w tej sprawie?', 'stop-dyskryminacji-core' ); ?></legend>
				<?php
				$current_role = $val( 'rola', 'osoba' );
				foreach ( sdc_reporter_roles() as $key => $label ) :
					?>
					<label class="sd-option">
						<input type="radio" name="sdc_rola" value="<?php echo esc_attr( $key ); ?>"
							<?php checked( $current_role, $key ); ?>>
						<span class="sd-option__title"><?php echo esc_html( $label ); ?></span>
					</label>
				<?php endforeach; ?>
			</fieldset>

			<!-- Imię -->
			<div class="sd-field <?php echo $err( 'imie' ) ? 'sd-field--error' : ''; ?>">
				<label for="sdc-imie">
					<?php esc_html_e( 'Jak mamy się do Ciebie zwracać?', 'stop-dyskryminacji-core' ); ?>
					
				</label>
				<p class="sd-field__hint" id="sdc-imie-hint">
					<?php echo esc_html( sdc_privacy26_copy( 'name_hint' ) ); ?>
				</p>
				<input type="text" class="sd-input" id="sdc-imie" name="sdc_imie"
					maxlength="80" autocomplete="nickname"
					value="<?php echo esc_attr( $val( 'imie' ) ); ?>"
					aria-describedby="sdc-imie-hint<?php echo $err( 'imie' ) ? ' sdc-imie-error' : ''; ?>"
					<?php echo $err( 'imie' ) ? 'aria-invalid="true"' : ''; ?>>
				<?php if ( $err( 'imie' ) ) : ?>
					<p class="sd-field__error" id="sdc-imie-error"><?php echo esc_html( $err( 'imie' ) ); ?></p>
				<?php endif; ?>
			</div>

			<!-- E-mail -->
			<div class="sd-field <?php echo $err( 'email' ) ? 'sd-field--error' : ''; ?>">
				<label for="sdc-email">
					<?php esc_html_e( 'Adres e-mail', 'stop-dyskryminacji-core' ); ?>
					
				</label>
				<p class="sd-field__hint" id="sdc-email-hint">
					<?php echo esc_html( sdc_privacy26_copy( 'email_hint' ) ); ?>
				</p>
				<input type="email" class="sd-input" id="sdc-email" name="sdc_email"
					maxlength="150" autocomplete="email" spellcheck="false"
					value="<?php echo esc_attr( $val( 'email' ) ); ?>"
					aria-describedby="sdc-email-hint<?php echo $err( 'email' ) ? ' sdc-email-error' : ''; ?>"
					<?php echo $err( 'email' ) ? 'aria-invalid="true"' : ''; ?>>
				<?php if ( $err( 'email' ) ) : ?>
					<p class="sd-field__error" id="sdc-email-error"><?php echo esc_html( $err( 'email' ) ); ?></p>
				<?php endif; ?>
			</div>

			<!-- Telefon -->
			<div class="sd-field <?php echo $err( 'telefon' ) ? 'sd-field--error' : ''; ?>">
				<label for="sdc-telefon"><?php esc_html_e( 'Telefon', 'stop-dyskryminacji-core' ); ?></label>
				<p class="sd-field__hint" id="sdc-telefon-hint">
					<?php echo esc_html( sdc_privacy26_copy( 'phone_hint' ) ); ?>
				</p>
				<input type="tel" class="sd-input" id="sdc-telefon" name="sdc_telefon"
					maxlength="30" autocomplete="tel"
					value="<?php echo esc_attr( $val( 'telefon' ) ); ?>"
					aria-describedby="sdc-telefon-hint<?php echo $err( 'telefon' ) ? ' sdc-telefon-error' : ''; ?>" <?php echo $err( 'telefon' ) ? 'aria-invalid="true"' : ''; ?>>
				<?php if ( $err( 'telefon' ) ) : ?><p class="sd-field__error" id="sdc-telefon-error"><?php echo esc_html( $err( 'telefon' ) ); ?></p><?php endif; ?>
			</div>

			<!-- Przesłanka -->
			<div class="sd-field">
				<label for="sdc-przeslanka"><?php esc_html_e( 'Czego dotyczyło nierówne traktowanie?', 'stop-dyskryminacji-core' ); ?></label>
				<p class="sd-field__hint" id="sdc-przeslanka-hint">
					<?php esc_html_e( 'Jeżeli nie masz pewności, wybierz „Nie wiem". To nie wpływa na sposób rozpatrzenia sprawy.', 'stop-dyskryminacji-core' ); ?>
				</p>
				<select class="sd-select" id="sdc-przeslanka" name="sdc_przeslanka" aria-describedby="sdc-przeslanka-hint">
					<?php foreach ( sdc_grounds() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $val( 'przeslanka', 'nie-wiem' ), $key ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<!-- Opis -->
			<div class="sd-field <?php echo $err( 'opis' ) ? 'sd-field--error' : ''; ?>">
				<label for="sdc-opis">
					<?php esc_html_e( 'Opisz, co się stało', 'stop-dyskryminacji-core' ); ?>
					<span class="sd-required" aria-hidden="true">*</span>
				</label>
				<p class="sd-field__hint" id="sdc-opis-hint">
					<?php esc_html_e( 'Własnymi słowami. Pomagają: kiedy to się stało, kto brał udział, czy są świadkowie lub dokumenty. Nie musisz używać języka prawniczego.', 'stop-dyskryminacji-core' ); ?>
					<?php esc_html_e( 'Wpisz od 20 do 8000 znaków.', 'stop-dyskryminacji-core' ); ?>
				</p>
				<textarea class="sd-textarea" id="sdc-opis" name="sdc_opis" required
					minlength="20" maxlength="8000" rows="9"
					aria-describedby="sdc-opis-hint<?php echo $err( 'opis' ) ? ' sdc-opis-error' : ''; ?>"
					<?php echo $err( 'opis' ) ? 'aria-invalid="true"' : ''; ?>><?php echo esc_textarea( $val( 'opis' ) ); ?></textarea>
				<?php if ( $err( 'opis' ) ) : ?>
					<p class="sd-field__error" id="sdc-opis-error"><?php echo esc_html( $err( 'opis' ) ); ?></p>
				<?php endif; ?>
			</div>

			<!-- Zgoda na przetwarzanie -->
			<div class="sd-field <?php echo $err( 'zgoda' ) ? 'sd-field--error' : ''; ?>">
				<div class="sd-option">
					<input type="checkbox" id="sdc-zgoda" name="sdc_zgoda" value="1" required
						<?php checked( $val( 'zgoda' ), '1' ); ?>
						aria-describedby="sdc-zgoda-hint<?php echo $err( 'zgoda' ) ? ' sdc-zgoda-error' : ''; ?>"
						<?php echo $err( 'zgoda' ) ? 'aria-invalid="true"' : ''; ?>>
					<span>
						<label for="sdc-zgoda" class="sd-option__title"><?php echo esc_html( sdc_privacy26_copy( 'ack_label' ) ); ?> <span class="sd-required" aria-hidden="true">*</span></label>
						<span class="sd-option__desc" id="sdc-zgoda-hint">
							<?php
							printf(
								/* translators: %s: adres polityki prywatności */
								sdc_privacy26_copy( 'ack_hint' ),
								// Na stronie UA/EN odnośnik mówi, że polityka jest po polsku (languages.php).
								sdc_document_link( sdc_option( 'privacy_url', '/polityka-prywatnosci/' ), __( 'polityką prywatności', 'stop-dyskryminacji-core' ) )
							);
							?>
						</span>
					</span>
				</div>
				<?php if ( $err( 'zgoda' ) ) : ?>
					<p class="sd-field__error" id="sdc-zgoda-error"><?php echo esc_html( $err( 'zgoda' ) ); ?></p>
				<?php endif; ?>
			</div>

			<div class="sd-field <?php echo $err( 'sensitive' ) ? 'sd-field--error' : ''; ?>">
				<div class="sd-option"><input type="checkbox" id="sdc-sensitive" name="sdc_sensitive" value="1" <?php checked( $val( 'sensitive' ), '1' ); ?> aria-describedby="sdc-sensitive-hint<?php echo $err( 'sensitive' ) ? ' sdc-sensitive-error' : ''; ?>" <?php echo $err( 'sensitive' ) ? 'aria-invalid="true"' : ''; ?>>
				<span><label for="sdc-sensitive" class="sd-option__title"><?php echo esc_html( sdc_privacy26_copy( 'sensitive_label' ) ); ?></label><span id="sdc-sensitive-hint" class="sd-option__desc"><?php echo esc_html( sdc_privacy26_copy( 'sensitive_hint' ) ); ?></span></span></div>
				<?php if ( $err( 'sensitive' ) ) : ?><p class="sd-field__error" id="sdc-sensitive-error"><?php echo esc_html( $err( 'sensitive' ) ); ?></p><?php endif; ?>
			</div>
			<?php sdc_render_recaptcha_field(); ?>

			<div>
				<button type="submit" class="sd-btn sd-btn--primary sd-btn--lg">
					<?php esc_html_e( 'Wyślij zgłoszenie', 'stop-dyskryminacji-core' ); ?>
				</button>
			</div>

			<?php sdc_render_privacy_clause(); ?>

		</form>
	</div>
	<?php

	// Fokus na podsumowaniu błędów po nieudanej wysyłce.
	if ( $errors ) {
		echo '<script>document.getElementById("sd-errors")&&document.getElementById("sd-errors").focus();</script>';
	}

	return ob_get_clean();
}

/**
 * Komunikat, gdy moduł nie jest skonfigurowany.
 *
 * Osoba szukająca pomocy nie może zostać z pustą stroną — dostaje
 * alternatywne drogi kontaktu.
 */
function sdc_render_form_unavailable() {
	$email = sdc_option( 'contact_email', get_option( 'admin_email' ) );
	$phone = sdc_option( 'contact_phone', '' );
	?>
	<div class="sd-callout sd-callout--warning" role="alert">
		<span aria-hidden="true"><?php echo wp_kses( sdc_icon( 'alert', 24 ), sdc_kses_svg() ); ?></span>
		<div>
			<span class="sd-callout__label"><?php esc_html_e( 'Formularz jest chwilowo niedostępny', 'stop-dyskryminacji-core' ); ?></span>
			<p>
				<?php esc_html_e( 'Nie chcemy przyjmować Twojego zgłoszenia, dopóki nie możemy go bezpiecznie zaszyfrować. W tym czasie napisz do nas bezpośrednio:', 'stop-dyskryminacji-core' ); ?>
			</p>
			<p>
				<a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
				<?php if ( $phone ) : ?>
					· <a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a>
				<?php endif; ?>
			</p>
		</div>
	</div>
	<?php
	if ( current_user_can( 'manage_options' ) ) {
		echo '<div class="sd-callout sd-callout--info"><div><span class="sd-callout__label">Informacja dla administratora</span><p>';
		esc_html_e( 'Brakuje stałej SDC_ENCRYPTION_KEY w pliku wp-config.php. Gotową linię do wklejenia znajdziesz w Ustawienia → STOP DYSKRYMINACJI.', 'stop-dyskryminacji-core' );
		echo '</p></div></div>';
	}
}

/**
 * Klauzula informacyjna RODO pod formularzem.
 *
 * Kryterium odbioru wymaga klauzuli „w pełnym brzmieniu". Treść pochodzi
 * z Załącznika nr 11 i jest wprowadzana przez Zamawiającego w ustawieniach —
 * nie wolno jej modyfikować bez jego pisemnej zgody (§ 7 ust. 7 umowy).
 */
function sdc_render_privacy_clause() {
	echo '<section class="sd-privacy-note" aria-labelledby="sdc-privacy-title"><h2 id="sdc-privacy-title">' . esc_html( sdc_privacy26_copy( 'clause_title' ) ) . '</h2>' . wp_kses_post( sdc_privacy26_clause() ) . '</section>';
}

/* -------------------------------------------------------------------------
 * 2. reCAPTCHA
 * ---------------------------------------------------------------------- */

/**
 * Pole reCAPTCHA v3 (niewidoczne).
 *
 * Wybór v3 zamiast v2 jest podyktowany dostępnością: zagadka obrazkowa jest
 * barierą dla osób niewidomych i osób z trudnościami poznawczymi, a serwis
 * ma spełniać WCAG 2.1 AA. Gdy JavaScript jest wyłączony, token nie powstaje —
 * zgłoszenie przechodzi wtedy przez pułapkę i pomiar czasu, a w panelu dostaje
 * znacznik „do weryfikacji ręcznej" zamiast być odrzucone.
 */
function sdc_render_recaptcha_field() {
	$site_key = trim( (string) sdc_option( 'recaptcha_site_key', '' ) );

	if ( '' === $site_key ) {
		return;
	}
	?>
	<input type="hidden" name="sdc_recaptcha_token" id="sdc-recaptcha-token" value="">
	<p class="sd-field__hint">
		<?php
		printf(
			/* translators: 1,2: znaczniki odnośników */
			esc_html__( 'Formularz jest chroniony przez reCAPTCHA. Obowiązują %1$spolityka prywatności%2$s i %3$swarunki usługi%4$s Google.', 'stop-dyskryminacji-core' ),
			'<a href="https://policies.google.com/privacy" rel="noopener">', '</a>',
			'<a href="https://policies.google.com/terms" rel="noopener">', '</a>'
		);
		?>
	</p>
	<script src="https://www.google.com/recaptcha/api.js?render=<?php echo esc_attr( $site_key ); ?>" defer></script>
	<script>
	document.addEventListener('DOMContentLoaded', function () {
		var form = document.getElementById('sd-help-form');
		if (!form) { return; }
		form.addEventListener('submit', function (e) {
			var field = document.getElementById('sdc-recaptcha-token');
			if (!field || field.value || typeof grecaptcha === 'undefined') { return; }
			e.preventDefault();
			grecaptcha.ready(function () {
				grecaptcha.execute('<?php echo esc_js( $site_key ); ?>', { action: 'zgloszenie' })
					.then(function (token) { field.value = token; form.submit(); })
					.catch(function () { form.submit(); });   // awaria Google nie blokuje zgłoszenia
			});
		});
	});
	</script>
	<?php
}

/**
 * Weryfikacja tokenu reCAPTCHA po stronie serwera.
 *
 * Weryfikacja wyłącznie po stronie przeglądarki nie daje nic — token trzeba
 * sprawdzić u Google, inaczej wystarczy wysłać żądanie z pominięciem strony.
 *
 * @param string $token Token z formularza.
 * @return array Tablica: verified (bool), score (float|null), reason (string).
 */
function sdc_verify_recaptcha( $token ) {
	$secret = trim( (string) sdc_option( 'recaptcha_secret', '' ) );

	if ( '' === $secret ) {
		return array( 'verified' => true, 'score' => null, 'reason' => 'nieskonfigurowana' );
	}

	if ( '' === $token ) {
		return array( 'verified' => false, 'score' => null, 'reason' => 'brak tokenu' );
	}

	$response = wp_remote_post(
		'https://www.google.com/recaptcha/api/siteverify',
		array(
			'timeout' => 8,
			'body'    => array(
				'secret'   => $secret,
				'response' => $token,
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		// Awaria po stronie Google nie może odciąć nikogo od pomocy —
		// zgłoszenie przechodzi, ale z adnotacją do weryfikacji ręcznej.
		return array( 'verified' => true, 'score' => null, 'reason' => 'usługa niedostępna' );
	}

	$body  = json_decode( wp_remote_retrieve_body( $response ), true );
	$ok    = ! empty( $body['success'] );
	$score = isset( $body['score'] ) ? (float) $body['score'] : null;
	$min   = (float) sdc_option( 'recaptcha_threshold', 0.5 );

	if ( $ok && null !== $score && $score < $min ) {
		return array( 'verified' => false, 'score' => $score, 'reason' => 'wynik poniżej progu' );
	}

	return array( 'verified' => $ok, 'score' => $score, 'reason' => $ok ? 'ok' : 'odrzucony przez Google' );
}

/* -------------------------------------------------------------------------
 * 3. Obsługa wysyłki
 * ---------------------------------------------------------------------- */

/**
 * Przyjęcie zgłoszenia.
 */
function sdc_submission_data() {
	$data = array();
	foreach ( array( 'imie' => 'text', 'email' => 'text', 'telefon' => 'text', 'opis' => 'textarea', 'rola' => 'key', 'przeslanka' => 'key', 'zgoda' => 'text', 'sensitive' => 'text' ) as $field => $format ) {
		$key = 'sdc_' . $field;
		$data[ $field ] = isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? sdc_post( $key, $format ) : '';
	}
	return $data;
}

function sdc_handle_submission() {
	sdc_form_private_headers();
	$referer = wp_get_referer() ? wp_get_referer() : home_url( '/' );
	$data = sdc_submission_data();

	/*
	 * Język odpowiedzi = język strony, z której wysłano formularz.
	 *
	 * Całe to żądanie działa już w tym języku — ustala to languages.php na
	 * podstawie pola sdc_from (patrz sdc_form_submission_language()). Dzięki
	 * temu osoba pisząca z sekcji ukraińskiej dostaje ukraińskie komunikaty
	 * błędów, ukraińską stronę podziękowania i ukraiński e-mail.
	 */
	$lang = function_exists( 'sdc_current_language' ) ? sdc_current_language() : 'pl';

	// 3.1 Nonce.
	if ( empty( $_POST['sdc_nonce'] ) || ! is_string( $_POST['sdc_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['sdc_nonce'] ) ), SDC_FORM_ACTION ) ) {
		sdc_redirect_with_errors( $referer, array( 'ogolny' => __( 'Sesja formularza została odnowiona. Zachowaliśmy wpisane dane. Sprawdź je, ponownie zaznacz zgodę i wyślij formularz.', 'stop-dyskryminacji-core' ) ), $data );
	}

	// 3.2 Klucz szyfrowania.
	if ( ! sdc_submissions_ready() ) {
		sdc_audit_log( 'zgloszenie_odrzucone', 0, 'Brak klucza szyfrowania' );
		sdc_redirect_with_errors( $referer, array( 'ogolny' => __( 'Moduł zgłoszeniowy jest chwilowo niedostępny. Napisz do nas bezpośrednio.', 'stop-dyskryminacji-core' ) ), $data );
	}

	// A short completion time is only a review signal: paste/autofill and corrections are valid.
	$timestamp = isset( $_POST['sdc_ts'] ) && is_scalar( $_POST['sdc_ts'] ) ? (int) $_POST['sdc_ts'] : 0;
	$elapsed = time() - $timestamp;
	$quick = ! $timestamp || $elapsed < 4;
	if ( '' !== sdc_post( 'sdc_website' ) ) {
		sdc_audit_log( 'zgloszenie_spam', 0, 'Wypełniona pułapka' );
		sdc_redirect_with_errors( $referer, array( 'ogolny' => __( 'Formularz nie został przyjęty przez ochronę przed spamem. Sprawdź dane i spróbuj ponownie.', 'stop-dyskryminacji-core' ) ), $data );
	}

	// 3.4 Limit liczby zgłoszeń z jednego źródła.
	$fingerprint = sdc_client_fingerprint();
	$rate_key    = 'sdc_rate_' . substr( $fingerprint, 0, 32 );
	$count       = (int) get_transient( $rate_key );

	if ( $count >= (int) sdc_option( 'rate_limit', 5 ) ) {
		sdc_audit_log( 'zgloszenie_odrzucone', 0, 'Przekroczony limit zgłoszeń' );
		sdc_redirect_with_errors( $referer, array( 'ogolny' => __( 'Przyjęliśmy już kilka zgłoszeń z tego urządzenia. Jeżeli chcesz uzupełnić wcześniejszą sprawę, odpowiedz na wiadomość e-mail z potwierdzeniem.', 'stop-dyskryminacji-core' ) ), $data );
	}

	// 3.5 Walidacja pól.
	$errors = sdc_privacy26_contact_errors( $data );
	if ( mb_strlen( $data['opis'] ) < 20 ) {
		$errors['opis'] = __( 'Opisz sytuację nieco szerzej — potrzebujemy co najmniej kilku zdań, żeby cokolwiek doradzić.', 'stop-dyskryminacji-core' );
	}
	if ( mb_strlen( $data['opis'] ) > 8000 ) {
		$errors['opis'] = __( 'Opis jest za długi. Zmieść się w 8000 znaków — szczegóły uzupełnimy w odpowiedzi.', 'stop-dyskryminacji-core' );
	}
	if ( '1' !== $data['zgoda'] ) {
		$errors['zgoda'] = sdc_privacy26_copy( 'ack_required' );
	}
	if ( ! array_key_exists( $data['rola'], sdc_reporter_roles() ) ) {
		$data['rola'] = 'osoba';
	}
	if ( ! array_key_exists( $data['przeslanka'], sdc_grounds() ) ) {
		$data['przeslanka'] = 'nie-wiem';
	}

	if ( $errors ) {
		/*
		 * Do formularza wraca to, co osoba wpisała — także niepoprawny adres
		 * e-mail. sanitize_email() zwraca pusty ciąg dla błędnego adresu, więc
		 * bez tego pole było czyszczone i trzeba było pisać wszystko od nowa,
		 * a komunikat mówił o błędzie w polu, którego treści już nie widać.
		 */
		sdc_redirect_with_errors( $referer, $errors, $data );
	}

	// 3.6 reCAPTCHA.
	$captcha = sdc_verify_recaptcha( isset( $_POST['sdc_recaptcha_token'] ) && is_string( $_POST['sdc_recaptcha_token'] ) ? sdc_post( 'sdc_recaptcha_token' ) : '' );

	if ( ! $captcha['verified'] ) {
		sdc_audit_log( 'zgloszenie_odrzucone', 0, 'reCAPTCHA: ' . $captcha['reason'] );
		sdc_redirect_with_errors(
			$referer,
			array( 'ogolny' => __( 'Nie udało się potwierdzić, że formularz wypełnił człowiek. Spróbuj ponownie albo napisz do nas bezpośrednio.', 'stop-dyskryminacji-core' ) ),
			$data
		);
	}
	if ( $quick ) { $captcha['manual_review'] = true; }

	// 3.7 Zapis.
	$post_id = sdc_store_submission( $data, $captcha );

	if ( is_wp_error( $post_id ) || ! $post_id ) {
		sdc_audit_log( 'zgloszenie_blad', 0, is_wp_error( $post_id ) ? $post_id->get_error_code() : 'brak rekordu' );
		sdc_redirect_with_errors( $referer, array( 'ogolny' => __( 'Zapis zgłoszenia nie powiódł się. Spróbuj ponownie za chwilę.', 'stop-dyskryminacji-core' ) ), $data );
	}

	set_transient( $rate_key, $count + 1, HOUR_IN_SECONDS );

	$confirmation = '' === $data['email'] ? null : sdc_send_confirmation( $data, $post_id );
	update_post_meta( $post_id, '_sdc_confirmation_status', null === $confirmation ? 'not-applicable' : ( $confirmation ? 'sent' : 'failed' ) );
	if ( false === $confirmation ) { sdc_audit_log( 'zgloszenie_poczta_blad', $post_id, 'Potwierdzenie nie zostało przyjęte przez transport pocztowy.' ); }

	// Powiadomienie dla zespołu projektu zawsze po polsku. Wyładowanie domeny
	// (bez możliwości ponownego wczytania) przywraca polskie teksty źródłowe.
	if ( 'pl' !== $lang ) {
		unload_textdomain( 'stop-dyskryminacji-core' );
	}
	$notification = sdc_notify_team( $post_id );
	update_post_meta( $post_id, '_sdc_notification_status', $notification ? 'sent' : 'failed' );
	if ( ! $notification ) { sdc_audit_log( 'zgloszenie_poczta_blad', $post_id, 'Powiadomienie zespołu nie zostało przyjęte przez transport pocztowy.' ); }
	if ( function_exists( 'sdc_capture_admin_test' ) ) { sdc_capture_admin_test( $post_id, $confirmation, $notification ); }
	$previous = sdc_form_state_token( $_POST['sdc_state'] ?? '' );
	if ( $previous && sdc_load_form_state( $previous ) ) { delete_transient( 'sdc_form_v2_' . $previous ); }

	sdc_analytics_set_receipt();
	wp_safe_redirect( sdc_thank_you_url( $lang ) );
	exit;
}
add_action( 'admin_post_nopriv_' . SDC_FORM_ACTION, 'sdc_handle_submission' );
add_action( 'admin_post_' . SDC_FORM_ACTION, 'sdc_handle_submission' );

/**
 * Zapis zgłoszenia w postaci zaszyfrowanej.
 *
 * @param array $data    Dane z formularza.
 * @param array $captcha Wynik weryfikacji reCAPTCHA.
 * @return int|WP_Error
 */
function sdc_store_submission( array $data, array $captcha ) {
	$payload = sdc_encrypt_array(
		array(
			'imie'       => $data['imie'],
			'email'      => $data['email'],
			'telefon'    => $data['telefon'],
			'opis'       => $data['opis'],
			'rola'       => $data['rola'],
			'przeslanka' => $data['przeslanka'],
			'zapisano'   => current_time( 'mysql', true ),
			'privacy_version' => '2026-10-27',
			'privacy_ack' => '1' === $data['zgoda'],
			'sensitive_consent' => '1' === ( $data['sensitive'] ?? '' ),
		)
	);

	if ( is_wp_error( $payload ) ) {
		return $payload;
	}

	// Sygnatura zamiast treści — tytuł jest widoczny na listach w panelu
	// i w powiadomieniach, więc nie może zdradzać niczego o sprawie.
	$signature = strtoupper( wp_generate_password( 6, false, false ) );

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'zgloszenie',
			'post_status'  => 'private',
			'post_title'   => sprintf( 'ZGL-%s-%s', gmdate( 'Ymd' ), $signature ),
			'post_content' => '',
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	// Szyfrogram w polu meta, nie w treści wpisu — treść wpisu bywa
	// indeksowana przez wyszukiwarkę wewnętrzną i wtyczki.
	if ( ! update_post_meta( $post_id, '_sdc_payload', $payload ) || get_post_meta( $post_id, '_sdc_payload', true ) !== $payload ) {
		return new WP_Error( 'sdc_payload_not_saved', __( 'Nie udało się zapisać zaszyfrowanego zgłoszenia.', 'stop-dyskryminacji-core' ) );
	}

	// Metadane jawne — potrzebne do filtrowania i statystyk, same w sobie
	// nie identyfikują nikogo.
	update_post_meta( $post_id, '_sdc_rola', $data['rola'] );
	// The optional ground may reveal health, ethnicity or beliefs: keep it only in the encrypted payload.
	update_post_meta( $post_id, '_sdc_status', 'nowe' );
	$received = time();
	update_post_meta( $post_id, '_sdc_last_contact_at', $received );
	update_post_meta( $post_id, '_sdc_expires_at', sdc_submission_default_expiry( $received ) );
	// Ślepy indeks adresu e-mail — do wyszukania zgłoszenia przy żądaniu z RODO (crypto.php).
	if ( $data['email'] ) { update_post_meta( $post_id, '_sdc_email_idx', sdc_email_index( $data['email'] ) ); }
	update_post_meta( $post_id, '_sdc_captcha', $captcha['reason'] . ( null !== $captcha['score'] ? ' (' . $captcha['score'] . ')' : '' ) );
	update_post_meta( $post_id, '_sdc_manual_review', ! empty( $captcha['manual_review'] ) || in_array( $captcha['reason'], array( 'brak tokenu', 'usługa niedostępna' ), true ) ? '1' : '0' );

	sdc_audit_log( 'zgloszenie_utworzone', $post_id, 'Przyjęto zgłoszenie; informacja RODO: 2026-10-27.' );

	return $post_id;
}

/**
 * Automatyczne potwierdzenie dla zgłaszającego.
 *
 * Rekomendacja nr 2: automatyczne potwierdzenie i deklarowany czas reakcji.
 * W wiadomości nie powtarzamy treści zgłoszenia — skrzynka e-mail
 * zgłaszającego bywa współdzielona albo firmowa.
 *
 * @param array $data    Dane zgłoszenia.
 * @param int   $post_id Identyfikator zgłoszenia.
 */
function sdc_send_confirmation( array $data, $post_id ) {
	$signature = get_the_title( $post_id );
	$days      = sdc_response_days();

	$subject = sprintf(
		/* translators: %s: sygnatura zgłoszenia */
		sdc_help27_mail_copy( 'subject' ),
		$signature
	);

	$lines = array(
		$data['imie'] ? sprintf( sdc_help27_mail_copy( 'greeting_named' ), $data['imie'] ) : sdc_privacy26_copy( 'greeting' ),
		'',
		sdc_help27_mail_copy( 'receipt' ),
		'',
		sprintf( sdc_help27_mail_copy( 'reference' ), $signature ),
		sprintf( sdc_help27_mail_copy( 'response' ), $days ),
		'',
		sdc_help27_urgent(),
		'',
		sdc_privacy26_copy( 'retention_email' ),
		'',
		'—',
		get_bloginfo( 'name' ),
		home_url( '/' ),
		'',
		sdc_help27_mail_copy( 'funding' ),
	);

	$reply_to = sdc_help27_email( sdc_option( 'confirmation_reply_to', 'tetiana.wroblewska@proukraina.org' ) );
	$headers = array( 'Reply-To: ' . ( $reply_to ?: 'tetiana.wroblewska@proukraina.org' ) );
	return wp_mail( $data['email'], $subject, implode( "\n", $lines ), $headers );
}

/**
 * Powiadomienie zespołu o nowym zgłoszeniu.
 *
 * Powiadomienie zawiera wyłącznie sygnaturę i odnośnik do panelu.
 * Treść zgłoszenia nigdy nie wychodzi pocztą — poczta nie jest szyfrowana
 * end-to-end, a opis może zawierać dane z art. 9 RODO.
 *
 * @param int $post_id Identyfikator zgłoszenia.
 */
function sdc_notify_team( $post_id ) {
	$to = sdc_help27_recipients();

	if ( ! $to ) {
		return false;
	}

	return wp_mail(
		$to,
		sprintf( __( '[STOP DYSKRYMINACJI] Nowe zgłoszenie %s', 'stop-dyskryminacji-core' ), get_the_title( $post_id ) ),
		implode(
			"\n",
			array(
				__( 'Wpłynęło nowe zgłoszenie w module „Potrzebuję pomocy".', 'stop-dyskryminacji-core' ),
				'',
				sprintf( __( 'Sygnatura: %s', 'stop-dyskryminacji-core' ), get_the_title( $post_id ) ),
				sprintf( __( 'Termin odpowiedzi: %d dni roboczych.', 'stop-dyskryminacji-core' ), sdc_response_days() ),
				'',
				__( 'Treść jest zaszyfrowana i widoczna wyłącznie po zalogowaniu:', 'stop-dyskryminacji-core' ),
				admin_url( 'post.php?post=' . (int) $post_id . '&action=edit' ),
			)
		)
	);
}

/**
 * Przekierowanie z zapisanymi błędami i wprowadzonymi wcześniej danymi.
 *
 * Dane wracają do formularza przez krótkotrwały transient, a nie przez adres URL —
 * opis sytuacji nie może trafić do logów serwera ani do historii przeglądarki.
 *
 * @param string $url    Adres powrotny.
 * @param array  $errors Błędy.
 * @param array  $old    Wprowadzone dane.
 */
function sdc_redirect_with_errors( $url, array $errors, array $old ) {
	unset( $old['zgoda'], $old['sensitive'] );
	$token = sdc_save_form_state( $errors, $old );
	if ( is_wp_error( $token ) ) {
		// A storage/cookie failure must not discard the submitted text or claim success.
		$message = '<h1>' . esc_html__( 'Formularza nie udało się wysłać', 'stop-dyskryminacji-core' ) . '</h1>';
		$message .= '<style>.sd-sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%)}input,textarea{max-width:100%}textarea{width:100%}</style>';
		$message .= sdc_render_help_form( array( '_sdc_state' => array( 'errors' => $errors, 'old' => $old ) ) );
		wp_die( $message, esc_html__( 'Formularz pomocy', 'stop-dyskryminacji-core' ), array( 'response' => 503 ) );
	}

	wp_safe_redirect( add_query_arg( 'sdc', $token, remove_query_arg( array( 'sdc', 'zgloszenie' ), $url ) ) . '#sd-errors' );
	exit;
}

/**
 * Adres strony podziękowania.
 *
 * @return string
 */
function sdc_thank_you_url( $lang = 'pl' ) {
	$page = (int) sdc_option( 'thankyou_page_id', 0 );
	if ( ! $page ) {
		$default = get_page_by_path( 'dziekujemy' );
		$page    = $default ? (int) $default->ID : 0;
	}

	// Podziękowanie w języku, w którym wysłano zgłoszenie — jeżeli istnieje.
	if ( $page && 'pl' !== $lang && function_exists( 'sdc_translation_id' ) ) {
		$translated = sdc_translation_id( $page, $lang );
		$page       = $translated ? $translated : $page;
	}

	$url = $page ? get_permalink( $page ) : home_url( '/dziekujemy/' );

	return add_query_arg( 'zgloszenie', 'ok', $url );
}

/* -------------------------------------------------------------------------
 * 4. Retencja
 * ---------------------------------------------------------------------- */

/**
 * Usunięcie zgłoszeń po okresie retencji.
 *
 * § 3 ust. 1 umowy powierzenia: „automatyczne usuwanie lub anonimizację zgłoszeń
 * po upływie zadeklarowanego okresu retencji". Usuwamy trwale, razem z koszem —
 * zgłoszenie w koszu nadal jest przetwarzaniem danych.
 */
function sdc_apply_submission_retention() {
	$ids = get_posts( array( 'post_type' => 'zgloszenie', 'post_status' => array( 'private', 'draft', 'trash' ), 'numberposts' => 200, 'fields' => 'ids',
		'meta_query' => array( array( 'key' => '_sdc_expires_at', 'value' => time(), 'compare' => '<=', 'type' => 'NUMERIC' ), array( 'key' => '_sdc_expires_at', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ) ) ) );
	foreach ( $ids as $id ) {
		// Recheck the actual last contact; a stale expiry index must never erase a recent case.
		$expiry = sdc_submission_expiry( $id );
		if ( ! $expiry ) { delete_post_meta( $id, '_sdc_expires_at' ); continue; }
		if ( $expiry > time() ) { update_post_meta( $id, '_sdc_expires_at', $expiry ); continue; }
		sdc_audit_log( 'zgloszenie_retencja', $id, 'Upłynęło 90 dni od ostatniego kontaktu.' );
		wp_delete_post( $id, true );
	}
}
