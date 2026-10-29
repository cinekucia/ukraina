<?php
/**
 * Sekcje językowe UA i EN: przełącznik, język żądania, menu, stopka i 404.
 *
 * DLACZEGO TO JEST WE WTYCZCE, A NIE W MOTYWIE
 * --------------------------------------------
 * Wybór języka jest funkcją serwisu, nie jego wyglądem. Po zmianie motywu
 * użytkownik ukraińskojęzyczny nadal musi mieć jak przełączyć się na swoją
 * wersję.
 *
 * DLACZEGO NIE NA SZTYWNO TRZY JĘZYKI
 * -----------------------------------
 * Przełącznik prowadzący do nieistniejącej wersji jest gorszy niż jego brak:
 * użytkownik klika i trafia na 404 albo na polską stronę, której nie rozumie.
 * Dlatego:
 *
 *   1. Jeżeli zainstalowana jest wtyczka tłumacząca (Polylang albo WPML),
 *      przełącznik bierze listę języków od niej — to jest docelowe rozwiązanie.
 *   2. Jeżeli nie ma żadnej, korzysta z adresów wpisanych w ustawieniach.
 *   3. Język bez wskazanego adresu NIE JEST pokazywany.
 *
 * Przy jednym dostępnym języku przełącznik nie renderuje się wcale.
 *
 * DOSTĘPNOŚĆ
 * ----------
 * WCAG 3.1.2 (Language of Parts): każdy odnośnik ma atrybut „lang" swojego
 * języka, żeby czytnik ekranu wymówił „Українська" po ukraińsku, a nie po
 * polsku. Skrót „UA" sam w sobie nie jest zrozumiały (WCAG 2.4.4), więc
 * pełna nazwa języka jedzie w tekście dla czytników ekranu.
 *
 * @package StopDyskryminacjiCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Obsługiwane języki serwisu.
 *
 * Kod „uk" to ukraiński zgodnie z ISO 639-1. Etykieta „UA" jest skrótem
 * rozpoznawalnym dla użytkownika, ale w atrybucie lang musi być „uk”.
 *
 * @return array
 */
function sdc_languages() {
	return array(
		'pl' => array(
			'label'    => 'PL',
			'name'     => 'Polski',
			'hreflang' => 'pl',
			'option'   => '',           // język podstawowy — adres to strona główna
		),
		'uk' => array(
			'label'    => 'UA',
			'name'     => 'Українська',
			'hreflang' => 'uk',
			'option'   => 'lang_url_uk',
		),
		'en' => array(
			'label'    => 'EN',
			'name'     => 'English',
			'hreflang' => 'en',
			'option'   => 'lang_url_en',
		),
	);
}

/**
 * Lista języków do pokazania, z adresami i oznaczeniem bieżącego.
 *
 * @return array
 */
function sdc_available_languages() {
	// 1. Wtyczka tłumacząca ma pierwszeństwo — ona wie o tłumaczeniach naprawdę.
	$from_plugin = apply_filters( 'sdc_language_links', null );
	if ( is_array( $from_plugin ) ) {
		return $from_plugin;
	}

	$out = array();

	foreach ( sdc_languages() as $code => $lang ) {
		$url = '' === $lang['option'] ? home_url( '/' ) : trim( (string) sdc_option( $lang['option'], '' ) );

		if ( '' === $url ) {
			continue;   // brak wersji w tym języku — nie pokazujemy martwego odnośnika
		}

		if ( 0 !== strpos( $url, 'http' ) ) {
			$url = home_url( '/' . ltrim( $url, '/' ) );
		}

		$lang_path = untrailingslashit( wp_parse_url( $url, PHP_URL_PATH ) );

		/*
		 * Przełącznik prowadzi do ODPOWIEDNIKA bieżącej strony, a nie zawsze
		 * do strony głównej sekcji. Inaczej każde przełączenie języka
		 * wyrzucałoby czytelnika na początek i gubiło to, co czytał.
		 * Gdy odpowiednika nie ma, wracamy do strony głównej danej sekcji.
		 */
		$target = ( 'pl' === $code )
			? sdc_translated_url( 'pl', $url )
			: sdc_translated_url( $code, $url );

		$out[ $code ] = array(
			'label'    => $lang['label'],
			'name'     => $lang['name'],
			'hreflang' => $lang['hreflang'],
			'url'      => $target,
			// Bieżący język wynika z przedrostka adresu, nie z porównywania ścieżek.
			'current'  => ( $code === sdc_current_language() ),
		);
	}

	return $out;
}

/**
 * Renderowanie przełącznika języka.
 *
 * @return string
 */
function sdc_render_language_switcher() {
	$langs = sdc_available_languages();

	/*
	 * „Prosty język" NIE jest tutaj i nie powinien być.
	 *
	 * To rejestr tekstu, a nie język: ta sama polszczyzna, tylko prostsza.
	 * Postawiony obok PL / UA / EN wyglądał jak czwarty język i mylił.
	 * Odnośnik do niego jest w stopce, razem z deklaracją dostępności —
	 * bo to jest funkcja dostępności, nie funkcja tłumaczenia.
	 */
	if ( count( $langs ) < 2 ) {
		return current_user_can( 'manage_options' )
			? '<p class="sd-lang-hint">' . esc_html__( 'Przełącznik języka pojawi się po wskazaniu adresów wersji językowych w Ustawienia → STOP DYSKRYMINACJI.', 'stop-dyskryminacji-core' ) . '</p>'
			: '';
	}

	ob_start();
	?>
	<div class="sd-lang-group">

		<?php if ( count( $langs ) > 1 ) : ?>
			<nav class="sd-lang" aria-label="<?php esc_attr_e( 'Wybór języka', 'stop-dyskryminacji-core' ); ?>">
				<ul>
					<?php foreach ( $langs as $code => $l ) : ?>
						<li>
							<a href="<?php echo esc_url( $l['url'] ); ?>"
								lang="<?php echo esc_attr( $l['hreflang'] ); ?>"
								hreflang="<?php echo esc_attr( $l['hreflang'] ); ?>"
								<?php echo $l['current'] ? 'aria-current="true"' : ''; ?>
								class="sd-lang__item<?php echo $l['current'] ? ' is-current' : ''; ?>">
								<?php
								/*
								 * Skrót zostaje w nazwie dostępnej, a pełna nazwa języka jest dopiskiem.
								 * WCAG 2.5.3 (etykieta w nazwie): osoba sterująca głosem mówi „kliknij UA",
								 * więc nazwa odnośnika musi zawierać widoczny napis „UA". Sama nazwa
								 * „Українська" (ze skrótem ukrytym przed czytnikiem) tego nie spełniała.
								 */
								?>
								<span><?php echo esc_html( $l['label'] ); ?></span><span class="sd-sr-only"> — <?php echo esc_html( $l['name'] ); ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>
		<?php endif; ?>

	</div>
	<?php
	return ob_get_clean();
}

/**
 * Znaczniki hreflang w sekcji head.
 *
 * Mówią wyszukiwarce, że to są wzajemne tłumaczenia tej samej treści,
 * a nie zduplikowane strony.
 */
function sdc_hreflang_tags() {
	if ( is_admin() ) {
		return;
	}

	// A language switcher may fall back to a section homepage; SEO alternatives
	// must refer to equivalent content and never inherit that navigation fallback.
	$langs = function_exists( 'sdc_seo_equivalent_languages' ) ? sdc_seo_equivalent_languages() : array();
	if ( count( $langs ) < 2 ) {
		return;
	}

	foreach ( $langs as $l ) {
		printf(
			'<link rel="alternate" hreflang="%1$s" href="%2$s">' . "\n",
			esc_attr( $l['hreflang'] ),
			esc_url( $l['url'] )
		);
	}
}
add_action( 'wp_head', 'sdc_hreflang_tags', 6 );

/**
 * Integracja z Polylang, jeżeli jest zainstalowany.
 *
 * Wtyczka tłumacząca wie, które wpisy mają tłumaczenia, więc jej odpowiedź
 * jest zawsze lepsza od adresów wpisanych ręcznie w ustawieniach.
 *
 * @param array|null $links Dotychczasowa lista.
 * @return array|null
 */
function sdc_polylang_language_links( $links ) {
	if ( ! function_exists( 'pll_the_languages' ) ) {
		return $links;
	}

	$raw = pll_the_languages( array( 'raw' => 1, 'hide_if_no_translation' => 1 ) );
	if ( ! is_array( $raw ) || count( $raw ) < 2 ) {
		return $links;
	}

	$out = array();
	foreach ( $raw as $slug => $l ) {
		$out[ $slug ] = array(
			'label'    => strtoupper( 'uk' === $slug ? 'ua' : $slug ),
			'name'     => $l['name'],
			'hreflang' => $l['locale'] ? substr( $l['locale'], 0, 2 ) : $slug,
			'url'      => $l['url'],
			'current'  => ! empty( $l['current_lang'] ),
		);
	}

	return $out;
}
add_filter( 'sdc_language_links', 'sdc_polylang_language_links' );

/**
 * Umieszczenie przełącznika w listwie ze znakami, po prawej stronie.
 *
 * Nagłówek serwisu jest już zajęty przez menu i wezwanie do działania —
 * dołożenie tam jeszcze przełącznika łamało pasek na dwie linie. Listwa ze
 * znakami ma po prawej wolne miejsce, a przełącznik języka jest dokładnie
 * tym rodzajem elementu, którego szuka się na samej górze strony.
 *
 * @param string $extra Dotychczasowa zawartość.
 * @return string
 */
function sdc_eu_bar_language_switcher( $extra ) {
	return $extra . sdc_render_language_switcher();
}
add_filter( 'sdc_eu_bar_extra', 'sdc_eu_bar_language_switcher' );

/* =========================================================================
 * KONTEKST JĘZYKOWY
 *
 * Bez wtyczki tłumaczącej język musi skądś wynikać. Wynika z adresu:
 * przedrostek /ua/ albo /en/ ustala język całego żądania. Dzięki temu
 * wybór języka jest trwały — każdy odnośnik w obrębie sekcji zostaje
 * w tym samym języku, a adres można wysłać komuś dalej.
 * ====================================================================== */

/**
 * Przedrostki adresu przypisane do języków.
 *
 * @return array
 */
function sdc_language_prefixes() {
	return array(
		'uk' => trim( (string) sdc_option( 'lang_url_uk', '' ), '/' ),
		'en' => trim( (string) sdc_option( 'lang_url_en', '' ), '/' ),
	);
}

/**
 * Język bieżącego żądania: „pl", „uk" albo „en".
 *
 * @return string
 */
function sdc_current_language() {
	static $lang = null;

	if ( null !== $lang ) {
		return $lang;
	}

	// W panelu nie zmieniamy języka — redaktor pracuje po polsku.
	// Wyjątek: wysyłka formularza zgłoszeniowego (admin-post.php) odpowiada
	// w języku strony, z której formularz wysłano.
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		if ( ! defined( 'SDC_FORM_ACTION' ) ) {
			return 'pl';   // moduł formularza jeszcze niewczytany — bez zapamiętywania wyniku
		}
		$form = sdc_form_submission_language();
		$lang = $form ? $form : 'pl';
		return $lang;
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- używana wyłącznie ścieżka, porównywana z przedrostkami.
	$lang = sdc_language_of_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '' );

	return $lang;
}

/**
 * Język wynikający z adresu: „pl", „uk" albo „en".
 *
 * Uwzględnia instalację WordPressa w podkatalogu — przedrostek /ua/ liczy się
 * od adresu strony głównej, nie od korzenia domeny.
 *
 * @param string $url Pełny adres albo sama ścieżka.
 * @return string
 */
function sdc_language_of_url( $url ) {
	$path = trim( (string) wp_parse_url( (string) $url, PHP_URL_PATH ), '/' );
	$home = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );

	if ( '' !== $home && ( $path === $home || 0 === strpos( $path . '/', $home . '/' ) ) ) {
		$path = trim( substr( $path, strlen( $home ) ), '/' );
	}

	foreach ( sdc_language_prefixes() as $code => $prefix ) {
		if ( '' !== $prefix && ( $path === $prefix || 0 === strpos( $path . '/', $prefix . '/' ) ) ) {
			return $code;
		}
	}

	return 'pl';
}

/**
 * Język wysyłanego formularza zgłoszeniowego albo pusty ciąg.
 *
 * Formularz trafia do admin-post.php, gdzie językiem jest zawsze polski.
 * Ukryte pole sdc_from niesie adres strony z formularzem — z niego wynika,
 * w jakim języku odpowiedzieć (komunikaty błędów, strona podziękowania,
 * e-mail z potwierdzeniem).
 *
 * @return string
 */
function sdc_form_submission_language() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- tylko wybór języka komunikatów; nonce sprawdza obsługa formularza.
	if ( empty( $_POST['action'] ) || ! is_string( $_POST['action'] ) || empty( $_POST['sdc_from'] ) || ! is_string( $_POST['sdc_from'] ) || ! defined( 'SDC_FORM_ACTION' ) ) {
		return '';
	}
	if ( SDC_FORM_ACTION !== sanitize_key( wp_unslash( $_POST['action'] ) ) ) {
		return '';
	}
	$from = esc_url_raw( wp_unslash( $_POST['sdc_from'] ) );
	// phpcs:enable

	return $from ? sdc_language_of_url( $from ) : '';
}

/**
 * Lokalizacja przy wysyłce formularza.
 *
 * W panelu WordPress ustala język przez determine_locale(), a nie get_locale(),
 * więc sam filtr „locale" nie wystarcza dla admin-post.php.
 *
 * @param string $locale Lokalizacja.
 * @return string
 */
function sdc_filter_determine_locale( $locale ) {
	return sdc_form_submission_language() ? sdc_filter_locale( $locale ) : $locale;
}
add_filter( 'determine_locale', 'sdc_filter_determine_locale' );

/**
 * Lokalizacja WordPressa zgodna z językiem adresu.
 *
 * To jeden filtr, ale robi najwięcej: przełącza wszystkie ciągi interfejsu
 * przepuszczone przez __() w motywie i we wtyczce, a także format dat.
 *
 * @param string $locale Lokalizacja.
 * @return string
 */
function sdc_filter_locale( $locale ) {
	switch ( sdc_current_language() ) {
		case 'uk':
			return 'uk';
		case 'en':
			return 'en_US';
	}
	return $locale;
}
add_filter( 'locale', 'sdc_filter_locale' );

/**
 * Zapasowe tłumaczenie napisów rdzenia WordPressa widocznych na stronie.
 *
 * Nazwy miesięcy w datach, etykiety przycisków menu na telefonie, tytuł
 * karty przeglądarki na stronie 404 i w wynikach wyszukiwania — to napisy
 * samego WordPressa, nie motywu. Tłumaczy je pakiet językowy WordPressa.
 * Gdy pakietu brak, strona pokazywała „17 September 2026" i „Open menu".
 * Dotyczy to zawsze ukraińskiego (WordPress sam pobiera wyłącznie pakiet
 * języka ustawionego dla całej witryny) oraz polskiego na serwerze, który
 * nie pobrał pakietu, np. bez dostępu do translate.wordpress.org.
 *
 * Oficjalny pakiet ma pierwszeństwo: plik z wtyczki ładuje się WYŁĄCZNIE
 * wtedy, gdy pakietu nie ma. Plik zawiera tylko kilkadziesiąt napisów
 * ustalonych pomiarem jako widoczne na stronie — nie zastępuje pakietu.
 *
 * @param string $mofile Ścieżka pliku tłumaczenia.
 * @param string $domain Domena tłumaczenia.
 * @return string
 */
function sdc_core_translation_fallback( $mofile, $domain ) {
	if ( 'default' !== $domain ) {
		return $mofile;
	}

	// Tylko główny plik rdzenia (pl_PL.mo, uk.mo) — nie admin-*, ms-*, continents-cities-*.
	$locale = basename( $mofile, '.mo' );
	if ( ! in_array( $locale, array( 'pl_PL', 'uk' ), true ) ) {
		return $mofile;
	}

	if ( is_readable( $mofile ) || is_readable( substr( $mofile, 0, -3 ) . '.l10n.php' ) ) {
		return $mofile;
	}

	$fallback = SDC_PATH . 'languages/wordpress-' . $locale . '.mo';

	return is_readable( $fallback ) ? $fallback : $mofile;
}
add_filter( 'load_textdomain_mofile', 'sdc_core_translation_fallback', 10, 2 );

/**
 * Atrybut lang elementu <html> zgodny z językiem sekcji.
 *
 * @param string $lang Kod języka.
 * @return string
 */
function sdc_filter_content_language( $lang ) {
	$map = array( 'pl' => 'pl', 'uk' => 'uk', 'en' => 'en' );
	return $map[ sdc_current_language() ] ?? $lang;
}
add_filter( 'sd_content_language', 'sdc_filter_content_language' );

/* -------------------------------------------------------------------------
 * MENU ZALEŻNE OD JĘZYKA
 * ---------------------------------------------------------------------- */

/**
 * Identyfikator menu nawigacji dla danego języka.
 *
 * @param string $lang Kod języka.
 * @return int
 */
function sdc_navigation_id( $lang ) {
	$found = get_posts( array(
		'post_type'      => 'wp_navigation',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => '_sdc_nav_lang',
		'meta_value'     => $lang,
	) );

	return $found ? (int) $found[0] : 0;
}

/**
 * Podmiana menu w bloku nawigacji na wersję w bieżącym języku.
 *
 * Blok core/navigation wskazuje jedno konkretne menu przez atrybut „ref".
 * Podmieniamy ten atrybut zanim blok się wyrenderuje — dzięki temu ten sam
 * szablon obsługuje wszystkie języki i nie trzeba trzymać trzech nagłówków.
 *
 * @param array $parsed Sparsowany blok.
 * @return array
 */
function sdc_swap_navigation_by_language( $parsed ) {
	if ( 'core/navigation' !== ( $parsed['blockName'] ?? '' ) ) {
		return $parsed;
	}

	/*
	 * Wskazujemy menu ZAWSZE, także dla polskiego.
	 *
	 * Blok core/navigation bez atrybutu „ref" bierze NAJNOWSZY wpis
	 * wp_navigation. Po dodaniu menu ukraińskiego i angielskiego oznaczało to,
	 * że polska strona główna dostawała menu angielskie — zależnie od tego,
	 * które menu utworzono jako ostatnie.
	 */
	$ref = sdc_navigation_id( sdc_current_language() );
	if ( $ref ) {
		$parsed['attrs']['ref'] = $ref;
	}

	return $parsed;
}
add_filter( 'render_block_data', 'sdc_swap_navigation_by_language' );

/* -------------------------------------------------------------------------
 * ODPOWIEDNIK STRONY W INNYM JĘZYKU
 * ---------------------------------------------------------------------- */

/**
 * Odnośnik do odpowiednika bieżącej strony w danym języku.
 *
 * Kolejność prób:
 *   1. strona powiązana meta „_sdc_translation_of" (dokładny odpowiednik),
 *   2. strona główna danej sekcji językowej.
 *
 * Nigdy nie prowadzimy do adresu, którego nie ma.
 *
 * @param string $lang      Docelowy język.
 * @param string $fallback  Adres zapasowy.
 * @return string
 */
function sdc_translated_url( $lang, $fallback ) {
	$id = get_queried_object_id();
	if ( $id && is_singular() && 'pl' === $lang && 'pl' === sdc_current_language() ) {
		return get_permalink( $id );
	}

	if ( $id ) {
		// Bieżąca strona jest oryginałem — szukamy tłumaczenia.
		$tr = sdc_translation_id( $id, $lang );
		if ( $tr ) {
			return get_permalink( $tr );
		}

		// Bieżąca strona jest tłumaczeniem — wracamy do oryginału albo w bok.
		$origin = (int) get_post_meta( $id, '_sdc_translation_of', true );
		if ( $origin ) {
			if ( 'pl' === $lang ) {
				return get_permalink( $origin );
			}
			$sib = sdc_translation_id( $origin, $lang );
			if ( $sib ) {
				return get_permalink( $sib );
			}
		}
	}

	return function_exists( 'sdc_translated_archive_url' ) ? sdc_translated_archive_url( $fallback, $lang ) : $fallback;
}

/**
 * Opublikowane tłumaczenie polskiej strony w danym języku.
 *
 * @param int    $origin Polska strona.
 * @param string $lang   Kod języka.
 * @return int Identyfikator tłumaczenia albo 0.
 */
function sdc_translation_id( $origin, $lang ) {
	$found = get_posts( array(
		'post_type'      => 'page',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_query'     => array(
			array( 'key' => '_sdc_translation_of', 'value' => (int) $origin ),
			array( 'key' => '_sdc_page_lang', 'value' => $lang ),
		),
	) );

	return $found ? (int) $found[0] : 0;
}

/**
 * Odnośnik do dokumentu, który może istnieć tylko po polsku.
 *
 * Polityka prywatności, polityka plików cookie, deklaracja dostępności.
 * Jeżeli dokument ma tłumaczenie w języku strony — odnośnik prowadzi do
 * tłumaczenia. Jeżeli nie ma — prowadzi do polskiego oryginału i MÓWI o tym
 * w treści odnośnika („privacy policy (in Polish)").
 *
 * Przy zgodzie w formularzu to nie jest detal: osoba oświadcza, że zapoznała
 * się z dokumentem, więc przed kliknięciem ma wiedzieć, w jakim języku go
 * dostanie.
 *
 * @param string $url   Adres polskiego dokumentu (względny albo pełny).
 * @param string $label Etykieta odnośnika, już przetłumaczona.
 * @return string Znacznik <a>.
 */
function sdc_document_link( $url, $label ) {
	$lang = sdc_current_language();
	$full = 0 === strpos( $url, 'http' ) ? $url : home_url( '/' . ltrim( $url, '/' ) );

	if ( 'pl' === $lang ) {
		return '<a href="' . esc_url( $full ) . '">' . esc_html( $label ) . '</a>';
	}

	$page_id = url_to_postid( $full );
	$tr      = $page_id ? sdc_translation_id( $page_id, $lang ) : 0;

	if ( $tr ) {
		return '<a href="' . esc_url( get_permalink( $tr ) ) . '">' . esc_html( $label ) . '</a>';
	}

	return sprintf(
		'<a href="%1$s" hreflang="pl">%2$s %3$s</a>',
		esc_url( $full ),
		esc_html( $label ),
		esc_html__( '(po polsku)', 'stop-dyskryminacji-core' )
	);
}

/**
 * Wezwanie „Potrzebuję pomocy" w nagłówku, w bieżącym języku.
 *
 * W szablonie był to zwykły przycisk z polskim napisem i adresem /potrzebuje-pomocy/.
 * Na stronie ukraińskiej dawał polski napis prowadzący do polskiej strony —
 * czyli dokładnie to, na co nie może sobie pozwolić przycisk, który ma być
 * najważniejszą akcją dla osoby nieposługującej się polskim.
 *
 * @return string
 */
function sdc_render_help_cta() {
	$lang = sdc_current_language();

	$targets = array(
		'pl' => '/potrzebuje-pomocy/',
		'uk' => '/ua/dopomoha/',
		'en' => '/en/get-help/',
	);

	$url = $targets[ $lang ] ?? $targets['pl'];

	// Gdy wersja językowa strony zgłoszeniowej nie istnieje, kierujemy
	// na stronę główną danej sekcji — nigdy na 404.
	$page = get_page_by_path( trim( $url, '/' ) );
	if ( ! $page && 'pl' !== $lang ) {
		$prefixes = sdc_language_prefixes();
		$url      = '/' . trim( $prefixes[ $lang ] ?? '', '/' ) . '/';
	}

	return sprintf(
		'<div class="wp-block-buttons sd-header__cta-wrap"><div class="wp-block-button sd-header__cta">'
			. '<a class="wp-block-button__link wp-element-button" href="%s">%s</a>'
			. '</div></div>',
		esc_url( home_url( $url ) ),
		esc_html__( 'Potrzebuję pomocy', 'stop-dyskryminacji-core' )
	);
}

/* -------------------------------------------------------------------------
 * STRONA GŁÓWNA SEKCJI: LOGO I OKRUSZKI
 * ---------------------------------------------------------------------- */

/**
 * Adres strony głównej sekcji językowej.
 *
 * Dla polskiego to strona główna serwisu, dla UA i EN — /ua/ i /en/.
 *
 * @param string|null $lang Kod języka; domyślnie bieżący.
 * @return string
 */
function sdc_section_home_url( $lang = null ) {
	$lang     = $lang ? $lang : sdc_current_language();
	$prefixes = sdc_language_prefixes();

	if ( 'pl' === $lang || empty( $prefixes[ $lang ] ) ) {
		return home_url( '/' );
	}

	return home_url( '/' . $prefixes[ $lang ] . '/' );
}

/**
 * Czy bieżąca strona jest stroną główną sekcji UA albo EN.
 *
 * Taka strona pełni rolę strony głównej, więc — jak strona główna serwisu —
 * nie dostaje okruszków.
 *
 * @return bool
 */
function sdc_is_section_home() {
	if ( 'pl' === sdc_current_language() || ! is_page() ) {
		return false;
	}

	return untrailingslashit( get_permalink() ) === untrailingslashit( sdc_section_home_url() );
}

/**
 * Nazwa i logo serwisu prowadzą do strony głównej BIEŻĄCEJ sekcji.
 *
 * Bloki core/site-title i core/site-logo zawsze linkują do strony głównej
 * serwisu. W sekcji ukraińskiej kliknięcie nazwy serwisu — najczęstszy
 * sposób „powrotu na start" — wyrzucało więc na polską stronę główną.
 *
 * @param string $html Wyrenderowany blok.
 * @return string
 */
function sdc_brand_link_to_section_home( $html ) {
	if ( 'pl' === sdc_current_language() ) {
		return $html;
	}

	$home = preg_quote( untrailingslashit( home_url() ), '#' );

	return preg_replace(
		'#href="' . $home . '/?"#',
		'href="' . esc_url( sdc_section_home_url() ) . '"',
		$html
	);
}
add_filter( 'render_block_core/site-title', 'sdc_brand_link_to_section_home' );
add_filter( 'render_block_core/site-logo', 'sdc_brand_link_to_section_home' );

/* -------------------------------------------------------------------------
 * STOPKA I STRONA 404 W JĘZYKU SEKCJI
 * ---------------------------------------------------------------------- */

/**
 * Czy motyw (albo zmiany zapisane w edytorze witryny) ma daną część szablonu.
 *
 * @param string $slug Nazwa części, np. „footer-uk".
 * @return bool
 */
function sdc_template_part_exists( $slug ) {
	static $cache = array();

	if ( ! isset( $cache[ $slug ] ) ) {
		$cache[ $slug ] = (bool) get_block_template( get_stylesheet() . '//' . $slug, 'wp_template_part' );
	}

	return $cache[ $slug ];
}

/**
 * Podmiana części szablonu na wersję językową: „footer" → „footer-uk".
 *
 * Tak samo jak przy menu: jeden szablon strony obsługuje wszystkie języki,
 * a podmieniane jest tylko to, co ma inny tekst. Stopki UA i EN są zwykłymi
 * częściami szablonu, więc redaktor zmienia je w edytorze witryny
 * (Wygląd → Edytor → Wzorce → Części szablonu), bez programisty.
 *
 * Gdy wersji językowej nie ma, zostaje część polska — nigdy pusta stopka.
 *
 * @param array $parsed Sparsowany blok.
 * @return array
 */
function sdc_swap_template_part_by_language( $parsed ) {
	if ( 'core/template-part' !== ( $parsed['blockName'] ?? '' ) ) {
		return $parsed;
	}

	$lang = sdc_current_language();
	$slug = (string) ( $parsed['attrs']['slug'] ?? '' );

	if ( 'pl' === $lang || '' === $slug || preg_match( '/-(uk|en)$/', $slug ) ) {
		return $parsed;
	}

	if ( sdc_template_part_exists( $slug . '-' . $lang ) ) {
		$parsed['attrs']['slug'] = $slug . '-' . $lang;
	}

	return $parsed;
}
add_filter( 'render_block_data', 'sdc_swap_template_part_by_language' );

/**
 * Szablon 404 w języku sekcji.
 *
 * Literówka w adresie w sekcji /ua/ kończyła się polskim komunikatem
 * „Nie znaleźliśmy tej strony" i polskimi podpowiedziami. Wariant językowy
 * (404-uk, 404-en) trafia do hierarchii szablonów PRZED zwykłym 404 —
 * jeżeli go nie ma, WordPress bierze zwykły.
 *
 * @param string[] $templates Hierarchia szablonów.
 * @return string[]
 */
function sdc_language_404_template( $templates ) {
	$lang = sdc_current_language();

	if ( 'pl' !== $lang ) {
		array_unshift( $templates, '404-' . $lang . '.php' );
	}

	return $templates;
}
add_filter( '404_template_hierarchy', 'sdc_language_404_template' );

/**
 * Czytelne nazwy szablonów językowych w edytorze witryny.
 *
 * @param array $types Typy szablonów.
 * @return array
 */
function sdc_language_template_types( $types ) {
	$types['404-uk'] = array(
		'title'       => __( 'Strona 404 (UA)', 'stop-dyskryminacji-core' ),
		'description' => __( 'Wyświetlana, gdy adres w sekcji ukraińskiej nie istnieje.', 'stop-dyskryminacji-core' ),
	);
	$types['404-en'] = array(
		'title'       => __( 'Strona 404 (EN)', 'stop-dyskryminacji-core' ),
		'description' => __( 'Wyświetlana, gdy adres w sekcji angielskiej nie istnieje.', 'stop-dyskryminacji-core' ),
	);

	return $types;
}
add_filter( 'default_template_types', 'sdc_language_template_types' );

/* -------------------------------------------------------------------------
 * PANEL „WERSJA JĘZYKOWA" PRZY EDYCJI STRONY
 *
 * Przełącznik prowadzi do odpowiednika strony, jeżeli wie, który to jest.
 * Bez tego panelu powiązanie dało się ustawić wyłącznie w kodzie, więc każda
 * nowa strona UA albo EN dodana przez redaktora przełączała na stronę główną
 * sekcji zamiast na swój polski odpowiednik.
 * ---------------------------------------------------------------------- */

/**
 * Język strony wynikający z jej miejsca w drzewie stron.
 *
 * Działa także dla szkiców, które nie mają jeszcze docelowego adresu.
 *
 * @param int $post_id Strona.
 * @return string „pl", „uk" albo „en".
 */
function sdc_page_language_from_tree( $post_id ) {
	$ids = array_map( 'intval', array_merge( array( $post_id ), get_post_ancestors( $post_id ) ) );

	foreach ( sdc_language_prefixes() as $code => $prefix ) {
		if ( '' === $prefix ) {
			continue;
		}
		$root = get_page_by_path( $prefix );
		if ( $root && in_array( (int) $root->ID, $ids, true ) ) {
			return $code;
		}
	}

	return 'pl';
}

/**
 * Rejestracja panelu.
 */
function sdc_add_language_meta_box() {
	add_meta_box(
		'sdc-language',
		__( 'Wersja językowa', 'stop-dyskryminacji-core' ),
		'sdc_render_language_meta_box',
		'page',
		'side'
	);
}
add_action( 'add_meta_boxes', 'sdc_add_language_meta_box' );

/**
 * Treść panelu.
 *
 * @param WP_Post $post Edytowana strona.
 */
function sdc_render_language_meta_box( $post ) {
	$langs = sdc_languages();
	$lang  = sdc_page_language_from_tree( $post->ID );

	if ( 'pl' === $lang ) {
		$translations = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'meta_key'       => '_sdc_translation_of',
			'meta_value'     => $post->ID,
		) );

		echo '<p>' . esc_html__( 'Język strony: polski.', 'stop-dyskryminacji-core' ) . '</p>';

		if ( ! $translations ) {
			echo '<p class="description">' . esc_html__( 'Ta strona nie ma tłumaczeń. Tłumaczenie to nowa strona ze stroną nadrzędną „ua" albo „en", w której wskazujesz tę stronę jako polski odpowiednik.', 'stop-dyskryminacji-core' ) . '</p>';
			return;
		}

		echo '<p><strong>' . esc_html__( 'Tłumaczenia:', 'stop-dyskryminacji-core' ) . '</strong></p><ul>';
		foreach ( $translations as $t ) {
			$code = sdc_page_language_from_tree( $t->ID );
			printf(
				'<li>%1$s — <a href="%2$s">%3$s</a></li>',
				esc_html( $langs[ $code ]['label'] ?? strtoupper( $code ) ),
				esc_url( get_edit_post_link( $t->ID ) ),
				esc_html( get_the_title( $t ) )
			);
		}
		echo '</ul>';
		return;
	}

	wp_nonce_field( 'sdc_language_meta', 'sdc_language_nonce' );

	// Sekcji językowych nie pokazujemy na liście — odpowiednik ma być polski.
	$roots = array();
	foreach ( sdc_language_prefixes() as $prefix ) {
		$root = '' !== $prefix ? get_page_by_path( $prefix ) : null;
		if ( $root ) {
			$roots[] = (int) $root->ID;
		}
	}

	// Panel czyta polski redaktor, więc nazwy języków są po polsku.
	$names = array(
		'uk' => __( 'ukraiński', 'stop-dyskryminacji-core' ),
		'en' => __( 'angielski', 'stop-dyskryminacji-core' ),
	);

	printf(
		'<p>%s</p>',
		/* translators: %s: nazwa języka */
		esc_html( sprintf( __( 'Język strony: %s (wynika ze strony nadrzędnej).', 'stop-dyskryminacji-core' ), $names[ $lang ] ?? $langs[ $lang ]['name'] ) )
	);

	printf( '<p><label for="sdc_translation_of"><strong>%s</strong></label></p>', esc_html__( 'Polski odpowiednik', 'stop-dyskryminacji-core' ) );
	$origin = (int) get_post_meta( $post->ID, '_sdc_translation_of', true );
	if ( $origin && 'page' !== get_post_type( $origin ) ) {
		printf( '<p><a href="%s">%s</a></p><input type="hidden" name="sdc_translation_of" value="%d">', esc_url( get_edit_post_link( $origin ) ), esc_html( get_the_title( $origin ) ), $origin );
		return;
	}

	wp_dropdown_pages( array(
		'name'              => 'sdc_translation_of',
		'id'                => 'sdc_translation_of',
		'selected'          => (int) get_post_meta( $post->ID, '_sdc_translation_of', true ),
		'show_option_none'  => __( '— brak —', 'stop-dyskryminacji-core' ),
		'option_none_value' => '0',
		'exclude_tree'      => $roots,
		'sort_column'       => 'menu_order, post_title',
	) );

	echo '<p class="description">' . esc_html__( 'Przełącznik języka prowadzi z tej strony do wskazanej strony polskiej i do jej pozostałych tłumaczeń. Bez wskazania prowadzi na stronę główną sekcji.', 'stop-dyskryminacji-core' ) . '</p>';
}

/**
 * Zapis panelu.
 *
 * @param int $post_id Strona.
 */
function sdc_save_language_meta( $post_id ) {
	if ( ! isset( $_POST['sdc_language_nonce'] )
		|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['sdc_language_nonce'] ) ), 'sdc_language_meta' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_page', $post_id ) ) {
		return;
	}

	$lang = sdc_page_language_from_tree( $post_id );

	// Strona przeniesiona poza sekcję językową przestaje być tłumaczeniem.
	if ( 'pl' === $lang ) {
		delete_post_meta( $post_id, '_sdc_page_lang' );
		delete_post_meta( $post_id, '_sdc_translation_of' );
		return;
	}

	update_post_meta( $post_id, '_sdc_page_lang', $lang );

	$origin = isset( $_POST['sdc_translation_of'] ) ? absint( $_POST['sdc_translation_of'] ) : 0;

	if ( $origin && $origin !== (int) $post_id && in_array( get_post_type( $origin ), array( 'page', 'post', 'publikacja', 'instytucja', 'praktyka' ), true ) && 'pl' === sdc_page_language_from_tree( $origin ) ) {
		update_post_meta( $post_id, '_sdc_translation_of', $origin );
	} else {
		delete_post_meta( $post_id, '_sdc_translation_of' );
	}
}
add_action( 'save_post_page', 'sdc_save_language_meta' );
