<?php
/** Explicit, repeatable setup of separate News and Knowledge base sections. */
defined( 'ABSPATH' ) || exit;

function sdc_knowledge_base_content( $lang ) {
	$copy = array(
		'pl' => array( 'Wybierz temat, poradnik lub narzędzie. Najnowsze artykuły znajdziesz w Aktualnościach.',
			'O dyskryminacji', 'Jak rozpoznać nierówne traktowanie i znaleźć właściwą ścieżkę działania.',
			'Dla pracownika', 'Prawa pracownika, dokumentowanie zdarzeń i dostępne wsparcie.',
			'Dla pracodawcy i HR', 'Zasady równego traktowania i praktyczne narzędzia dla organizacji.',
			'Publikacje', 'Kodeks Równości, poradniki oraz kompendium dobrych praktyk.',
			'Wzory dokumentów', 'Materiały pomocne przy wdrażaniu zasad w firmie.',
			'Dobre praktyki', 'Przykłady rozwiązań wspierających równe traktowanie.',
			'Gdzie szukać pomocy', 'Instytucje i miejsca wsparcia w Wielkopolsce.',
			'Aktualności', 'Najnowsze artykuły i informacje projektu.' ),
		'en' => array( 'Choose a topic, guide or tool. Find the latest articles in News.',
			'About discrimination', 'Recognise unequal treatment and find the right way forward.',
			'For employees', 'Employee rights, documenting incidents and available support.',
			'For employers and HR', 'Equal treatment principles and practical tools for organisations.',
			'Publications', 'The Equality Code, practical guides and the good practice compendium.',
			'Document templates', 'Materials to help implement equal treatment at work.',
			'Good practices', 'Examples of measures that support equal treatment.',
			'Where to get help', 'Support services and institutions in Wielkopolska.',
			'News', 'The latest articles and project updates.' ),
		'uk' => array( 'Оберіть тему, посібник або інструмент. Найновіші статті шукайте в розділі «Новини».',
			'Про дискримінацію', 'Як розпізнати нерівне ставлення та обрати подальші дії.',
			'Для працівника', 'Права працівника, документування подій і доступна підтримка.',
			'Для роботодавця та HR', 'Принципи рівного ставлення та практичні інструменти для організацій.',
			'Публікації', 'Кодекс рівності, посібники та збірник належних практик.',
			'Шаблони документів', 'Матеріали для впровадження рівного ставлення на роботі.',
			'Належні практики', 'Приклади рішень, що сприяють рівному ставленню.',
			'Куди звернутися по допомогу', 'Установи та служби підтримки у Великопольщі.',
			'Новини', 'Найновіші статті та інформація про проєкт.' ),
	)[ $lang ];
	$paths = array( '/czy-to-dyskryminacja/', '/dla-pracownika/', '/dla-pracodawcy/', '/publikacje/', '/wzory-dokumentow/', '/dobre-praktyki/', '/gdzie-szukac-pomocy/', '/aktualnosci/' );
	$html = '<!-- wp:paragraph {"className":"sd-lead"} --><p class="sd-lead">' . esc_html( $copy[0] ) . '</p><!-- /wp:paragraph -->';
	$html .= '<!-- wp:group {"className":"sd-grid sd-grid--2","layout":{"type":"default"}} --><div class="wp-block-group sd-grid sd-grid--2">';
	foreach ( $paths as $i => $path ) {
		$url = sdc_localized_content_url( home_url( $path ), $lang );
		$html .= '<!-- wp:group {"className":"sd-card","layout":{"type":"default"}} --><div class="wp-block-group sd-card">';
		$html .= '<!-- wp:heading --><h2 class="wp-block-heading"><a href="' . esc_url( $url ) . '">' . esc_html( $copy[ 1 + $i * 2 ] ) . '</a></h2><!-- /wp:heading -->';
		$html .= '<!-- wp:paragraph --><p>' . esc_html( $copy[ 2 + $i * 2 ] ) . '</p><!-- /wp:paragraph --></div><!-- /wp:group -->';
	}
	return $html . '</div><!-- /wp:group -->';
}

function sdc_news_navigation_block( $id, $label ) {
	return array( 'blockName' => 'core/navigation-link', 'attrs' => array( 'id' => $id, 'label' => $label, 'type' => 'page', 'kind' => 'post-type', 'url' => get_permalink( $id ) ), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() );
}

/** Repair only the generated News card when upgrading the initial split. */
function sdc_news_refresh_links( $ids ) {
	foreach ( array( 'en' => 'News', 'uk' => 'Новини' ) as $lang => $label ) {
		$id = $ids[ $lang ]['knowledge'];
		$content = get_post_field( 'post_content', $id );
		$old = '<a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( $label ) . '</a>';
		$new = '<a href="' . esc_url( get_permalink( $ids[ $lang ]['news'] ) ) . '">' . esc_html( $label ) . '</a>';
		$fixed = str_replace( $old, $new, $content );
		if ( $fixed !== $content ) {
			wp_save_post_revision( $id );
			$result = wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $fixed ) ), true );
			if ( is_wp_error( $result ) ) { return $result; }
		}
	}
	return $ids;
}

function sdc_setup_news_sections() {
	if ( ! current_user_can( 'manage_options' ) ) { return new WP_Error( 'sdc_forbidden', 'Brak uprawnień.' ); }
	$done = get_option( 'sdc_news_sections_v1' );
	if ( $done ) { return sdc_news_refresh_links( $done ); }
	$posts_id = (int) get_option( 'page_for_posts' );
	if ( ! $posts_id || ! get_post( $posts_id ) ) { return new WP_Error( 'sdc_news_missing', 'Brak strony z wpisami w ustawieniach czytania.' ); }
	$before = get_option( 'sdc_news_before_v1' );
	if ( ! $before ) {
		$before = array( 'posts_id' => $posts_id, 'pages' => array(), 'navigation' => array(), 'translated_archives' => array() );
		$before['pages'][ $posts_id ] = get_post( $posts_id, ARRAY_A );
		foreach ( array( 'pl', 'en', 'uk' ) as $lang ) {
			$nav = sdc_navigation_id( $lang );
			if ( ! $nav ) { return new WP_Error( 'sdc_news_nav_missing', 'Brak menu: ' . $lang ); }
			$before['navigation'][ $lang ] = get_post( $nav, ARRAY_A );
			if ( 'pl' !== $lang ) {
				$archive = sdc_translation_id( $posts_id, $lang );
				if ( ! $archive ) { return new WP_Error( 'sdc_news_translation_missing', 'Najpierw uzupełnij tłumaczenia stron.' ); }
				$before['translated_archives'][ $lang ] = $archive;
				$before['pages'][ $archive ] = get_post( $archive, ARRAY_A );
			}
		}
		add_option( 'sdc_news_before_v1', $before, '', false );
	}
	$definitions = array( 'pl' => array( 'knowledge' => 'baza-wiedzy', 'knowledge_title' => 'Baza wiedzy', 'news_title' => 'Aktualności' ),
		'en' => array( 'news' => 'news', 'knowledge_title' => 'Knowledge base', 'news_title' => 'News' ),
		'uk' => array( 'news' => 'novyny', 'knowledge_title' => 'База знань', 'news_title' => 'Новини' ) );
	// Preflight newly created addresses, before changing any published page.
	foreach ( $definitions as $lang => $def ) {
		$path = 'pl' === $lang ? $def['knowledge'] : sdc_multilingual_prefix( $lang ) . '/' . $def['news'];
		$page = get_page_by_path( $path );
		if ( $page && ! get_post_meta( $page->ID, '_sdc_news_created', true ) ) { return new WP_Error( 'sdc_news_conflict', 'Adres wymaga porównania z istniejącą stroną: ' . $path ); }
	}
	$ids = array();
	foreach ( $definitions as $lang => $def ) {
		$parent = 'pl' === $lang ? 0 : get_page_by_path( sdc_multilingual_prefix( $lang ) )->ID;
		$path = 'pl' === $lang ? $def['knowledge'] : sdc_multilingual_prefix( $lang ) . '/' . $def['news'];
		$existing = get_page_by_path( $path );
		$new = array( 'post_type' => 'page', 'post_status' => 'publish', 'post_parent' => $parent,
			'post_title' => 'pl' === $lang ? $def['knowledge_title'] : $def['news_title'], 'post_name' => basename( $path ),
			'post_content' => 'pl' === $lang ? '' : $before['pages'][ $before['translated_archives'][ $lang ] ]['post_content'], 'comment_status' => 'closed', 'ping_status' => 'closed' );
		if ( $existing ) { $new['ID'] = $existing->ID; }
		$id = wp_insert_post( wp_slash( $new ), true );
		if ( is_wp_error( $id ) ) { return $id; }
		update_post_meta( $id, '_sdc_news_created', '1' );
		$ids[ $lang ] = array( 'news' => 'pl' === $lang ? $posts_id : $id, 'knowledge' => 'pl' === $lang ? $id : $before['translated_archives'][ $lang ] );
	}
	// First establish language links; then build the resource cards using those links.
	foreach ( array( 'en', 'uk' ) as $lang ) {
		foreach ( array( 'news' => $posts_id, 'knowledge' => $ids['pl']['knowledge'] ) as $kind => $origin ) {
			$id = $ids[ $lang ][ $kind ];
			update_post_meta( $id, '_sdc_page_lang', $lang ); update_post_meta( $id, '_sdc_translation_of', $origin );
			update_post_meta( $id, '_sdc_source_path', 'news' === $kind ? '/aktualnosci/' : '/baza-wiedzy/' );
			update_post_meta( $id, '_sdc_source_type', 'page' );
		}
	}
	foreach ( $definitions as $lang => $def ) {
		wp_save_post_revision( $ids[ $lang ]['knowledge'] );
		$saved = wp_update_post( wp_slash( array( 'ID' => $ids[ $lang ]['knowledge'], 'post_title' => $def['knowledge_title'], 'post_content' => sdc_knowledge_base_content( $lang ) ) ), true );
		if ( is_wp_error( $saved ) ) { return $saved; }
		if ( 'pl' === $lang ) {
			wp_save_post_revision( $posts_id );
			$saved = wp_update_post( wp_slash( array( 'ID' => $posts_id, 'post_title' => $def['news_title'] ) ), true );
			if ( is_wp_error( $saved ) ) { return $saved; }
		}
		$nav = sdc_navigation_id( $lang );
		$blocks = parse_blocks( get_post_field( 'post_content', $nav ) );
		$links = array(); $added = false;
		foreach ( $blocks as $block ) {
			if ( ( $block['attrs']['label'] ?? '' ) === $def['knowledge_title'] ) {
				$links[] = sdc_news_navigation_block( $ids[ $lang ]['news'], $def['news_title'] );
				$links[] = sdc_news_navigation_block( $ids[ $lang ]['knowledge'], $def['knowledge_title'] );
				$added = true;
			} else { $links[] = $block; }
		}
		if ( ! $added ) { return new WP_Error( 'sdc_news_nav_changed', 'Nie znaleziono Bazy wiedzy w menu: ' . $lang ); }
		wp_save_post_revision( $nav );
		$saved = wp_update_post( wp_slash( array( 'ID' => $nav, 'post_content' => serialize_blocks( $links ) ) ), true );
		if ( is_wp_error( $saved ) ) { return $saved; }
	}
	update_option( 'sdc_news_sections_v1', $ids, false );
	return $ids;
}

function sdc_news_admin_page() {
	echo '<div class="wrap"><h1>Aktualności i Baza wiedzy</h1><p>Aktualności będą listą opublikowanych wpisów. Baza wiedzy będzie osobną stroną z tematami, publikacjami i narzędziami. Menu PL, EN i UA otrzyma pozycję Aktualności / News / Новини.</p><p>Ta operacja nie publikuje ani nie planuje artykułów. Szkice pozostają niewidoczne dla odwiedzających.</p>';
	if ( get_option( 'sdc_news_sections_v1' ) ) { echo '<div class="notice notice-success inline"><p>Aktualności dodano do trzech menu. Baza wiedzy jest osobną sekcją.</p></div>'; }
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="sdc_news_setup">';
	wp_nonce_field( 'sdc_news_setup' ); submit_button( 'Dodaj Aktualności do menu' ); echo '</form></div>';
}
add_action( 'admin_menu', function () { add_management_page( 'Aktualności i Baza wiedzy', 'Aktualności i Baza wiedzy', 'manage_options', 'sdc-news', 'sdc_news_admin_page' ); } );
add_action( 'admin_post_sdc_news_setup', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Brak uprawnień.' ); }
	check_admin_referer( 'sdc_news_setup' ); $result = sdc_setup_news_sections();
	if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); }
	wp_safe_redirect( admin_url( 'tools.php?page=sdc-news' ) ); exit;
} );
