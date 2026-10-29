<?php
/**
 * Model treści: typy wpisów i taksonomie.
 *
 * Etap 2 i 4 zakresu obowiązków — „typy treści i taksonomie, model danych".
 *
 * DECYZJA: artykuły korzystają z wbudowanego typu „post", a nie z osobnego CPT.
 * Wbudowany typ ma gotowe kanały RSS, wyszukiwanie, archiwa kategorii i obsługę
 * w każdej wtyczce wielojęzycznej. Osobny CPT „artykul" nie dałby tu nic poza
 * kolejną pozycją w menu i koniecznością odtwarzania tych funkcji ręcznie.
 * Zmieniamy więc tylko etykiety.
 *
 * @package StopDyskryminacjiCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rejestracja typów treści.
 */
function sdc_register_post_types() {

	/* --- Publikacje: Kodeks, poradniki, kompendium --------------------- */
	register_post_type(
		'publikacja',
		array(
			'labels'        => array(
				'name'               => __( 'Publikacje', 'stop-dyskryminacji-core' ),
				'singular_name'      => __( 'Publikacja', 'stop-dyskryminacji-core' ),
				'add_new'            => __( 'Dodaj nową', 'stop-dyskryminacji-core' ),
				'add_new_item'       => __( 'Dodaj nową publikację', 'stop-dyskryminacji-core' ),
				'edit_item'          => __( 'Edytuj publikację', 'stop-dyskryminacji-core' ),
				'new_item'           => __( 'Nowa publikacja', 'stop-dyskryminacji-core' ),
				'view_item'          => __( 'Zobacz publikację', 'stop-dyskryminacji-core' ),
				'all_items'          => __( 'Wszystkie publikacje', 'stop-dyskryminacji-core' ),
				'search_items'       => __( 'Szukaj publikacji', 'stop-dyskryminacji-core' ),
				'not_found'          => __( 'Nie znaleziono publikacji.', 'stop-dyskryminacji-core' ),
				'featured_image'     => __( 'Okładka publikacji', 'stop-dyskryminacji-core' ),
				'set_featured_image' => __( 'Ustaw okładkę', 'stop-dyskryminacji-core' ),
			),
			'public'        => true,
			'has_archive'   => true,
			'rewrite'       => array( 'slug' => 'publikacje', 'with_front' => false ),
			'menu_icon'     => 'dashicons-book-alt',
			'menu_position' => 5,
			'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'custom-fields', 'revisions' ),
			'show_in_rest'  => true,
			'template'      => array(
				array( 'core/paragraph', array( 'placeholder' => __( 'Krótki opis publikacji — co czytelnik w niej znajdzie.', 'stop-dyskryminacji-core' ) ) ),
			),
		)
	);

	/* --- Dobre praktyki: baza filtrowana zamiast PDF-a ------------------ */
	// Rekomendacja nr 4 z benchmarku (JAN SOAR, EU Platform of Diversity Charters):
	// kompendium działa lepiej jako przeszukiwalna baza niż jako plik do pobrania.
	register_post_type(
		'praktyka',
		array(
			'labels'       => array(
				'name'          => __( 'Dobre praktyki', 'stop-dyskryminacji-core' ),
				'singular_name' => __( 'Dobra praktyka', 'stop-dyskryminacji-core' ),
				'add_new_item'  => __( 'Dodaj dobrą praktykę', 'stop-dyskryminacji-core' ),
				'edit_item'     => __( 'Edytuj dobrą praktykę', 'stop-dyskryminacji-core' ),
				'all_items'     => __( 'Wszystkie praktyki', 'stop-dyskryminacji-core' ),
			),
			'public'       => true,
			'has_archive'  => true,
			'rewrite'      => array( 'slug' => 'dobre-praktyki', 'with_front' => false ),
			'menu_icon'    => 'dashicons-lightbulb',
			'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
			'show_in_rest' => true,
		)
	);

	/* --- Instytucje pomocowe w Wielkopolsce ----------------------------- */
	// Rekomendacja nr 9: nawigator instytucji (PIP, RPO, sądy pracy, NGO).
	register_post_type(
		'instytucja',
		array(
			'labels'       => array(
				'name'          => __( 'Instytucje pomocowe', 'stop-dyskryminacji-core' ),
				'singular_name' => __( 'Instytucja', 'stop-dyskryminacji-core' ),
				'add_new_item'  => __( 'Dodaj instytucję', 'stop-dyskryminacji-core' ),
				'all_items'     => __( 'Wszystkie instytucje', 'stop-dyskryminacji-core' ),
			),
			'public'       => true,
			'has_archive'  => false,
			'rewrite'      => array( 'slug' => 'instytucje', 'with_front' => false ),
			'menu_icon'    => 'dashicons-building',
			'supports'     => array( 'title', 'editor', 'custom-fields' ),
			'show_in_rest' => true,
		)
	);

	/* --- Zgłoszenia: dane szczególnych kategorii, nigdy publiczne -------- */
	register_post_type(
		'zgloszenie',
		array(
			'labels'              => array(
				'name'          => __( 'Zgłoszenia', 'stop-dyskryminacji-core' ),
				'singular_name' => __( 'Zgłoszenie', 'stop-dyskryminacji-core' ),
				'all_items'     => __( 'Wszystkie zgłoszenia', 'stop-dyskryminacji-core' ),
				'search_items'  => __( 'Szukaj zgłoszeń', 'stop-dyskryminacji-core' ),
			),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => false,   // brak wystawienia przez REST API
			'menu_icon'           => 'dashicons-shield-alt',
			'menu_position'       => 26,
			'capability_type'     => array( 'zgloszenie', 'zgloszenia' ),
			'map_meta_cap'        => true,
			'capabilities'        => array(
				'create_posts' => 'do_not_allow',   // powstają wyłącznie z formularza
			),
			'supports'            => array( 'title' ),
			'rewrite'             => false,
			'query_var'           => false,
			'can_export'          => false,        // nie trafiają do eksportu WXR
		)
	);
}
add_action( 'init', 'sdc_register_post_types' );

/**
 * Rejestracja taksonomii.
 */
function sdc_register_taxonomies() {

	$shared = array( 'publikacja', 'post', 'praktyka' );

	// Przesłanka dyskryminacji — wspólny wymiar dla publikacji, artykułów i praktyk.
	register_taxonomy(
		'przeslanka',
		$shared,
		array(
			'labels'            => array(
				'name'          => __( 'Przesłanki', 'stop-dyskryminacji-core' ),
				'singular_name' => __( 'Przesłanka', 'stop-dyskryminacji-core' ),
				'add_new_item'  => __( 'Dodaj przesłankę', 'stop-dyskryminacji-core' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'przeslanka', 'with_front' => false ),
		)
	);

	// Grupa odbiorców — odpowiada czterem ścieżkom ze strony głównej.
	register_taxonomy(
		'grupa_odbiorcow',
		$shared,
		array(
			'labels'            => array(
				'name'          => __( 'Grupy odbiorców', 'stop-dyskryminacji-core' ),
				'singular_name' => __( 'Grupa odbiorców', 'stop-dyskryminacji-core' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'dla', 'with_front' => false ),
		)
	);

	register_taxonomy(
		'typ_publikacji',
		array( 'publikacja' ),
		array(
			'labels'            => array(
				'name'          => __( 'Typy publikacji', 'stop-dyskryminacji-core' ),
				'singular_name' => __( 'Typ publikacji', 'stop-dyskryminacji-core' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'typ-publikacji', 'with_front' => false ),
		)
	);

	register_taxonomy(
		'branza',
		array( 'praktyka' ),
		array(
			'labels'            => array( 'name' => __( 'Branże', 'stop-dyskryminacji-core' ), 'singular_name' => __( 'Branża', 'stop-dyskryminacji-core' ) ),
			'hierarchical'      => true,
			'public'            => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'branza', 'with_front' => false ),
		)
	);

	register_taxonomy(
		'wielkosc_firmy',
		array( 'praktyka' ),
		array(
			'labels'            => array( 'name' => __( 'Wielkość firmy', 'stop-dyskryminacji-core' ), 'singular_name' => __( 'Wielkość firmy', 'stop-dyskryminacji-core' ) ),
			'hierarchical'      => true,
			'public'            => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'wielkosc-firmy', 'with_front' => false ),
		)
	);

	register_taxonomy(
		'typ_instytucji',
		array( 'instytucja' ),
		array(
			'labels'            => array( 'name' => __( 'Typy instytucji', 'stop-dyskryminacji-core' ), 'singular_name' => __( 'Typ instytucji', 'stop-dyskryminacji-core' ) ),
			'hierarchical'      => true,
			'public'            => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
		)
	);
}
add_action( 'init', 'sdc_register_taxonomies' );

/**
 * Zmiana etykiet wbudowanego typu „Wpisy" na „Artykuły".
 *
 * Używamy filtra „post_type_labels_post", a nie „register_post_type_args":
 * ten drugi dostaje tablicę argumentów, w której etykiety nie są jeszcze
 * zbudowane — WordPress generuje je dopiero po zastosowaniu tamtego filtra.
 * „post_type_labels_{$post_type}" dostaje gotowy obiekt etykiet i jest
 * właściwym miejscem na taką zmianę.
 *
 * @param object $labels Etykiety typu „post".
 * @return object
 */
function sdc_rename_posts( $labels ) {
	$overrides = array(
		'name'           => __( 'Artykuły', 'stop-dyskryminacji-core' ),
		'singular_name'  => __( 'Artykuł', 'stop-dyskryminacji-core' ),
		'menu_name'      => __( 'Artykuły', 'stop-dyskryminacji-core' ),
		'name_admin_bar' => __( 'Artykuł', 'stop-dyskryminacji-core' ),
		'add_new_item'   => __( 'Dodaj nowy artykuł', 'stop-dyskryminacji-core' ),
		'edit_item'      => __( 'Edytuj artykuł', 'stop-dyskryminacji-core' ),
		'new_item'       => __( 'Nowy artykuł', 'stop-dyskryminacji-core' ),
		'view_item'      => __( 'Zobacz artykuł', 'stop-dyskryminacji-core' ),
		'view_items'     => __( 'Zobacz artykuły', 'stop-dyskryminacji-core' ),
		'all_items'      => __( 'Wszystkie artykuły', 'stop-dyskryminacji-core' ),
		'search_items'   => __( 'Szukaj artykułów', 'stop-dyskryminacji-core' ),
		'not_found'      => __( 'Nie znaleziono artykułów.', 'stop-dyskryminacji-core' ),
	);

	foreach ( $overrides as $key => $value ) {
		$labels->$key = $value;
	}

	return $labels;
}
add_filter( 'post_type_labels_post', 'sdc_rename_posts' );

/**
 * Pola publikacji: pliki do pobrania i metadane.
 *
 * Rejestrujemy je jako meta z „show_in_rest", dzięki czemu widać je
 * w panelu bocznym edytora bloków bez dodatkowej wtyczki do pól.
 */
function sdc_register_publication_meta() {
	$fields = array(
		'_sdc_pdf_id'       => array( 'type' => 'integer', 'label' => __( 'Plik PDF', 'stop-dyskryminacji-core' ) ),
		'_sdc_epub_id'      => array( 'type' => 'integer', 'label' => __( 'Plik EPUB', 'stop-dyskryminacji-core' ) ),
		'_sdc_docx_id'      => array( 'type' => 'integer', 'label' => __( 'Wersja edytowalna DOCX', 'stop-dyskryminacji-core' ) ),
		'_sdc_pages'        => array( 'type' => 'integer', 'label' => __( 'Liczba stron', 'stop-dyskryminacji-core' ) ),
		'_sdc_isbn'         => array( 'type' => 'string', 'label' => __( 'ISBN', 'stop-dyskryminacji-core' ) ),
		'_sdc_lang'         => array( 'type' => 'string', 'label' => __( 'Język publikacji', 'stop-dyskryminacji-core' ) ),
		// Kod z nazw plików od Zamawiającego (np. P4) — pliki „P4_….pdf" trafiają tu same.
		'_sdc_file_code'    => array( 'type' => 'string', 'label' => __( 'Kod w nazwach plików', 'stop-dyskryminacji-core' ) ),
	);

	foreach ( $fields as $key => $conf ) {
		register_post_meta(
			'publikacja',
			$key,
			array(
				'type'              => $conf['type'],
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'integer' === $conf['type'] ? 'absint' : 'sanitize_text_field',
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	// Dane teleadresowe instytucji pomocowych.
	foreach ( array( '_sdc_phone', '_sdc_email', '_sdc_www', '_sdc_address', '_sdc_hours', '_sdc_scope' ) as $key ) {
		register_post_meta(
			'instytucja',
			$key,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}
}
add_action( 'init', 'sdc_register_publication_meta' );

/* -------------------------------------------------------------------------
 * Kolor serii publikacji
 * ---------------------------------------------------------------------- */

/**
 * Kolory serii z projektu „logo i layout publikacji". Fiolet z projektu (03)
 * odrzucił Zamawiający — w jego miejsce ochra.
 *
 * @return array Klucz → nazwa w panelu edytora.
 */
function sdc_series_colors() {
	return array(
		'morski'  => __( 'Morski (01 Kodeks)', 'stop-dyskryminacji-core' ),
		'ceglany' => __( 'Ceglany (02 Poradnik)', 'stop-dyskryminacji-core' ),
		'ochra'   => __( 'Ochra', 'stop-dyskryminacji-core' ),
		'zielony' => __( 'Zielony (04 Kompendium)', 'stop-dyskryminacji-core' ),
	);
}

/**
 * Kolor serii publikacji: wybrany w edytorze albo wynikający z typu
 * (Kodeks — morski, Poradnik — ceglany, Kompendium — zielony).
 *
 * @param int $post_id ID publikacji.
 * @return string Klucz koloru.
 */
function sdc_publication_series( $post_id ) {
	$key = (string) get_post_meta( $post_id, '_sdc_series_color', true );
	if ( isset( sdc_series_colors()[ $key ] ) ) {
		return $key;
	}
	$terms = get_the_terms( $post_id, 'typ_publikacji' );
	$slugs = is_array( $terms ) ? wp_list_pluck( $terms, 'slug' ) : array();
	foreach ( array( 'kompendium' => 'zielony', 'poradnik' => 'ceglany', 'kodeks' => 'morski' ) as $needle => $color ) {
		foreach ( $slugs as $slug ) {
			if ( false !== strpos( $slug, $needle ) ) {
				return $color;
			}
		}
	}
	return 'morski';
}

/**
 * Pole koloru serii — tylko wartości z listy, puste = według typu publikacji.
 */
function sdc_register_series_meta() {
	register_post_meta(
		'publikacja',
		'_sdc_series_color',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => function ( $value ) {
				$value = sanitize_key( (string) $value );
				return isset( sdc_series_colors()[ $value ] ) ? $value : '';
			},
			'auth_callback'     => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'init', 'sdc_register_series_meta' );

/**
 * Klasa sd-seria-<kolor> na stronie publikacji (body) i na jej kartach
 * w listach (element listy bloku core/post-template). Kolor w CSS motywu.
 *
 * @param string[] $classes Klasy.
 * @return string[]
 */
function sdc_series_body_class( $classes ) {
	if ( is_singular( 'publikacja' ) ) {
		$classes[] = 'sd-seria-' . sdc_publication_series( get_queried_object_id() );
	}
	return $classes;
}
add_filter( 'body_class', 'sdc_series_body_class' );

/**
 * @param string[] $classes Klasy.
 * @param string[] $class   Klasy dodane przez wywołującego.
 * @param int      $post_id ID wpisu.
 * @return string[]
 */
function sdc_series_post_class( $classes, $class, $post_id ) {
	if ( 'publikacja' === get_post_type( $post_id ) ) {
		$classes[] = 'sd-seria-' . sdc_publication_series( $post_id );
	}
	return $classes;
}
add_filter( 'post_class', 'sdc_series_post_class', 10, 3 );

/**
 * EPUB w bibliotece mediów. WordPress domyślnie odrzuca ten format, a publikacje
 * mają być dostępne w PDF i EPUB (kryterium odbioru: test pobrania obu formatów).
 * Plik jest pobierany, a nie wyświetlany w przeglądarce.
 *
 * @param array $mimes Dozwolone typy plików.
 * @return array
 */
function sdc_allow_epub_upload( $mimes ) {
	$mimes['epub'] = 'application/epub+zip';
	return $mimes;
}
add_filter( 'upload_mimes', 'sdc_allow_epub_upload' );

/**
 * Nadanie uprawnień do zgłoszeń.
 *
 * Zgłoszenia zawierają dane z art. 9 RODO. Dostęp ma wyłącznie administrator
 * i dedykowana rola „Opiekun zgłoszeń" — redaktor treści ich nie widzi.
 */
function sdc_setup_roles() {
	$caps = array(
		'read'                     => true,
		'edit_zgloszenia'          => true,
		'edit_others_zgloszenia'   => true,
		'edit_private_zgloszenia'  => true,
		'read_private_zgloszenia'  => true,
		'delete_zgloszenia'        => true,
		'delete_others_zgloszenia' => true,
		'delete_private_zgloszenia' => true,
	);

	if ( ! get_role( 'sdc_caseworker' ) ) {
		add_role( 'sdc_caseworker', __( 'Opiekun zgłoszeń', 'stop-dyskryminacji-core' ), $caps );
	}

	foreach ( array( 'administrator', 'sdc_caseworker' ) as $role_name ) {
		$role = get_role( $role_name );
		if ( $role ) {
			foreach ( array_keys( $caps ) as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}
}
add_action( 'admin_init', 'sdc_setup_roles' );

/* -------------------------------------------------------------------------
 * KOMENTARZE WYŁĄCZONE
 *
 * Serwis nie ma komentarzy i mieć ich nie powinien. Szablony ich nie pokazują,
 * ale WordPress przyjmowałby je przez wp-comments-post.php i REST API dla
 * każdego wpisu z otwartymi komentarzami — furtka dla spamu i dla zbierania
 * danych osobowych (imię, e-mail, adres IP) bez klauzuli informacyjnej.
 * ---------------------------------------------------------------------- */

add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open', '__return_false', 20 );
add_filter( 'comments_array', '__return_empty_array', 20 );
add_filter( 'feed_links_show_comments_feed', '__return_false' );

/**
 * Usunięcie obsługi komentarzy z typów treści i z panelu.
 */
function sdc_disable_comments_support() {
	foreach ( get_post_types() as $type ) {
		if ( post_type_supports( $type, 'comments' ) ) {
			remove_post_type_support( $type, 'comments' );
		}
		if ( post_type_supports( $type, 'trackbacks' ) ) {
			remove_post_type_support( $type, 'trackbacks' );
		}
	}
}
add_action( 'init', 'sdc_disable_comments_support', 100 );

/**
 * Pozycja „Komentarze" znika z menu i z paska administracyjnego.
 */
function sdc_hide_comments_admin() {
	remove_menu_page( 'edit-comments.php' );
}
add_action( 'admin_menu', 'sdc_hide_comments_admin' );

/**
 * @param WP_Admin_Bar $bar Pasek administracyjny.
 */
function sdc_hide_comments_admin_bar( $bar ) {
	$bar->remove_node( 'comments' );
}
add_action( 'admin_bar_menu', 'sdc_hide_comments_admin_bar', 999 );
