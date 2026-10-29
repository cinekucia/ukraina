<?php
/**
 * Obowiązkowe oznakowanie projektu: FE / RP / UE / herb Województwa.
 *
 * Podstawa: Załącznik nr 10 do umowy zlecenia — „Zasady promocji projektu
 * i identyfikacja wizualna", oraz art. 50 rozporządzenia (UE) 2021/1060.
 *
 * NAJWAŻNIEJSZE ZASADY, KTÓRE TEN PLIK REALIZUJE
 * ----------------------------------------------
 * rozdz. 5  Zestaw znaków na samej górze KAŻDEJ podstrony, widoczny bez
 *           przewijania. Umieszczenie wyłącznie w stopce NIE spełnia wymagania
 *           — Załącznik nazywa to wprost „najczęstszym błędem".
 * rozdz. 5  Plik ładowany z serwera projektu, nie z adresu zewnętrznego.
 * rozdz. 5  Znaki nie są elementem tła CSS — muszą być osadzone jako element
 *           treści z tekstem alternatywnym.
 * rozdz. 4  Na stronie internetowej wyłącznie wersja pełnokolorowa; wersja
 *           achromatyczna jest zakazana. Tło białe.
 * rozdz. 3  Kolejność od lewej: FE dla Wielkopolski → barwy RP → UE → herb.
 * rozdz. 9  Dokładna treść tekstu alternatywnego, narzucona co do słowa.
 * rozdz. 7  Open Graph i Twitter Card z obrazem 1200 × 630 px zawierającym
 *           zestaw znaków.
 *
 * Pliki pochodzą z paczki KOLOR_POZIOM.zip ze strony programu. Oryginał leży
 * w assets/brand/*-original.png i jest bajtowo niezmieniony; pozostałe rozmiary
 * to wyłącznie przeskalowanie tego samego pliku, bez zmiany barw i kadru.
 * Sumy kontrolne i proweniencja: assets/brand/PROWENIENCJA.json.
 *
 * @package StopDyskryminacjiCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Treść tekstu alternatywnego narzucona przez Załącznik nr 10 rozdz. 9.
 * Nie zmieniać — jest sprawdzana w punkcie 7 listy kontrolnej odbioru.
 */
const SDC_LOCKUP_ALT = 'Znaki: Fundusze Europejskie dla Wielkopolski, barwy Rzeczypospolitej Polskiej, Dofinansowane przez Unię Europejską, Herb Województwa Wielkopolskiego';

/**
 * Wariant językowy zestawu znaków.
 *
 * Paczka programu zawiera wersję polską i angielską. Dla wersji ukraińskiej
 * program nie udostępnia osobnego pliku, a znaków nie wolno odtwarzać
 * samodzielnie — dlatego UA korzysta z zestawu polskiego.
 *
 * @return string „pl" albo „en".
 */
function sdc_lockup_lang() {
	/*
	 * Domyślnie POLSKI, nie „cokolwiek wynika z ustawień".
	 *
	 * Świeża instalacja WordPressa ma język en_US, dopóki ktoś go nie zmieni.
	 * Przy zwykłym mapowaniu locale → wariant znaków oznaczałoby to angielski
	 * zestaw na polskiej stronie projektu — uchybienie warte 1 956,47 zł,
	 * w dodatku ciche: strona wygląda poprawnie, tylko znaki są nie te.
	 *
	 * Dlatego wariant angielski wchodzi wyłącznie wtedy, gdy język został
	 * ŚWIADOMIE ustawiony na angielski (opcja WPLANG jest niepusta i zaczyna
	 * się od „en"). Brak ustawienia = polski.
	 */
	$lang = 'pl';

	/*
	 * Pierwszeństwo ma język SEKCJI wynikający z adresu (/en/, /ua/).
	 * Przedrostek w adresie to świadoma decyzja redaktora, więc spełnia
	 * warunek „język ustawiony świadomie" — inaczej niż domyślne en_US
	 * świeżej instalacji.
	 *
	 * Dla ukraińskiego zostaje wariant polski: program nie udostępnia
	 * zestawu ukraińskiego, a znaków nie wolno odtwarzać samodzielnie
	 * (Załącznik nr 10, wstęp).
	 */
	if ( function_exists( 'sdc_current_language' ) ) {
		if ( 'en' === sdc_current_language() ) {
			return 'en';
		}
		if ( 'uk' === sdc_current_language() ) {
			return 'pl';
		}
	}

	$configured = trim( (string) ( is_multisite() ? get_site_option( 'WPLANG', '' ) : get_option( 'WPLANG', '' ) ) );
	if ( '' === $configured ) {
		$configured = trim( (string) get_option( 'WPLANG', '' ) );
	}

	if ( '' !== $configured && 0 === strpos( strtolower( $configured ), 'en' ) ) {
		$lang = 'en';
	}

	/*
	 * Filtr dla przyszłej wersji wielojęzycznej: wtyczka tłumacząca może
	 * wskazać wariant per język strony. Zwracamy tylko „pl" albo „en",
	 * bo tylko takie warianty zawiera paczka programu.
	 */
	$lang = apply_filters( 'sdc_lockup_lang', $lang );

	return 'en' === $lang ? 'en' : 'pl';
}

/**
 * Adres pliku zestawu znaków o danej szerokości.
 *
 * @param int    $width Szerokość wariantu.
 * @param string $lang  Wersja językowa.
 * @return string
 */
function sdc_lockup_url( $width, $lang = 'pl' ) {
	return SDC_URL . 'assets/brand/zestawienie-znakow-' . $lang . '-' . $width . '.png';
}

/**
 * Znacznik <img> zestawu znaków wraz z srcset.
 *
 * @param string $sizes Wartość atrybutu sizes.
 * @param string $class Klasa CSS.
 * @param bool   $eager Czy ładować natychmiast (pasek nad treścią — tak).
 * @return string
 */
function sdc_lockup_img( $sizes, $class, $eager = true ) {
	$lang = sdc_lockup_lang();

	// Proporcje oryginału: PL 6946×710, EN 6118×710.
	$ratio  = 'en' === $lang ? 6118 / 710 : 6946 / 710;
	$width  = 900;
	$height = (int) round( $width / $ratio );

	$srcset = implode(
		', ',
		array(
			sdc_lockup_url( 640, $lang ) . ' 640w',
			sdc_lockup_url( 900, $lang ) . ' 900w',
			sdc_lockup_url( 1200, $lang ) . ' 1200w',
			sdc_lockup_url( 1800, $lang ) . ' 1800w',
		)
	);

	// The image variant and the language of its accessible description are independent.
	$alt_lang = function_exists( 'sdc_current_language' ) ? sdc_current_language() : 'pl';
	$alt = function_exists( 'sdc_content_translate' ) ? sdc_content_translate( SDC_LOCKUP_ALT, $alt_lang ) : SDC_LOCKUP_ALT;
	return sprintf(
		'<img class="%1$s" src="%2$s" srcset="%3$s" sizes="%4$s" width="%5$d" height="%6$d" alt="%7$s" lang="%9$s" %8$s decoding="async">',
		esc_attr( $class ),
		esc_url( sdc_lockup_url( 900, $lang ) ),
		esc_attr( $srcset ),
		esc_attr( $sizes ),
		$width,
		$height,
		esc_attr( $alt ),
		// Pasek u góry strony jest w pierwszym ekranie — nie może być leniwy,
		// bo znika z widoku „bez przewijania" na wolnym łączu.
		$eager ? 'loading="eager" fetchpriority="high"' : 'loading="lazy"',
		esc_attr( $alt_lang )
	);
}

/**
 * Renderowanie bloku stopdysk/eu-lockup.
 *
 * @param array $attributes Atrybuty bloku.
 * @return string
 */
function sdc_render_eu_lockup( $attributes = array() ) {
	$context = isset( $attributes['context'] ) ? $attributes['context'] : 'header';

	// Sygnał dla kontroli na końcu żądania — patrz sdc_lockup_fallback().
	do_action( 'sdc_lockup_rendered', $context );

	if ( 'footer' === $context ) {
		return '<div class="sd-footer__brand">' .
			sdc_lockup_img( '(max-width: 75rem) 90vw, 68rem', 'sd-footer__brand-img', false ) .
			'</div>';
	}

	if ( 'inline' === $context ) {
		return '<p class="sd-lockup-inline">' .
			sdc_lockup_img( '(max-width: 48rem) 88vw, 40rem', 'sd-lockup-inline__img', false ) .
			'</p>';
	}

	/*
	 * Wariant domyślny: pasek na samej górze strony.
	 *
	 * Po prawej stronie paska jest miejsce na elementy uzupełniające —
	 * korzysta z niego przełącznik języka (languages.php). Załącznik nr 10
	 * rozdz. 4 wymaga wolnego pola wokół zestawu o szerokości co najmniej
	 * wysokości flagi UE, więc odstęp pilnuje tego w CSS (--sd-lockup-clear).
	 * Elementy uzupełniające to wyłącznie tekst — żaden dodatkowy ZNAK
	 * nie może trafić do linii zestawienia (rozdz. 3).
	 */
	$extra = apply_filters( 'sdc_eu_bar_extra', '' );

	return '<div class="sd-eu-bar"><div class="sd-eu-bar__inner">' .
		sdc_lockup_img( '(max-width: 48.6rem) 91vw, 704px', 'sd-eu-bar__img', true ) .
		$extra .
		'</div></div>';
}

/**
 * Znaczniki Open Graph i Twitter Card.
 *
 * Punkt 12 listy kontrolnej odbioru: obraz podglądu 1200 × 630 px zawierający
 * zestaw znaków. Obraz jest ten sam na każdej podstronie — uzasadnienie
 * w komentarzu przy zmiennej $image poniżej.
 */
function sdc_open_graph_tags() {
	if ( is_admin() || is_feed() ) {
		return;
	}

	$is_single = is_singular();
	$title     = $is_single ? get_the_title() : get_bloginfo( 'name' );
	$seo_title = $is_single ? get_post_meta( get_queried_object_id(), '_sdc_meta_title', true ) : '';
	if ( $seo_title ) { $title = $seo_title; }
	$site      = get_bloginfo( 'name' );

	$description = $is_single ? get_the_excerpt() : get_bloginfo( 'description' );
	if ( ! $description ) {
		$description = __( 'Platforma o równym traktowaniu w zatrudnieniu. Wiedza, praktyczne narzędzia i wsparcie dla Wielkopolski.', 'stop-dyskryminacji-core' );
	}
	$seo_description = $is_single ? get_post_meta( get_queried_object_id(), '_sdc_meta_description', true ) : '';
	$description = $seo_description ?: wp_trim_words( wp_strip_all_tags( $description ), 40 );
	if ( function_exists( 'sdc_seo_metadata' ) ) {
		$metadata = sdc_seo_metadata();
		$title = $metadata['title'] ?: $title;
		$description = $metadata['description'] ?: $description;
	}

	$url = $is_single ? get_permalink() : home_url( '/' );
	if ( function_exists( 'sdc_seo_canonical_url' ) ) { $url = sdc_seo_canonical_url() ?: $url; }

	/*
	 * Obraz podglądu jest ZAWSZE brandowany i zawsze ten sam.
	 *
	 * Punkt 12 listy kontrolnej (Zał. nr 10) wymaga, aby przy udostępnianiu
	 * adresu strony wyświetlał się obraz 1200 × 630 px ZAWIERAJĄCY ZESTAW ZNAKÓW.
	 * Obraz wyróżniający wpisu tego zestawu nie zawiera, więc podstawianie go
	 * tutaj — choć ładniejsze w mediach społecznościowych — łamie wymóg na
	 * każdej podstronie, która ma własną grafikę. Stawka za uchybienie to
	 * 1 956,47 zł, więc wybieramy pewność zamiast urody podglądu.
	 *
	 * Gdyby Zamawiający chciał w przyszłości podglądy per artykuł, właściwą
	 * drogą jest generowanie kompozycji „grafika wpisu + pasek znaków"
	 * do osobnego załącznika, a nie podstawianie samego obrazu wyróżniającego.
	 */
	$image   = SDC_URL . 'assets/brand/og-image-' . sdc_lockup_lang() . '.png';
	$img_w   = 1200;
	$img_h   = 630;
	$img_alt = SDC_LOCKUP_ALT;

	$tags = array(
		'og:site_name'        => $site,
		'og:type'             => $is_single ? 'article' : 'website',
		'og:locale'           => get_locale(),
		'og:title'            => $title,
		'og:description'      => $description,
		'og:url'              => $url,
		'og:image'            => $image,
		'og:image:width'      => (string) $img_w,
		'og:image:height'     => (string) $img_h,
		'og:image:alt'        => $img_alt,
		'twitter:card'        => 'summary_large_image',
		'twitter:title'       => $title,
		'twitter:description' => $description,
		'twitter:image'       => $image,
		'twitter:image:alt'   => $img_alt,
	);

	echo "\n<!-- Oznakowanie projektu: Open Graph / Twitter Card -->\n";
	foreach ( $tags as $key => $value ) {
		if ( '' === (string) $value ) {
			continue;
		}
		printf(
			'<meta %1$s="%2$s" content="%3$s">' . "\n",
			0 === strpos( $key, 'twitter:' ) ? 'name' : 'property',
			esc_attr( $key ),
			esc_attr( $value )
		);
	}
}
add_action( 'wp_head', 'sdc_open_graph_tags', 5 );

/**
 * Style oznakowania ładowane z wtyczki.
 *
 * Powtarzają minimum tego, co jest w motywie. Powód: gdyby motyw został
 * wymieniony albo uszkodzony, pasek znaków nadal musi wyglądać poprawnie —
 * a jego brak kosztuje 1 956,47 zł pomniejszenia dofinansowania.
 *
 * Geometria (uzasadnienie w main.css motywu, sekcja 3): wysokość zestawu
 * 72 px na komputerze, proporcjonalnie mniej na wąskim ekranie (szerokość
 * paska / 10,8), wolne pole dookoła = połowa wysokości pliku — razem
 * z marginesem wbudowanym w plik daje to co najmniej wysokość flagi UE.
 * Obie kopie reguł muszą być identyczne, bo ładują się w różnej kolejności.
 */
function sdc_branding_styles() {
	$css = '
	.sd-eu-bar{background:#fff;border-bottom:1px solid #dcd6cb;container-type:inline-size;--sd-lockup-h:min(72px,calc(100vw / 11.6))}
	@supports (width:1cqi){.sd-eu-bar__inner{--sd-lockup-h:min(72px,calc(100cqi / 11))}}
	@media (max-height:30em){
		.sd-eu-bar{--sd-lockup-h:min(44px,calc(100vw / 11.6))}
		@supports (width:1cqi){.sd-eu-bar__inner{--sd-lockup-h:min(44px,calc(100cqi / 11))}}
	}
	.sd-eu-bar__inner{--sd-lockup-clear:calc(var(--sd-lockup-h) * .5);max-width:75rem;margin-inline:auto;padding:var(--sd-lockup-clear) 1.5rem;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;column-gap:max(var(--sd-lockup-clear),2.5rem);row-gap:var(--sd-lockup-clear)}
	@media (max-width:77.99em){.sd-eu-bar__inner{padding-inline:var(--sd-lockup-clear)}}
	.sd-eu-bar__img{display:block;height:var(--sd-lockup-h);width:auto;max-width:none;flex:0 0 auto}
	.sd-footer__brand-img,.sd-lockup-inline__img{display:block;max-width:100%;height:auto;margin-inline:auto}
	.sd-lockup-inline{background:#fff;padding:1rem;border-radius:8px;margin-block:1rem}
	:root{--sd-sign-h:calc(min(72px,calc(100vw / 11.6)) * .6)}
	@media (max-height:30em){:root{--sd-sign-h:calc(min(44px,calc(100vw / 11.6)) * .6)}}
	.sd-brand__mark{display:block;height:var(--sd-sign-h);width:auto;flex:0 0 auto}
	';
	wp_register_style( 'sdc-branding', false, array(), SDC_VERSION );
	wp_enqueue_style( 'sdc-branding' );
	wp_add_inline_style( 'sdc-branding', preg_replace( '/\s+/', ' ', $css ) );
}
add_action( 'wp_enqueue_scripts', 'sdc_branding_styles' );

/* =========================================================================
 * Logo projektu „Dymek równości"
 * ====================================================================== */

/**
 * Sygnet jako SVG w treści strony — plik assets/brand/sygnet.svg (źródło:
 * tools/build_brand_icons.py) z atrybutami dla elementu ozdobnego.
 *
 * Wysokość sygnetu wynika z wysokości zestawu znaków FE w pasku (zmienna
 * --sd-sign-h = 0,6 wysokości zestawu), więc nigdy nie przekracza flagi UE
 * (0,662 wysokości zestawu) — warunek z projektu znaku i Załącznika nr 10.
 *
 * @return string
 */
function sdc_brand_mark_svg() {
	static $svg = null;
	if ( null === $svg ) {
		$file = SDC_PATH . 'assets/brand/sygnet.svg';
		$svg  = file_exists( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
		$svg  = preg_replace( '#<title>.*?</title>#s', '', trim( $svg ) );
		$svg  = preg_replace( '#^<svg #', '<svg class="sd-brand__mark" aria-hidden="true" focusable="false" ', $svg );
	}
	return $svg;
}

/**
 * Blok „Logo serwisu": sygnet i nazwa, odnośnik do strony głównej bieżącej
 * wersji językowej. Pierwsze słowo nazwy („STOP") w kolorze marki.
 *
 * @return string
 */
function sdc_render_brand() {
	$name = get_bloginfo( 'name' );
	if ( preg_match( '/^(\S+)\s+(.+)$/u', $name, $m ) ) {
		$label = '<span class="sd-brand__stop">' . esc_html( $m[1] ) . '</span> ' . esc_html( $m[2] );
	} else {
		$label = esc_html( $name );
	}
	$home = function_exists( 'sdc_section_home_url' ) ? sdc_section_home_url() : home_url( '/' );

	return sprintf(
		'<a class="sd-brand" href="%1$s">%2$s<span class="sd-brand__name">%3$s</span></a>',
		esc_url( $home ),
		sdc_brand_mark_svg(),
		$label
	);
}

/**
 * Ikona karty przeglądarki i ekranu startowego — dopóki w Wygląd → Dostosuj
 * nie ustawiono własnej ikony witryny.
 */
function sdc_favicon_links() {
	if ( has_site_icon() ) {
		return;
	}
	$base = SDC_URL . 'assets/brand/';
	printf( '<link rel="icon" href="%s" sizes="32x32" type="image/png">' . "\n", esc_url( $base . 'favicon-32.png?v=' . SDC_VERSION ) );
	printf( '<link rel="icon" href="%s" type="image/svg+xml">' . "\n", esc_url( $base . 'favicon.svg?v=' . SDC_VERSION ) );
	printf( '<link rel="apple-touch-icon" href="%s">' . "\n", esc_url( $base . 'favicon-180.png?v=' . SDC_VERSION ) );
}
add_action( 'wp_head', 'sdc_favicon_links', 2 );
add_action( 'admin_head', 'sdc_favicon_links' );
add_action( 'login_head', 'sdc_favicon_links' );

/**
 * Zabezpieczenie na wypadek usunięcia paska z szablonu.
 *
 * Jeżeli szablon nie wyrenderował zestawu znaków, wstrzykujemy go zaraz po
 * otwarciu <body>. Lepiej mieć oznakowanie w nieidealnym miejscu niż nie mieć
 * go wcale. W dzienniku pojawia się ostrzeżenie, żeby usterkę naprawić u źródła.
 */
function sdc_lockup_fallback() {
	if ( is_admin() ) {
		return;
	}
	add_action(
		'wp_footer',
		function () {
			if ( ! did_action( 'sdc_lockup_rendered' ) ) {
				sdc_audit_log( 'oznakowanie_brak', 0, 'Zestaw znaków nie został wyrenderowany przez szablon' );
			}
		},
		999
	);
}
add_action( 'wp_body_open', 'sdc_lockup_fallback', 0 );
