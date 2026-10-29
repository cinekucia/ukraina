<?php
/**
 * Dostępność — WCAG 2.1 AA.
 *
 * Wymaganie umowne (Załącznik nr 1, etap 5 oraz Załącznik nr 2 — kryteria odbioru):
 * zgodność z WCAG 2.1 AA dla wszystkich szablonów, nawigacja klawiaturą,
 * poprawna struktura nagłówków, opisy alternatywne.
 *
 * Plik gromadzi te elementy dostępności, których nie da się załatwić samym CSS
 * ani samą strukturą szablonu blokowego.
 *
 * @package StopDyskryminacji
 */

defined( 'ABSPATH' ) || exit;

/**
 * Odnośnik pomijający nawigację (WCAG 2.4.1 Bypass Blocks).
 *
 * Musi być pierwszym elementem otrzymującym fokus na stronie — stąd
 * wywołanie na „wp_body_open" z priorytetem 1. Ważne także dlatego, że
 * Załącznik nr 10 rozdz. 9 wymaga, aby zestaw znaków nie był jedynym
 * elementem nagłówka przechwytującym fokus klawiatury.
 */
function sd_skip_link() {
	printf(
		'<a class="sd-skip-link" href="#sd-main">%s</a>',
		esc_html__( 'Przejdź do treści głównej', 'stop-dyskryminacji' )
	);
}
add_action( 'wp_body_open', 'sd_skip_link', 1 );

/*
 * WordPress dokłada do motywów blokowych własny, ukryty odnośnik „Skip to
 * content". Motyw ma swój (wyżej), więc osoba poruszająca się klawiaturą
 * trafiała na dwa kolejne odnośniki prowadzące w to samo miejsce. Odpięcie
 * tej akcji to udokumentowany w rdzeniu sposób wyłączenia odnośnika rdzenia.
 */
remove_action( 'wp_enqueue_scripts', 'wp_enqueue_block_template_skip_link' );
remove_action( 'wp_footer', 'the_block_template_skip_link' );   // starsze wersje WordPressa

/**
 * Uzupełnienie ogólnych odnośników „Czytaj dalej" o kontekst dla czytnika ekranu
 * (WCAG 2.4.4 Link Purpose). Sam tekst „Czytaj dalej" nie mówi, o czym jest wpis.
 *
 * @param string $html Znacznik odnośnika.
 * @return string
 */
function sd_more_link_context( $html ) {
	if ( false === strpos( $html, 'sd-sr-only' ) ) {
		$context = sprintf(
			' <span class="sd-sr-only">%s</span>',
			esc_html( sprintf( __( 'o: %s', 'stop-dyskryminacji' ), wp_strip_all_tags( get_the_title() ) ) )
		);
		$html    = str_replace( '</a>', $context . '</a>', $html );
	}
	return $html;
}
add_filter( 'the_content_more_link', 'sd_more_link_context' );
add_filter( 'excerpt_more', 'sd_more_link_context' );

/**
 * Atrybut lang na elemencie <html> zgodny z językiem treści (WCAG 3.1.1).
 * Filtr pozwala wtyczce wielojęzycznej podmienić wartość dla wersji UA/EN.
 *
 * @param string $output Atrybuty języka.
 * @return string
 */
function sd_language_attributes( $output ) {
	$lang = sd_content_language();
	if ( $lang && false === strpos( $output, 'lang="' . $lang ) ) {
		$output = preg_replace( '/lang="[^"]*"/', 'lang="' . esc_attr( $lang ) . '"', $output );
	}
	return $output;
}
add_filter( 'language_attributes', 'sd_language_attributes' );

/**
 * Ostrzeżenie w bibliotece mediów o brakującym tekście alternatywnym.
 *
 * Audyt dostępności jest zlecany zewnętrznie i to jego uwagi decydują o odbiorze —
 * taniej jest nie dopuścić do powstania obrazu bez opisu, niż poprawiać po audycie.
 *
 * @param array   $form_fields Pola formularza załącznika.
 * @param WP_Post $post        Załącznik.
 * @return array
 */
function sd_alt_text_reminder( $form_fields, $post ) {
	if ( 0 !== strpos( (string) get_post_mime_type( $post ), 'image/' ) ) {
		return $form_fields;
	}
	// Pojedyncza spacja to świadome „obraz dekoracyjny" — wtedy nie przypominamy.
	$alt = get_post_meta( $post->ID, '_wp_attachment_image_alt', true );
	if ( '' === (string) $alt && isset( $form_fields['image_alt'] ) ) {
		$form_fields['image_alt']['helps'] =
			'<strong style="color:#8e0d17">' .
			esc_html__( 'Brak tekstu alternatywnego — wymagany przez WCAG 2.1 AA.', 'stop-dyskryminacji' ) .
			'</strong> ' .
			esc_html__( 'Opisz, co przedstawia obraz. Jeśli obraz jest wyłącznie dekoracyjny, wpisz pojedynczą spację.', 'stop-dyskryminacji' );
	}
	return $form_fields;
}
add_filter( 'attachment_fields_to_edit', 'sd_alt_text_reminder', 10, 2 );

/**
 * Miniatura na karcie: jeden odnośnik na kartę dla klawiatury i czytnika ekranu.
 *
 * Blok „Obraz wyróżniający" z włączonym odnośnikiem tworzy drugi odnośnik do tego
 * samego wpisu, a WordPress wstawia mu tekst alternatywny równy tytułowi. Efekt:
 * na liście artykułów każdy kafelek miał dwa przystanki Taba i był ogłaszany
 * dwa razy tym samym zdaniem. Odnośnik zostaje dla myszy (duży cel), ale znika
 * z drzewa dostępności i z kolejności fokusu — nazwę karty niesie odnośnik
 * w nagłówku (WCAG 2.4.4, 2.4.3).
 *
 * @param string $html  Wyrenderowany blok.
 * @param array  $block Blok.
 * @return string
 */
function sd_card_thumb_single_link( $html, $block ) {
	$classes = isset( $block['attrs']['className'] ) ? (string) $block['attrs']['className'] : '';

	if ( empty( $block['attrs']['isLink'] ) || false === strpos( $classes, 'sd-card__thumb' ) ) {
		return $html;
	}

	$tags = new WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag( 'a' ) ) {
		$tags->set_attribute( 'tabindex', '-1' );
		$tags->set_attribute( 'aria-hidden', 'true' );
	}

	return $tags->get_updated_html();
}
add_filter( 'render_block_core/post-featured-image', 'sd_card_thumb_single_link', 10, 2 );

/**
 * Nadanie tytułu ramkom osadzonym (np. nagrania z konferencji), jeżeli
 * dostawca go nie ustawił. Ramka bez tytułu jest uwagą w audycie (WCAG 4.1.2).
 *
 * @param string $html Kod osadzenia.
 * @return string
 */
function sd_iframe_title( $html ) {
	if ( is_string( $html ) && false !== strpos( $html, '<iframe' ) && false === strpos( $html, 'title=' ) ) {
		$html = str_replace(
			'<iframe',
			'<iframe title="' . esc_attr__( 'Materiał wideo', 'stop-dyskryminacji' ) . '"',
			$html
		);
	}
	return $html;
}
add_filter( 'embed_oembed_html', 'sd_iframe_title' );
add_filter( 'video_embed_html', 'sd_iframe_title' );
