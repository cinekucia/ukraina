<?php
/** Free first-party SEO. Approved expert text, files and publication dates stay intact. */
defined( 'ABSPATH' ) || exit;

function sdc_seo_public_context() {
	if ( is_admin() || is_feed() || is_search() || is_404() || is_preview() ) { return false; }
	if ( is_singular() ) {
		$id = get_queried_object_id();
		return in_array( get_post_type( $id ), array( 'page', 'post', 'publikacja', 'praktyka', 'instytucja' ), true ) && 'publish' === get_post_status( $id ) && ! post_password_required( $id );
	}
	return is_home() || is_front_page() || is_post_type_archive() || is_category() || is_tag() || is_tax();
}

function sdc_seo_source_path() {
	if ( is_front_page() || sdc_is_section_home() ) { return '/'; }
	if ( is_home() ) { return '/aktualnosci/'; }
	if ( is_post_type_archive( 'publikacja' ) ) { return '/publikacje/'; }
	if ( is_post_type_archive( 'praktyka' ) ) { return '/dobre-praktyki/'; }
	$id = get_queried_object_id();
	$source = $id ? get_post_meta( $id, '_sdc_source_path', true ) : '';
	if ( $source ) { return $source; }
	if ( is_page() ) {
		$path = wp_parse_url( get_permalink( $id ), PHP_URL_PATH );
		$base = rtrim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
		return $base && 0 === strpos( $path, $base . '/' ) ? substr( $path, strlen( $base ) ) : $path;
	}
	return '';
}

function sdc_seo_metadata() {
	$out = array( 'title' => '', 'description' => '' );
	if ( ! sdc_seo_public_context() ) { return $out; }
	static $pages;
	if ( null === $pages ) { $pages = json_decode( file_get_contents( SDC_PATH . 'data/seo-pages.json' ), true ); }
	$id = is_singular() ? get_queried_object_id() : 0;
	$copy = $pages[ sdc_seo_source_path() ][ sdc_current_language() ] ?? array( '', '' );
	$out['title'] = ( $id ? get_post_meta( $id, '_sdc_meta_title', true ) : '' ) ?: $copy[0];
	$out['description'] = ( $id ? get_post_meta( $id, '_sdc_meta_description', true ) : '' ) ?: $copy[1];
	if ( ! $out['description'] && $id ) {
		$post = get_post( $id ); $text = $post->post_excerpt ?: $post->post_content;
		$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( strip_shortcodes( $text ) ) ) );
		$out['description'] = wp_html_excerpt( $text, 155, '…' );
	}
	$page = max( 1, (int) get_query_var( 'paged' ) );
	if ( $page > 1 && $out['title'] ) { $out['title'] .= ' · ' . array( 'pl' => 'strona', 'en' => 'page', 'uk' => 'сторінка' )[ sdc_current_language() ] . ' ' . $page; }
	return $out;
}

add_filter( 'pre_get_document_title', function ( $title ) {
	$meta = sdc_seo_metadata(); return $meta['title'] ?: $title;
}, 30 );
add_filter( 'wpseo_title', function ( $title ) { $meta = sdc_seo_metadata(); return $meta['title'] ?: $title; }, 30 );
add_filter( 'rank_math/frontend/title', function ( $title ) { $meta = sdc_seo_metadata(); return $meta['title'] ?: $title; }, 30 );
add_filter( 'wpseo_metadesc', function ( $description ) { $meta = sdc_seo_metadata(); return $meta['description'] ?: $description; }, 30 );
add_filter( 'rank_math/frontend/description', function ( $description ) { $meta = sdc_seo_metadata(); return $meta['description'] ?: $description; }, 30 );

function sdc_seo_canonical_url() {
	if ( ! sdc_seo_public_context() ) { return ''; }
	$page = max( 1, (int) get_query_var( 'paged' ) );
	if ( $page > 1 ) { return strtok( get_pagenum_link( $page, false ), '?' ); }
	if ( is_front_page() ) { return home_url( '/' ); }
	if ( is_singular() ) { return get_permalink( get_queried_object_id() ); }
	if ( is_home() ) { $id = (int) get_option( 'page_for_posts' ); return $id ? get_permalink( $id ) : home_url( '/' ); }
	if ( is_post_type_archive() ) { return get_post_type_archive_link( get_query_var( 'post_type' ) ); }
	if ( is_category() || is_tag() || is_tax() ) { $url = get_term_link( get_queried_object() ); return is_wp_error( $url ) ? '' : $url; }
	return '';
}
add_filter( 'get_canonical_url', function ( $url, $post ) {
	return (int) $post->ID === get_queried_object_id() && (int) get_query_var( 'paged' ) > 1 ? ( sdc_seo_canonical_url() ?: $url ) : $url;
}, 20, 2 );

add_action( 'wp_head', function () {
	if ( ! sdc_seo_public_context() ) { return; }
	$meta = sdc_seo_metadata();
	$custom = is_singular() ? get_post_meta( get_queried_object_id(), '_sdc_meta_description', true ) : '';
	if ( ! defined( 'WPSEO_VERSION' ) && ! defined( 'RANK_MATH_VERSION' ) ) {
		if ( ! $custom && $meta['description'] ) { echo '<meta name="description" content="' . esc_attr( $meta['description'] ) . '">' . "\n"; }
		if ( ! is_singular() ) { $url = sdc_seo_canonical_url(); if ( $url ) { echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n"; } }
	}
	$token = get_option( 'sdc_google_site_verification', '' );
	if ( preg_match( '/^[A-Za-z0-9_-]{10,256}$/D', $token ) ) { echo '<meta name="google-site-verification" content="' . esc_attr( $token ) . '">' . "\n"; }
}, 8 );

/** Published equivalents only; navigation fallbacks never become SEO alternatives. */
function sdc_seo_equivalent_languages() {
	if ( ! sdc_seo_public_context() || (int) get_query_var( 'paged' ) > 1 || (int) get_query_var( 'page' ) > 1 ) { return array(); }
	// These plugins emit their own equivalence tags. Avoid a second, conflicting set.
	if ( function_exists( 'pll_the_languages' ) || defined( 'ICL_SITEPRESS_VERSION' ) ) { return array(); }
	$out = array(); $source = sdc_seo_source_path(); $id = get_queried_object_id();
	if ( in_array( $source, array( '/', '/publikacje/', '/dobre-praktyki/' ), true ) ) {
		$base = home_url( $source );
		foreach ( sdc_languages() as $lang => $info ) {
			$url = 'pl' === $lang ? $base : sdc_localized_content_url( $base, $lang );
			if ( 'pl' !== $lang && $url === $base ) { continue; }
			$out[ $lang ] = array( 'hreflang' => $info['hreflang'], 'url' => $url );
		}
	} else {
		if ( is_home() ) { $id = (int) get_option( 'page_for_posts' ); }
		if ( ! $id || ! ( is_singular() || is_home() ) ) { return array(); }
		$origin = (int) get_post_meta( $id, '_sdc_translation_of', true ) ?: $id;
		if ( 'publish' !== get_post_status( $origin ) ) { return array(); }
		$expert = (bool) get_post_meta( $origin, '_sdc_editorial_code', true );
		$out['pl'] = array( 'hreflang' => 'pl', 'url' => get_permalink( $origin ) );
		foreach ( array( 'en', 'uk' ) as $lang ) {
			$translation = sdc_translation_id( $origin, $lang );
			// Publication summaries with Polish downloads are not full legal translations.
			if ( ! $translation || ( $expert && '1' !== get_post_meta( $translation, '_sdc_editorial_full_translation', true ) ) ) { continue; }
			$out[ $lang ] = array( 'hreflang' => $lang, 'url' => get_permalink( $translation ) );
		}
	}
	$current = sdc_current_language(); $url = sdc_seo_canonical_url();
	return count( $out ) > 1 && isset( $out[ $current ] ) && untrailingslashit( $out[ $current ]['url'] ) === untrailingslashit( $url ) ? $out : array();
}

/** Public files live behind the permission-checking download handler. */
add_filter( 'robots_txt', function ( $output, $public ) {
	if ( ! $public || preg_match( '~^Disallow:\s*/\s*$~m', $output ) ) { return $output; }
	return rtrim( $output ) . "\nAllow: /wp-admin/admin-post.php?action=sdc_editorial_asset&attachment=\n";
}, 20, 2 );

function sdc_seo_author_page() {
	$id = (int) get_option( 'sdc_seo_author_page' );
	return $id && 'publish' === get_post_status( $id ) && get_post_meta( $id, '_sdc_seo_author_profile', true ) ? $id : 0;
}
function sdc_seo_person() {
	$person = array( '@type' => 'Person', '@id' => home_url( '/#krzysztof-martyniak' ), 'name' => 'Krzysztof Martyniak', 'jobTitle' => 'Ekspert ds. równego traktowania' );
	$id = sdc_seo_author_page(); if ( $id ) { $person['url'] = get_permalink( $id ); }
	return $person;
}

function sdc_seo_schema_graph() {
	if ( ! sdc_seo_public_context() ) { return array(); }
	$lang = sdc_current_language(); $url = sdc_seo_canonical_url(); $meta = sdc_seo_metadata();
	if ( ! $url ) { return array(); }
	$org_id = home_url( '/#organization' ); $website_id = home_url( '/#website' );
	$org = array( '@type' => 'Organization', '@id' => $org_id, 'name' => 'Stowarzyszenie na rzecz Zrównoważonego Rozwoju „Pro Ukraina”', 'url' => 'https://www.proukraina.org/', 'email' => 'info@proukraina.org', 'sameAs' => array( 'https://www.facebook.com/PROUkraina.org', 'https://www.linkedin.com/company/stowarzyszenie-pro-ukraina/', 'https://www.instagram.com/stowarzyszenie_proukraina/' ) );
	$site = array( '@type' => 'WebSite', '@id' => $website_id, 'url' => home_url( '/' ), 'name' => 'STOP DYSKRYMINACJI', 'inLanguage' => array( 'pl', 'en', 'uk' ), 'publisher' => array( '@id' => $org_id ) );
	$page = array( '@type' => 'WebPage', '@id' => $url . '#webpage', 'url' => $url, 'name' => $meta['title'] ?: wp_get_document_title(), 'inLanguage' => $lang, 'isPartOf' => array( '@id' => $website_id ) );
	if ( $meta['description'] ) { $page['description'] = $meta['description']; }
	$graph = array( $org, $site ); $id = get_queried_object_id();
	$home = sdc_section_home_url();
	if ( untrailingslashit( $home ) !== untrailingslashit( $url ) ) {
		$crumbs = array( array( '@type' => 'ListItem', 'position' => 1, 'name' => array( 'pl' => 'Strona główna', 'en' => 'Home', 'uk' => 'Головна' )[ $lang ], 'item' => $home ), array( '@type' => 'ListItem', 'position' => 2, 'name' => is_singular() ? get_the_title( $id ) : $page['name'], 'item' => $url ) );
		$graph[] = array( '@type' => 'BreadcrumbList', '@id' => $url . '#breadcrumb', 'itemListElement' => $crumbs ); $page['breadcrumb'] = array( '@id' => $url . '#breadcrumb' );
	}
	$code = is_singular() ? get_post_meta( $id, '_sdc_editorial_code', true ) : '';
	if ( $code && ! sdc_editorial_pending( $id ) && '1' === get_post_meta( $id, '_sdc_editorial_released', true ) ) {
		$work = array( '@type' => 'post' === get_post_type( $id ) ? 'Article' : 'CreativeWork', '@id' => $url . '#article', 'url' => $url, 'headline' => get_the_title( $id ), 'name' => get_the_title( $id ), 'author' => array( '@id' => home_url( '/#krzysztof-martyniak' ) ), 'publisher' => array( '@id' => $org_id ), 'inLanguage' => 'pl', 'datePublished' => get_post_time( DATE_W3C, true, $id ), 'dateModified' => get_post_modified_time( DATE_W3C, true, $id ), 'mainEntityOfPage' => array( '@id' => $page['@id'] ), 'license' => 'https://creativecommons.org/licenses/by-nc-nd/4.0/' );
		if ( $meta['description'] ) { $work['description'] = $meta['description']; }
		$image_id = get_post_thumbnail_id( $id );
		// The approved infographic is embedded in article content, even when the
		// theme deliberately has no featured image above the article heading.
		if ( ! $image_id || ! sdc_editorial_asset_is_public( $image_id ) ) {
			$asset_key = $code . ( 'post' === get_post_type( $id ) ? '_INFOGRAPHIC' : '_COVER' );
			$found = sdc_editorial_find( 'attachment', '_sdc_editorial_asset_key', $asset_key );
			$image_id = is_wp_error( $found ) ? 0 : (int) $found;
		}
		if ( $image_id && sdc_editorial_asset_is_public( $image_id ) ) { $work['image'] = wp_get_attachment_url( $image_id ); }
		$pdf = (int) get_post_meta( $id, '_sdc_editorial_public_pdf_id', true );
		if ( $pdf && sdc_editorial_asset_is_public( $pdf ) ) { $work['encoding'] = array( '@type' => 'MediaObject', 'encodingFormat' => 'application/pdf', 'contentUrl' => sdc_editorial_asset_url( $pdf ) ); }
		$people = function_exists( 'sdc_article_has_coauthors' ) && sdc_article_has_coauthors( $id ) ? sdc_article_author_people() : array( sdc_seo_person() );
		if ( count( $people ) > 1 ) { $work['author'] = array_map( function ( $person ) { return array( '@id' => $person['@id'] ); }, $people ); }
		$page['mainEntity'] = array( '@id' => $work['@id'] ); foreach ( $people as $person ) { $graph[] = $person; } $graph[] = $work;
	} elseif ( $id && $id === sdc_seo_author_page() ) {
		$page['@type'] = 'ProfilePage'; $page['mainEntity'] = array( '@id' => home_url( '/#krzysztof-martyniak' ) ); $graph[] = sdc_seo_person();
	}
	$graph[] = $page; return $graph;
}
add_action( 'wp_head', function () {
	if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) { return; }
	$graph = sdc_seo_schema_graph();
	if ( $graph ) { echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . '</script>' . "\n"; }
}, 12 );

/** Topic-based recommendations replace the generic latest-three query. */
function sdc_seo_related_codes( $code ) {
	$groups = array( 'A01' => array( 'A11', 'A02', 'A04' ), 'A02' => array( 'A12', 'A08', 'A01' ), 'A03' => array( 'A05', 'A09', 'A01' ), 'A04' => array( 'A01', 'A07', 'A02' ), 'A05' => array( 'A10', 'A03', 'A09' ), 'A06' => array( 'A02', 'A05', 'A09' ), 'A07' => array( 'A04', 'A02', 'A11' ), 'A08' => array( 'A02', 'A09', 'A01' ), 'A09' => array( 'A11', 'A02', 'A12' ), 'A10' => array( 'A05', 'A02', 'A01' ), 'A11' => array( 'A01', 'A09', 'A07' ), 'A12' => array( 'A02', 'A09', 'A01' ) );
	return $groups[ $code ] ?? array();
}
add_filter( 'query_loop_block_query_vars', function ( $query, $block ) {
	if ( ! is_singular( 'post' ) || 11 !== (int) ( $block->context['queryId'] ?? 0 ) ) { return $query; }
	$code = get_post_meta( get_queried_object_id(), '_sdc_editorial_code', true );
	$ids = array(); foreach ( sdc_seo_related_codes( $code ) as $related ) { $id = sdc_editorial_find( 'post', '_sdc_editorial_code', $related ); if ( $id && ! is_wp_error( $id ) && 'publish' === get_post_status( $id ) ) { $ids[] = $id; } }
	if ( $ids ) { $query['post__in'] = $ids; $query['orderby'] = 'post__in'; $query['posts_per_page'] = 3; }
	return $query;
}, 40, 2 );

add_filter( 'render_block_stopdysk/editorial-meta', function ( $html, $block ) {
	$id = get_queried_object_id(); $profile = sdc_seo_author_page();
	if ( ! $profile || ! is_singular() || ! get_post_meta( $id, '_sdc_editorial_code', true ) ) { return $html; }
	$label = function_exists( 'sdc_article_has_coauthors' ) && sdc_article_has_coauthors( $id ) ? 'O Krzysztofie Martyniaku – współautorze artykułu' : 'O autorze i jego materiałach';
	return $html . '<p class="sd-editorial-meta"><a href="' . esc_url( get_permalink( $profile ) ) . '">' . esc_html( $label ) . '</a></p>';
}, 30, 2 );

/** Link older introductory pieces to the full approved expert articles, without redirects. */
add_filter( 'the_content', function ( $content ) {
	if ( ! is_singular( 'post' ) || doing_filter( 'get_the_excerpt' ) || get_queried_object_id() !== get_the_ID() ) { return $content; }
	$map = array( 'czy-to-juz-dyskryminacja' => 'A01', 'jak-dokumentowac-nierowne-traktowanie' => 'A11', 'luka-placowa-co-mowi-prawo' => 'A03', 'mobbing-a-dyskryminacja-roznice' => 'A11', 'rekrutacja-bez-uprzedzen' => 'A02', 'obowiazki-pracodawcy-wobec-osob-z-niepelnosprawnosciami' => 'A08' );
	$code = $map[ get_post_field( 'post_name', get_the_ID() ) ] ?? '';
	if ( ! $code ) { return $content; }
	$id = sdc_editorial_find( 'post', '_sdc_editorial_code', $code );
	if ( ! $id || is_wp_error( $id ) || 'publish' !== get_post_status( $id ) ) { return $content; }
	return $content . '<aside class="sd-callout sd-callout--info sd-callout--text"><p>Przeczytaj pełny materiał ekspercki: <a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a>. W artykule znajdziesz datę stanu prawnego i PDF do pobrania.</p></aside>';
}, 55 );

function sdc_seo_create_author_profile() {
	if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'publish_pages' ) ) { return new WP_Error( 'forbidden', 'Brak uprawnień.' ); }
	$existing = sdc_seo_author_page(); if ( $existing ) { return $existing; }
	$path = 'krzysztof-martyniak';
	if ( get_page_by_path( $path ) ) { return new WP_Error( 'occupied', 'Ten adres jest już zajęty; nie nadpisano istniejącej strony.' ); }
	$content = '<!-- wp:paragraph --><p>Krzysztof Martyniak jest autorem materiałów eksperckich projektu „STOP DYSKRYMINACJI” i ekspertem ds. równego traktowania. Jego materiały dotyczą równego traktowania w zatrudnieniu i są przeznaczone dla pracowników, świadków oraz pracodawców.</p><!-- /wp:paragraph -->' . "\n";
	$content .= '<!-- wp:paragraph --><p>Na stronach poszczególnych materiałów podano autora, stan prawny wskazany w tekście, datę publikacji i warunki licencji. Przy artykułach i publikacjach dostępne są bezpłatne pliki PDF.</p><!-- /wp:paragraph -->' . "\n";
	foreach ( array( 'post' => 'Artykuły autora', 'publikacja' => 'Publikacje autora' ) as $type => $label ) {
		$content .= '<!-- wp:heading --><h2 class="wp-block-heading">' . $label . '</h2><!-- /wp:heading -->' . "\n<!-- wp:list -->\n<ul class=\"wp-block-list\">";
		foreach ( get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'numberposts' => -1, 'meta_key' => '_sdc_editorial_released', 'meta_value' => '1', 'orderby' => 'title', 'order' => 'ASC' ) ) as $post ) {
			if ( 0 !== strpos( get_post_meta( $post->ID, '_sdc_editorial_author', true ), 'Krzysztof Martyniak' ) ) { continue; }
			$content .= '<!-- wp:list-item --><li><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( $post->post_title ) . '</a></li><!-- /wp:list-item -->';
		}
		$content .= '</ul><!-- /wp:list -->' . "\n";
	}
	$content .= '<!-- wp:paragraph --><p><a href="' . esc_url( home_url( '/o-projekcie/' ) ) . '">O projekcie i jego realizatorach</a> · <a href="' . esc_url( home_url( '/potrzebuje-pomocy/' ) ) . '">Potrzebuję pomocy</a></p><!-- /wp:paragraph -->';
	$id = wp_insert_post( wp_slash( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $path, 'post_title' => 'Krzysztof Martyniak – autor materiałów eksperckich', 'post_content' => $content, 'comment_status' => 'closed', 'ping_status' => 'closed', 'meta_input' => array( '_sdc_seo_author_profile' => '1', '_sdc_meta_title' => 'Krzysztof Martyniak – autor | STOP DYSKRYMINACJI', '_sdc_meta_description' => 'Krzysztof Martyniak, ekspert ds. równego traktowania: artykuły i publikacje projektu STOP DYSKRYMINACJI. Pełne teksty i bezpłatne PDF-y.' ) ) ), true );
	if ( ! is_wp_error( $id ) ) { update_option( 'sdc_seo_author_page', $id, false ); update_option( 'sdc_seo_installed_20261024', current_time( 'mysql' ), false ); }
	return $id;
}

add_action( 'admin_menu', function () { add_management_page( 'Widoczność w Google — SEO', 'Widoczność w Google', 'manage_options', 'sdc-seo', 'sdc_seo_admin_page' ); } );
function sdc_seo_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	echo '<div class="wrap"><h1>Widoczność w Google — bezpłatne SEO</h1><p>Ta funkcja nie uruchamia reklam i nie wymaga płatnej wtyczki ani abonamentu.</p><ul><li>Opisy i tytuły głównych stron w PL, EN i UA; własne metadane artykułów mają pierwszeństwo.</li><li>Znaczniki języka prowadzą do odpowiedników treści, bez podstawiania stron głównych zamiast tłumaczeń.</li><li>Dane strukturalne organizacji, artykułów, publikacji i ścieżki nawigacji.</li><li>Powiązania tematyczne artykułów i odnośniki ze starszych wprowadzeń do pełnych tekstów.</li></ul>';
	$id = sdc_seo_author_page();
	if ( $id ) { echo '<div class="notice notice-success inline"><p>Profil autora jest opublikowany: <a href="' . esc_url( get_permalink( $id ) ) . '">Krzysztof Martyniak — materiały eksperckie</a>.</p></div>'; }
	else { echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="sdc_seo_profile">'; wp_nonce_field( 'sdc_seo_profile' ); submit_button( 'Opublikuj profil autora z wykazem zatwierdzonych materiałów' ); echo '</form>'; }
	echo '<h2>Google Search Console</h2><p><a href="https://search.google.com/search-console/">Otwórz bezpłatne Search Console</a>. Po potwierdzeniu właściciela zgłoś mapę: <a href="' . esc_url( home_url( '/wp-sitemap.xml' ) ) . '">wp-sitemap.xml</a>. Nie można potwierdzić połączenia wyłącznie na podstawie obecności tego ekranu.</p><p>Opcjonalna metoda „Tag HTML”: poniżej zapisuje się sam token podany przez Google. Jego umieszczenie potwierdza kontrolę nad stroną kontu korzystającemu z tego tokenu.</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="sdc_seo_verify">'; wp_nonce_field( 'sdc_seo_verify' );
	echo '<label for="sdc-verification">Token weryfikacyjny Google</label><br><input class="regular-text" id="sdc-verification" name="verification" autocomplete="off" value="' . esc_attr( get_option( 'sdc_google_site_verification', '' ) ) . '"><p class="description">Sama wartość content, bez całego znacznika. Pozostaw istniejący token, jeżeli nie zmieniasz weryfikacji.</p>';
	submit_button( 'Zapisz token dla wskazanego konta Google' ); echo '</form><h2>Co sprawdzić dalej</h2><ol><li>Search Console: indeksowanie, zapytania, kliknięcia i pozycje.</li><li>PageSpeed Insights: test mobilny strony głównej i artykułu.</li><li>Linki z witryn partnerów i organizacji współpracujących — uzyskane redakcyjnie, bez kupowania pakietów linków.</li></ol><p>Wprowadzone zmiany nie gwarantują konkretnej pozycji ani terminu pojawienia się w Google.</p></div>';
}
add_action( 'admin_post_sdc_seo_profile', function () {
	check_admin_referer( 'sdc_seo_profile' ); $result = sdc_seo_create_author_profile(); if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); }
	wp_safe_redirect( admin_url( 'tools.php?page=sdc-seo' ) ); exit;
} );
add_action( 'admin_post_sdc_seo_verify', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Brak uprawnień.' ); }
	check_admin_referer( 'sdc_seo_verify' ); $value = isset( $_POST['verification'] ) && is_string( $_POST['verification'] ) ? trim( wp_unslash( $_POST['verification'] ) ) : '';
	if ( ! preg_match( '/^[A-Za-z0-9_-]{10,256}$/D', $value ) ) { wp_die( 'Nieprawidłowy token; pozostawiono dotychczasową weryfikację.' ); }
	update_option( 'sdc_google_site_verification', $value, false ); wp_safe_redirect( admin_url( 'tools.php?page=sdc-seo' ) ); exit;
} );
