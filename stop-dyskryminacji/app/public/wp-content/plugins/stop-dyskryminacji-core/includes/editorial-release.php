<?php
/** Explicit publication after the operator has confirmed author and licence approval. */
defined( 'ABSPATH' ) || exit;

function sdc_editorial_asset_is_public( $id ) {
	$parent = (int) get_post_field( 'post_parent', $id );
	return $parent && 'publish' === get_post_status( $parent ) && ! sdc_editorial_pending( $parent )
		&& '1' === get_post_meta( $parent, '_sdc_editorial_released', true ) && '1' === get_post_meta( $id, '_sdc_asset_released', true );
}

function sdc_release_publication_paths() {
	return array( 'P1' => 'kodeks-rownosci-i-inkluzywnosci', 'P2' => 'poradnik-dla-osob-doswiadczajacych-dyskryminacji', 'P3' => 'poradnik-dla-swiadkow', 'P4' => 'kompendium-dobrych-praktyk' );
}

function sdc_release_backup( $id ) {
	$backup = get_option( 'sdc_editorial_release_before', array() );
	if ( ! isset( $backup[ $id ] ) ) { $backup[ $id ] = array( 'post' => get_post( $id, ARRAY_A ), 'meta' => get_post_custom( $id ) ); update_option( 'sdc_editorial_release_before', $backup, false ); }
	wp_save_post_revision( $id );
}

function sdc_release_error( $code, $message ) { return new WP_Error( $code, $message ); }

function sdc_release_project_copy() {
	if ( get_option( 'sdc_project_release_copy_done' ) ) { return true; }
	$data = json_decode( file_get_contents( SDC_PATH . 'data/project-release-copy.json' ), true ); $targets = array();
	foreach ( $data['pages'] as $item ) {
		$page = get_page_by_path( $item['path'] );
		if ( ! $page || 'publish' !== $page->post_status ) { return sdc_release_error( 'project_page', 'Brak opublikowanej strony ' . $item['path'] ); }
		$targets[ $page->ID ] = $item['content'];
	}
	foreach ( array( 'pl' => 'deklaracja-dostepnosci', 'en' => 'en/accessibility', 'uk' => 'ua/dostupnist' ) as $lang => $path ) {
		$page = get_page_by_path( $path );
		if ( ! $page || 'publish' !== $page->post_status ) { return sdc_release_error( 'statement', 'Brak deklaracji ' . $path ); }
		$content = $page->post_content;
		$content = strtr( $content, array(
			'Aktualizacja informacji o dostępności: 22 października 2026 r.' => 'Aktualizacja informacji o dostępności: 23 października 2026 r.',
			'Accessibility information updated: 22 October 2026.' => 'Accessibility information updated: 23 October 2026.',
			'Інформацію про доступність оновлено 22 жовтня 2026 року.' => 'Інформацію про доступність оновлено 23 жовтня 2026 року.',
			'Dane elektronicznego i telefonicznego kontaktu oraz dostępność siedziby wymagają potwierdzenia operatora.' => 'Potwierdzony kontakt do zespołu podano poniżej. Dostępność architektoniczna siedziby wymaga osobnego potwierdzenia.',
			'Electronic and telephone contact details and physical accessibility information require confirmation by the operator.' => 'Confirmed team contact details are listed below. Physical accessibility information still requires confirmation.',
			'Електронні й телефонні контактні дані та інформацію про архітектурну доступність має підтвердити оператор.' => 'Підтверджені контактні дані команди наведено нижче. Інформація про архітектурну доступність потребує окремого підтвердження.',
		) );
		$targets[ $page->ID ] = $content . "\n" . $data['contacts'][ $lang ];
	}
	foreach ( $targets as $id => $content ) { sdc_release_backup( $id ); $saved = wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $content ) ), true ); if ( is_wp_error( $saved ) ) { return $saved; } }
	update_option( 'sdc_project_release_copy_done', array( 'date' => current_time( 'mysql' ), 'pages' => array_keys( $targets ) ), false ); return true;
}

function sdc_release_editorial_package() {
	if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'publish_posts' ) ) { return sdc_release_error( 'forbidden', 'Brak uprawnień do publikacji.' ); }
	$state = get_option( 'sdc_editorial_release_state', array() );
	if ( ! empty( $state['complete'] ) ) { return $state; }
	$package = sdc_editorial_package();
	if ( count( $package['entries'] ?? array() ) !== 16 || ( $package['print_publication_date'] ?? '' ) !== current_time( 'Y-m-d' ) ) { return sdc_release_error( 'package', 'Brak kompletu materiałów albo aktualnej daty PDF artykułów.' ); }
	$plan = array(); $paths = sdc_release_publication_paths();
	// Preflight every entry and every asset before changing a single status.
	foreach ( $package['entries'] as $entry ) {
		$id = sdc_editorial_find( $entry['type'], '_sdc_editorial_code', $entry['code'] );
		if ( is_wp_error( $id ) || ! $id || get_post_meta( $id, '_sdc_editorial_package_id', true ) !== $package['package_id'] ) { return sdc_release_error( 'entry', 'Brak właściwej wersji materiału ' . $entry['code'] ); }
		$keys = array( $entry['infographic_asset'], 'post' === $entry['type'] ? $entry['code'] . '_PRINT' : $entry['pdf_asset'] );
		if ( isset( $entry['cover_asset'] ) ) { $keys[] = $entry['cover_asset']; }
		foreach ( $keys as $key ) {
			$bytes = sdc_editorial_asset_bytes( $key );
			if ( ! isset( $package['assets'][ $key ] ) || ! is_string( $bytes ) || ! hash_equals( $package['assets'][ $key ]['sha256'], hash( 'sha256', $bytes ) ) ) { return sdc_release_error( 'asset', 'Brak pliku lub niezgodna suma: ' . $key ); }
		}
		$legacy = isset( $paths[ $entry['code'] ] ) ? get_page_by_path( $paths[ $entry['code'] ], OBJECT, 'publikacja' ) : null;
		if ( $legacy && $legacy->ID !== $id && get_post_meta( $legacy->ID, '_sdc_editorial_code', true ) ) { return sdc_release_error( 'path', 'Adres zajmuje inny zatwierdzony materiał.' ); }
		$plan[] = array( 'entry' => $entry, 'id' => $id, 'keys' => $keys, 'legacy' => $legacy && $legacy->ID !== $id ? $legacy : null );
	}
	$lock = (int) get_option( 'sdc_editorial_release_lock' );
	if ( $lock && time() - $lock < 600 ) { return sdc_release_error( 'busy', 'Publikacja już trwa.' ); }
	if ( $lock ) { delete_option( 'sdc_editorial_release_lock' ); }
	if ( ! add_option( 'sdc_editorial_release_lock', time(), '', false ) ) { return sdc_release_error( 'busy', 'Publikacja już trwa.' ); }
	try {
		$copy = sdc_release_project_copy(); if ( is_wp_error( $copy ) ) { return $copy; }
		$done = $state['posts'] ?? array();
		foreach ( $plan as $item ) {
			$id = $item['id']; $entry = $item['entry']; $code = $entry['code'];
			if ( '1' === get_post_meta( $id, '_sdc_editorial_released', true ) && 'publish' === get_post_status( $id ) ) {
				$done[ $code ] = array( 'id' => $id, 'url' => get_permalink( $id ), 'published_at' => get_post_field( 'post_date', $id ), 'pdf' => sdc_editorial_asset_url( (int) get_post_meta( $id, '_sdc_editorial_public_pdf_id', true ) ) ); continue;
			}
			sdc_release_backup( $id );
			$asset_ids = array();
			foreach ( $item['keys'] as $key ) {
				$attachment = sdc_editorial_find( 'attachment', '_sdc_editorial_asset_key', $key );
				if ( ! $attachment ) { $attachment = sdc_editorial_register_attachment( $key, $id, $package['assets'][ $key ] ); }
				if ( is_wp_error( $attachment ) || (int) get_post_field( 'post_parent', $attachment ) !== $id ) { return sdc_release_error( 'attachment', 'Nieprawidłowy załącznik ' . $key ); }
				update_post_meta( $attachment, '_sdc_asset_released', '1' ); update_post_meta( $attachment, '_sdc_asset_lang', 'pl' ); $asset_ids[ $key ] = $attachment;
			}
			$pdf_id = $asset_ids[ 'post' === $entry['type'] ? $code . '_PRINT' : $entry['pdf_asset'] ];
			update_post_meta( $id, '_sdc_editorial_public_pdf_id', $pdf_id );
			if ( $item['legacy'] ) {
				$legacy = $item['legacy']; sdc_release_backup( $legacy->ID );
				$archived = wp_update_post( wp_slash( array( 'ID' => $legacy->ID, 'post_status' => 'private', 'post_name' => $legacy->post_name . '-archiwum-' . $legacy->ID ) ), true );
				if ( is_wp_error( $archived ) ) { return $archived; }
				update_post_meta( $legacy->ID, '_sdc_replaced_by', $id );
				foreach ( get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => -1, 'meta_key' => '_sdc_translation_of', 'meta_value' => $legacy->ID ) ) as $translation ) {
					sdc_release_backup( $translation->ID ); update_post_meta( $translation->ID, '_sdc_translation_of', $id );
					set_post_thumbnail( $translation->ID, $asset_ids[ $entry['cover_asset'] ] );
					update_post_meta( $translation->ID, '_sdc_pdf_id', $pdf_id );
					$lang = get_post_meta( $translation->ID, '_sdc_page_lang', true );
					$intro = 'en' === $lang ? 'The approved Polish-language PDF is available to download below. The complete approved text is available in Polish.' : 'Затверджений PDF польською мовою можна завантажити нижче. Повний затверджений текст доступний польською мовою.';
					$summary = '<!-- wp:paragraph --><p>' . esc_html( $intro ) . ' <a hreflang="pl" lang="pl" href="' . esc_url( home_url( '/publikacje/' . $paths[ $code ] . '/' ) ) . '">Pełny tekst po polsku</a></p><!-- /wp:paragraph -->';
					$saved = wp_update_post( wp_slash( array( 'ID' => $translation->ID, 'post_content' => $summary ) ), true );
					if ( is_wp_error( $saved ) ) { return $saved; }
				}
			}
			update_post_meta( $id, '_sdc_author_approved', '1' ); update_post_meta( $id, '_sdc_license_approved', '1' );
			update_post_meta( $id, '_sdc_approval_provenance', 'Potwierdzenie operatora w rozmowie 23.10.2026; osobne podpisane autoryzacje wymagają archiwizacji.' );
			update_post_meta( $id, '_sdc_approval_recorded_by', get_current_user_id() ); update_post_meta( $id, '_sdc_approval_recorded_at', current_time( 'mysql' ) );
			update_post_meta( $id, '_sdc_editorial_released', '1' );
			$post = array( 'ID' => $id, 'post_status' => 'publish', 'post_date' => current_time( 'mysql' ), 'post_date_gmt' => current_time( 'mysql', true ) );
			if ( isset( $paths[ $code ] ) ) { $post['post_name'] = $paths[ $code ]; }
			$result = wp_update_post( wp_slash( $post ), true );
			if ( is_wp_error( $result ) || 'publish' !== get_post_status( $id ) ) { return sdc_release_error( 'publish', 'Nie udało się opublikować ' . $code ); }
			$done[ $code ] = array( 'id' => $id, 'url' => get_permalink( $id ), 'published_at' => get_post_field( 'post_date', $id ), 'pdf' => sdc_editorial_asset_url( $pdf_id ) );
			update_option( 'sdc_editorial_release_state', array( 'complete' => false, 'posts' => $done ), false );
		}
		flush_rewrite_rules( false );
		$state = array( 'complete' => 16 === count( $done ), 'posts' => $done, 'date' => current_time( 'mysql' ) );
		update_option( 'sdc_editorial_release_state', $state, false ); return $state;
	} finally { delete_option( 'sdc_editorial_release_lock' ); }
}

function sdc_editorial_release_page() {
	echo '<div class="wrap"><h1>Publikacja zatwierdzonych materiałów 2.0</h1><p>12 artykułów i 4 publikacje. Licencja CC BY-NC-ND 4.0. Operacja zapisuje potwierdzenie akceptacji przez operatora i faktyczną datę publikacji; nie tworzy podpisów autora. Cztery starsze opisy zostaną zachowane jako prywatne kopie, a zatwierdzone publikacje przejmą ich stałe adresy.</p>';
	$state = get_option( 'sdc_editorial_release_state', array() );
	if ( ! empty( $state['complete'] ) ) { echo '<div class="notice notice-success inline"><p>Opublikowano komplet 16 materiałów. Data: ' . esc_html( $state['date'] ) . '.</p></div>'; }
	echo '<table class="widefat striped"><thead><tr><th>Kod</th><th>Tytuł</th><th>Status</th><th>Adres publiczny</th><th>PDF</th></tr></thead><tbody>';
	foreach ( sdc_editorial_package()['entries'] ?? array() as $entry ) {
		$id = sdc_editorial_find( $entry['type'], '_sdc_editorial_code', $entry['code'] );
		if ( is_wp_error( $id ) ) { continue; }
		echo '<tr><td>' . esc_html( $entry['code'] ) . '</td><td>' . esc_html( $entry['title'] ) . '</td><td>' . esc_html( $id ? get_post_status( $id ) : 'brak' ) . '</td><td>';
		if ( $id && 'publish' === get_post_status( $id ) ) { echo '<a href="' . esc_url( get_permalink( $id ) ) . '">Zobacz materiał</a>'; }
		echo '</td><td>'; $pdf = $id ? (int) get_post_meta( $id, '_sdc_editorial_public_pdf_id', true ) : 0;
		if ( $pdf ) { echo '<a href="' . esc_url( sdc_editorial_asset_url( $pdf ) ) . '">Pobierz PDF</a>'; } echo '</td></tr>';
	}
	echo '</tbody></table><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="sdc_editorial_release">';
	wp_nonce_field( 'sdc_editorial_release' );
	echo '<p><label><input type="checkbox" name="author_confirmed" value="1" required> Potwierdzam otrzymanie akceptacji autora.</label></p><p><label><input type="checkbox" name="licence_confirmed" value="1" required> Potwierdzam zatwierdzenie licencji CC BY-NC-ND 4.0 i publikacji teraz, z zachowaniem autorskiej daty stanu prawnego 5 listopada 2026 r.</label></p>';
	submit_button( 'Opublikuj 12 artykułów i 4 publikacje' ); echo '</form>';
	echo '<h2>Test modułu pomocy</h2><p>Adres powiadomień: <strong>' . esc_html( implode( ', ', sdc_help27_recipients() ) ) . '</strong>. Wynik transportu nie potwierdza doręczenia do skrzynki.</p><p><a href="' . esc_url( home_url( '/potrzebuje-pomocy/?sdc_test=1' ) ) . '">Otwórz formularz do testu administratora</a></p>';
	$test = get_option( 'sdc_admin_form_test_result' );
	if ( $test ) { echo '<p>Ostatni test: ' . esc_html( $test['date'] ) . ', sygnatura ' . esc_html( $test['signature'] ) . ', rekord #' . (int) $test['id'] . '. Transport potwierdzenia: ' . ( $test['confirmation'] ? 'przyjął' : 'odrzucił' ) . '; transport powiadomienia zespołu: ' . ( $test['notification'] ? 'przyjął' : 'odrzucił' ) . '.</p>'; }
	echo '</div>';
}
add_action( 'admin_menu', function () { add_management_page( 'Publikacja materiałów 2.0', 'Publikacja materiałów 2.0', 'manage_options', 'sdc-editorial-release', 'sdc_editorial_release_page' ); } );
add_action( 'admin_post_sdc_editorial_release', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Brak uprawnień.' ); }
	check_admin_referer( 'sdc_editorial_release' );
	if ( '1' !== ( $_POST['author_confirmed'] ?? '' ) || '1' !== ( $_POST['licence_confirmed'] ?? '' ) ) { wp_die( 'Obie akceptacje są wymagane.' ); }
	$result = sdc_release_editorial_package();
	if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); }
	wp_safe_redirect( admin_url( 'tools.php?page=sdc-editorial-release' ) ); exit;
} );

/** Release context is added outside the closed expert text. */
function sdc_editorial_release_context( $content ) {
	if ( ! is_singular() || get_queried_object_id() !== get_the_ID() ) { return $content; }
	$id = get_the_ID(); $origin = (int) get_post_meta( $id, '_sdc_translation_of', true ); $source = $origin ?: $id;
	if ( '1' !== get_post_meta( $source, '_sdc_editorial_released', true ) ) { return $content; }
	$lang = sdc_current_language(); $author = get_post_meta( $source, '_sdc_editorial_author', true );
	$licence = '<p class="sd-editorial-meta sd-editorial-license">' . esc_html( $author ) . ' · <a rel="license" href="https://creativecommons.org/licenses/by-nc-nd/4.0/">CC BY-NC-ND 4.0</a></p>';
	$date = get_post_meta( $source, '_sdc_editorial_legal_as_of_iso', true );
	if ( ! $origin && $date && $date > current_time( 'Y-m-d' ) ) { $content = '<p class="sd-callout sd-callout--info sd-callout--text">Materiał opublikowano ' . esc_html( get_the_date( 'j F Y', $source ) ) . '. Autor wskazał stan prawny na 5 listopada 2026 r.; tekst obejmuje także zmiany odnoszące się do późniejszego terminu.</p>' . $content; }
	$pdf = (int) get_post_meta( $source, '_sdc_editorial_public_pdf_id', true );
	if ( 'post' === get_post_type( $source ) && $pdf ) { $content .= '<p><a class="sd-btn sd-btn--primary" href="' . esc_url( sdc_editorial_asset_url( $pdf ) ) . '">Pobierz PDF artykułu (PL, ' . esc_html( size_format( (int) get_post_meta( $pdf, '_sdc_editorial_bytes', true ) ) ) . ')</a></p>'; }
	return $content . $licence;
}
add_filter( 'the_content', 'sdc_editorial_release_context', 25 );

add_filter( 'render_block', function ( $html, $block ) {
	$state = get_option( 'sdc_editorial_release_state', array() );
	if ( empty( $state['complete'] ) ) { return $html; }
	if ( 'core/group' === $block['blockName'] && false !== strpos( $block['attrs']['className'] ?? '', 'sd-footer__cols' ) ) {
		$label = array( 'pl' => 'Kontakt do zespołu projektu', 'en' => 'Contact the project team', 'uk' => 'Контакт із командою проєкту' )[ sdc_current_language() ];
		$html .= '<p class="sd-footer__contact">' . esc_html( $label ) . ': Tetiana Wróblewska · <a href="tel:+48889744607">+48 889 744 607</a> · <a href="mailto:tetiana.wroblewska@proukraina.org">tetiana.wroblewska@proukraina.org</a></p>';
	}
	return strtr( $html, array(
		'Pliki do pobrania będą udostępniane po zatwierdzeniu materiałów do publikacji.' => 'Zatwierdzone publikacje są dostępne bezpłatnie w PDF na licencji CC BY-NC-ND 4.0.',
		'Downloadable files will be made available after the materials are approved for publication.' => 'The approved publications are available free of charge as Polish-language PDFs under CC BY-NC-ND 4.0.',
		'Файли для завантаження з’являться після затвердження матеріалів до публікації.' => 'Затверджені публікації доступні безкоштовно у PDF польською мовою за ліцензією CC BY-NC-ND 4.0.',
		'Poznaj tematykę publikacji projektu. Pliki do pobrania pojawią się po zatwierdzeniu materiałów do publikacji.' => 'Poznaj publikacje projektu i pobierz zatwierdzone pliki PDF.',
		'Explore the topics covered by our project publications. Downloadable files will be added after the materials are approved for publication.' => 'Explore our project publications and download the approved Polish-language PDFs.',
		'Ознайомтеся з тематикою публікацій проєкту. Файли для завантаження з’являться після затвердження матеріалів до публікації.' => 'Ознайомтеся з публікаціями проєкту та завантажте затверджені PDF польською мовою.',
		'Opis publikacji; pliki po zatwierdzeniu' => 'PDF i pełny tekst publikacji',
		'Publication description; files after approval' => 'Polish-language PDF and full text',
		'Опис публікації; файли після затвердження' => 'PDF і повний текст польською мовою',
		'Informacje o poradnikach i Kodeksie Równości. Pliki będą dostępne po zatwierdzeniu materiałów.' => 'Poradniki i Kodeks Równości są dostępne w PDF.',
		'Інформація про посібники та Кодекс рівності. Файли будуть доступні після затвердження.' => 'Посібники та Кодекс рівності доступні у PDF польською мовою.',
		'Pliki i warunki ich wykorzystania będą podane po zatwierdzeniu materiałów i licencji.' => 'Zatwierdzone PDF są dostępne na licencji CC BY-NC-ND 4.0.',
		'Файли та умови використання будуть надані після затвердження матеріалів і ліцензії.' => 'Затверджені PDF доступні за ліцензією CC BY-NC-ND 4.0.',
	) );
}, 50, 2 );

/** Record only a deliberately marked synthetic administrator test, never inspect other cases. */
function sdc_capture_admin_test( $id, $confirmation, $notification ) {
	if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['sdc_test_nonce'] ) || ! is_string( $_POST['sdc_test_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['sdc_test_nonce'] ) ), 'sdc_admin_form_test' ) ) { return; }
	if ( 'info@proukraina.org' !== sdc_post( 'sdc_email' ) || 0 !== strpos( sdc_post( 'sdc_opis', 'textarea' ), 'TEST TECHNICZNY PLATFORMY' ) ) { return; }
	update_post_meta( $id, '_sdc_test_submission', '1' );
	update_option( 'sdc_admin_form_test_result', array( 'id' => $id, 'signature' => get_the_title( $id ), 'date' => current_time( 'mysql' ), 'confirmation' => (bool) $confirmation, 'notification' => (bool) $notification ), false );
}
