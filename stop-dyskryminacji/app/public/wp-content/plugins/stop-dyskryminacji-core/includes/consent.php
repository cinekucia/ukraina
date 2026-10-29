<?php
/**
 * Zgoda na pliki cookie.
 *
 * Kryterium odbioru (Załącznik nr 2 do umowy, wiersz „RODO serwisu"):
 * „polityka cookies opublikowane; okno zgody z RÓWNORZĘDNYMI PRZYCISKAMI;
 * brak zapisu plików analitycznych PRZED ZGODĄ".
 *
 * Dwie rzeczy, na których takie wdrożenia najczęściej przepadają przy audycie:
 *   1. Przycisk odmowy wygląda słabiej od przycisku zgody — wtedy zgoda nie jest
 *      dobrowolna w rozumieniu art. 4 pkt 11 RODO. Tutaj oba przyciski mają
 *      identyczny rozmiar, identyczną ramkę i identyczną wagę tekstu.
 *   2. Skrypt analityczny ładuje się już przy pierwszym wejściu, a banner tylko
 *      go „ukrywa". Tutaj GA4 nie trafia do dokumentu, dopóki w localStorage
 *      nie ma zapisanej zgody — sprawdzenie widać w zakładce Sieć narzędzi
 *      deweloperskich, czego audytor właśnie wymaga jako dowodu.
 *
 * Banner nie jest modalny i nie blokuje treści — osoba szukająca pomocy
 * musi móc korzystać z serwisu bez podejmowania decyzji o analityce.
 *
 * @package StopDyskryminacjiCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Czy banner zgody ma być w ogóle pokazywany.
 *
 * Bez skonfigurowanego GA4 serwis nie ustawia żadnych plików analitycznych,
 * więc pytanie o zgodę byłoby pytaniem bez przedmiotu.
 *
 * @return bool
 */
function sdc_consent_needed() {
	return '' !== sdc_ga4_measurement_id() || ( function_exists( 'sdc_stats_enabled' ) && sdc_stats_enabled() );
}

function sdc_analytics_consent_text() {
	$lang = function_exists( 'sdc_current_language' ) ? sdc_current_language() : 'pl';
	$copy = array(
		'pl' => 'Po Twojej zgodzie mierzymy odwiedziny i kliknięcia za pomocą losowego identyfikatora przeglądarki. Lokalne statystyki przechowujemy na serwerze strony. Odmowa nie ogranicza korzystania z serwisu.',
		'en' => 'With your consent, we measure visits and link clicks using a random browser identifier. Local statistics stay on this website’s server. Refusing does not limit access to the website.',
		'uk' => 'За вашою згодою ми вимірюємо відвідування та натискання посилань за допомогою випадкового ідентифікатора браузера. Локальна статистика зберігається на сервері цього сайту. Відмова не обмежує користування сайтом.',
	);
	$text = $copy[ $lang ] ?? $copy['pl'];
	if ( sdc_ga4_measurement_id() ) {
		$extra = array( 'pl' => ' Po zgodzie działa również Google Analytics 4 (Google Ireland Limited); dane analityczne mogą być przetwarzane poza EOG, m.in. w USA. Nie przekazujemy treści formularzy ani danych kontaktowych.', 'en' => ' Google Analytics 4 (Google Ireland Limited) also runs after consent; analytics data may be processed outside the EEA, including the USA. Form contents and contact details are not sent.', 'uk' => ' Після згоди також працює Google Analytics 4 (Google Ireland Limited); аналітичні дані можуть опрацьовуватися поза ЄЕЗ, зокрема у США. Зміст форм і контакти не передаються.' );
		$text .= $extra[ $lang ] ?? $extra['pl'];
	}
	return $text;
}

/**
 * Banner zgody.
 *
 * Wizualnie przyklejony do dołu ekranu, ale w kodzie strony stoi na samym
 * początku, zaraz po odnośniku „Przejdź do treści głównej". Osoba poruszająca
 * się klawiaturą albo czytnikiem ekranu trafia na niego od razu, a nie dopiero
 * po przejściu przez całą stronę (WCAG 2.4.3 — kolejność fokusu).
 */
function sdc_render_consent_banner() {
	static $done = false;

	if ( $done || is_admin() || ! sdc_consent_needed() ) {
		return;
	}
	$done = true;

	$privacy = sdc_option( 'cookie_policy_url', '/polityka-cookies/' );
	?>
	<div class="sd-consent" id="sd-consent" role="region"
		aria-labelledby="sd-consent-title" hidden>
		<div class="sd-consent__inner">
			<div>
				<h2 id="sd-consent-title"><?php esc_html_e( 'Pliki cookie na tej stronie', 'stop-dyskryminacji-core' ); ?></h2>
				<p>
					<?php echo esc_html( sdc_analytics_consent_text() ); ?>
					<?php echo sdc_document_link( $privacy, __( 'Polityka plików cookie', 'stop-dyskryminacji-core' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- znacznik budowany z esc_url()/esc_html() ?>
				</p>
			</div>
			<div class="sd-consent__actions">
				<?php /* Oba przyciski celowo mają tę samą klasę wizualną — patrz komentarz na górze pliku. */ ?>
				<button type="button" class="sd-btn sd-btn--secondary" data-sd-consent="granted">
					<?php esc_html_e( 'Zgadzam się na statystyki', 'stop-dyskryminacji-core' ); ?>
				</button>
				<button type="button" class="sd-btn sd-btn--secondary" data-sd-consent="denied">
					<?php esc_html_e( 'Tylko niezbędne', 'stop-dyskryminacji-core' ); ?>
				</button>
			</div>
		</div>
	</div>
	<?php
}
// Zaraz po odnośniku „Przejdź do treści głównej" (motyw drukuje go z priorytetem 1).
add_action( 'wp_body_open', 'sdc_render_consent_banner', 2 );

/**
 * Zapas dla motywów, które nie wywołują wp_body_open.
 *
 * Priorytet 5, a nie 20: skrypty stopki drukuje „wp_print_footer_scripts"
 * na priorytecie 20 — przy równym priorytecie kod obsługi zgody trafiałby
 * do dokumentu PRZED znacznikami bannera i nie miałby czego znaleźć.
 */
function sdc_render_consent_banner_fallback() {
	if ( ! did_action( 'wp_body_open' ) ) {
		sdc_render_consent_banner();
	}
}
add_action( 'wp_footer', 'sdc_render_consent_banner_fallback', 5 );

/**
 * Skrypt obsługi zgody.
 *
 * Wstrzykiwany do stopki, więc nie blokuje pierwszego renderowania —
 * ma to znaczenie dla wyniku Lighthouse wymaganego w warunkach odbioru.
 */
function sdc_consent_script() {
	if ( is_admin() || ! sdc_consent_needed() ) {
		return;
	}

	/*
	 * Zależność od „sdc-analytics" jest tu istotna, a nie kosmetyczna:
	 * to ona gwarantuje, że window.sdInitAnalytics istnieje, zanim ten skrypt
	 * spróbuje go wywołać. Bez niej przy zapisanej wcześniej zgodzie analityka
	 * po prostu się nie uruchamiała — kod zgody wykonywał się pierwszy.
	 */
	wp_enqueue_script( 'sdc-consent', SDC_URL . 'assets/js/consent.js', array( 'sdc-analytics', 'sdc-local-stats' ), SDC_VERSION, array( 'in_footer' => true ) );

}
add_action( 'wp_enqueue_scripts', 'sdc_consent_script' );

/** Keep withdrawal available even when a saved database footer overrides the theme file. */
function sdc_consent_footer_control( $content, $block ) {
	if ( is_admin() || ! sdc_consent_needed() || 'footer' !== ( $block['attrs']['tagName'] ?? '' ) || false !== strpos( $content, 'data-sd-consent-reset' ) ) { return $content; }
	$labels = array( 'pl' => 'Ustawienia plików cookies', 'en' => 'Cookie settings', 'uk' => 'Налаштування cookies' );
	$button = '<div class="sd-footer__inner"><p><button type="button" class="sd-btn sd-btn--secondary" style="background:transparent;color:inherit;border-color:currentColor" data-sd-consent-reset>' . esc_html( $labels[ sdc_current_language() ] ?? $labels['pl'] ) . '</button></p></div>';
	$end = strripos( $content, '</footer>' );
	return false === $end ? $content : substr_replace( $content, $button, $end, 0 );
}
add_filter( 'render_block_core/group', 'sdc_consent_footer_control', 60, 2 );
