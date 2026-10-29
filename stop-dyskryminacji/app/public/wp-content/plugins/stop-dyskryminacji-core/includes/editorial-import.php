<?php
/** Import the reviewed 2.0 package as drafts, with private review attachments. */
defined( 'ABSPATH' ) || exit;

function sdc_editorial_package() {
	static $package = null;
	if ( null === $package ) {
		$file = SDC_PATH . 'data/editorial-v2.php';
		$package = is_readable( $file ) ? require $file : array();
		$print_file = SDC_PATH . 'data/editorial-print.php';
		if ( is_readable( $print_file ) ) { $print = require $print_file; $package['assets'] += $print['assets']; $package['print_publication_date'] = $print['publication_date']; }
		if ( function_exists( 'sdc_editorial_correct_package' ) ) { $package = sdc_editorial_correct_package( $package ); }
		$package = apply_filters( 'sdc_editorial_package', $package );
	}
	return $package;
}

function sdc_editorial_asset_bytes( $key ) {
	if ( ! preg_match( '/^(A(?:0[1-9]|1[0-2])|P[1-4])_(PDF|INFOGRAPHIC|COVER|PRINT|EPUB)$/D', $key ) ) { return false; }
	$file = SDC_PATH . 'data/editorial-v2-assets/' . $key . '.php';
	$file = apply_filters( 'sdc_editorial_asset_file', $file, $key );
	return is_readable( $file ) ? require $file : false;
}

function sdc_editorial_asset_url( $id ) {
	return add_query_arg( array( 'action' => 'sdc_editorial_asset', 'attachment' => (int) $id ), admin_url( 'admin-post.php' ) );
}

/** Preview files stay private; only explicitly released assets of approved published parents are public. */
function sdc_serve_editorial_asset() {
	$id = isset( $_GET['attachment'] ) ? absint( $_GET['attachment'] ) : 0;
	$parent = (int) get_post_field( 'post_parent', $id );
	$key = get_post_meta( $id, '_sdc_editorial_asset_key', true );
	$public = function_exists( 'sdc_editorial_asset_is_public' ) && sdc_editorial_asset_is_public( $id );
	if ( ! $key || ! $parent || ( ! $public && ! current_user_can( 'edit_post', $parent ) ) ) {
		status_header( 404 );
		exit;
	}
	$package = sdc_editorial_package();
	$asset = $package['assets'][ $key ] ?? null;
	$bytes = $asset ? sdc_editorial_asset_bytes( $key ) : false;
	if ( ! is_string( $bytes ) || ! hash_equals( $asset['sha256'], hash( 'sha256', $bytes ) ) ) {
		status_header( 404 ); exit;
	}
	nocache_headers();
	header( $public ? 'Cache-Control: public, max-age=300' : 'Cache-Control: private, no-store, max-age=0' );
	header( 'X-Content-Type-Options: nosniff' );
	header( $public ? 'X-Robots-Tag: index, follow' : 'X-Robots-Tag: noindex, nofollow, noarchive' );
	header( 'Content-Type: ' . $asset['mime'] );
	header( 'Content-Length: ' . strlen( $bytes ) );
	header( 'Content-Disposition: inline; filename="' . sanitize_file_name( $asset['filename'] ) . '"' );
	echo $bytes; // phpcs:ignore WordPress.Security.EscapeOutput -- authenticated binary response, validated by SHA-256.
	exit;
}
add_action( 'admin_post_sdc_editorial_asset', 'sdc_serve_editorial_asset' );
add_action( 'admin_post_nopriv_sdc_editorial_asset', 'sdc_serve_editorial_asset' );

function sdc_editorial_private_attachment_url( $url, $id ) {
	if ( ! get_post_meta( $id, '_sdc_editorial_asset_key', true ) ) { return $url; }
	$parent = (int) get_post_field( 'post_parent', $id );
	return $parent && ( current_user_can( 'edit_post', $parent ) || ( function_exists( 'sdc_editorial_asset_is_public' ) && sdc_editorial_asset_is_public( $id ) ) ) ? sdc_editorial_asset_url( $id ) : '';
}
add_filter( 'wp_get_attachment_url', 'sdc_editorial_private_attachment_url', 20, 2 );

function sdc_editorial_find( $type, $key, $value ) {
	$ids = get_posts( array( 'post_type' => $type, 'post_status' => array( 'draft', 'private', 'pending', 'publish', 'future', 'inherit' ),
		'numberposts' => 2, 'fields' => 'ids', 'meta_key' => $key, 'meta_value' => $value ) );
	if ( count( $ids ) > 1 ) { return new WP_Error( 'sdc_editorial_duplicate', 'Powtórzony identyfikator materiału: ' . $value ); }
	return $ids ? (int) $ids[0] : 0;
}

function sdc_editorial_register_attachment( $key, $parent, $asset, $alt = '' ) {
	$id = sdc_editorial_find( 'attachment', '_sdc_editorial_asset_key', $key );
	if ( is_wp_error( $id ) ) { return $id; }
	if ( $id ) {
		if ( get_post_meta( $id, '_sdc_editorial_asset_sha256', true ) !== $asset['sha256'] ) {
			return new WP_Error( 'sdc_editorial_asset_changed', 'Plik zmienił się po imporcie: ' . $key );
		}
		return $id;
	}
	// The file is deliberately not copied into public uploads. The Media entry has an authenticated URL.
	$id = wp_insert_attachment( wp_slash( array( 'post_title' => pathinfo( $asset['filename'], PATHINFO_FILENAME ),
		'post_status' => 'private', 'post_mime_type' => $asset['mime'], 'post_parent' => $parent,
		'post_content' => '', 'post_excerpt' => '' ) ), false, $parent, true );
	if ( is_wp_error( $id ) ) { return $id; }
	update_post_meta( $id, '_sdc_editorial_asset_key', $key );
	update_post_meta( $id, '_sdc_editorial_asset_sha256', $asset['sha256'] );
	update_post_meta( $id, '_sdc_editorial_bytes', $asset['bytes'] );
	update_post_meta( $id, '_wp_attached_file', 'sdc-editorial-private/' . $asset['filename'] );
	if ( 'image/png' === $asset['mime'] ) {
		update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		wp_update_attachment_metadata( $id, array( 'width' => $asset['width'], 'height' => $asset['height'],
			'file' => 'sdc-editorial-private/' . $asset['filename'], 'sizes' => array(), 'filesize' => $asset['bytes'] ) );
	}
	return (int) $id;
}

function sdc_editorial_import_package() {
	if ( ! current_user_can( 'manage_options' ) ) { return new WP_Error( 'sdc_editorial_forbidden', 'Import wymaga uprawnień administratora.' ); }
	$package = sdc_editorial_package();
	if ( empty( $package['entries'] ) || count( $package['entries'] ) !== 16 || 'draft' !== $package['post_status'] ) {
		return new WP_Error( 'sdc_editorial_missing', 'Brak kompletnej paczki 16 szkiców.' );
	}
	// Verify the complete asset set before creating a single CMS record.
	foreach ( $package['assets'] as $key => $asset ) {
		$bytes = sdc_editorial_asset_bytes( $key );
		if ( ! is_string( $bytes ) || ! hash_equals( $asset['sha256'], hash( 'sha256', $bytes ) ) ) {
			return new WP_Error( 'sdc_editorial_corrupt', 'Brak pliku lub niezgodna suma kontrolna: ' . $key );
		}
		unset( $bytes );
	}
	$lock = (int) get_option( 'sdc_editorial_import_lock' );
	if ( $lock && time() - $lock < 300 ) { return new WP_Error( 'sdc_editorial_busy', 'Import już trwa.' ); }
	if ( $lock ) { delete_option( 'sdc_editorial_import_lock' ); }
	if ( ! add_option( 'sdc_editorial_import_lock', time(), '', false ) ) { return new WP_Error( 'sdc_editorial_busy', 'Import już trwa.' ); }
	try {
		$imported = array();
		foreach ( $package['entries'] as $entry ) {
			$id = sdc_editorial_find( $entry['type'], '_sdc_editorial_code', $entry['code'] );
			if ( is_wp_error( $id ) ) { return $id; }
			if ( $id && get_post_meta( $id, '_sdc_editorial_package_id', true ) === $package['package_id'] && '1' === get_post_meta( $id, '_sdc_editorial_import_complete', true ) ) {
				$imported[ $entry['code'] ] = $id;
				continue; // Repeating the same import preserves all subsequent editorial edits.
			}
			if ( $id && get_post_meta( $id, '_sdc_editorial_package_id', true ) !== $package['package_id'] ) { return new WP_Error( 'sdc_editorial_conflict', 'Istnieje inna wersja materiału ' . $entry['code'] . '; wymaga porównania redakcyjnego.' ); }
			$slug = $entry['seo']['url'] ? basename( $entry['seo']['url'] ) : sanitize_title( $entry['title'] );
			if ( ! $id ) { $id = wp_insert_post( wp_slash( array( 'post_type' => $entry['type'], 'post_status' => 'draft',
				'post_title' => $entry['title'], 'post_excerpt' => $entry['excerpt'], 'post_content' => '',
				'post_name' => $slug, 'post_author' => get_current_user_id(),
				'comment_status' => 'closed', 'ping_status' => 'closed' ) ), true ); }
			if ( is_wp_error( $id ) ) { return $id; }
			$meta = array( '_sdc_editorial_code' => $entry['code'], '_sdc_editorial_package_id' => $package['package_id'],
				'_sdc_editorial_source_file' => $entry['source_file'], '_sdc_editorial_source_sha256' => $entry['source_sha256'],
				'_sdc_editorial_author' => $entry['author'], '_sdc_editorial_legal_as_of' => $entry['legal_as_of'],
				'_sdc_editorial_legal_as_of_iso' => $entry['legal_as_of_iso'], '_sdc_editorial_lead_html' => $entry['lead_html'],
				'_sdc_meta_title' => $entry['seo']['title'], '_sdc_meta_description' => $entry['seo']['description'],
				'_sdc_editorial_keywords' => $entry['seo']['keywords'], '_sdc_editorial_path' => $entry['seo']['url'],
				'_sdc_editorial_planned_month' => $entry['planned_month'], '_sdc_editorial_planned_date' => $entry['planned_date'],
				'_sdc_editorial_deadline' => $entry['publication_deadline'], '_sdc_author_approved' => '0', '_sdc_license_approved' => '0' );
			foreach ( $meta as $key => $value ) { update_post_meta( $id, $key, $value ); }
			$asset_ids = array();
			foreach ( array_filter( array( $entry['pdf_asset'], $entry['infographic_asset'], $entry['cover_asset'] ?? '' ) ) as $key ) {
				$alt = $key === $entry['infographic_asset'] ? $entry['alt'] : 'Okładka publikacji „' . $entry['title'] . '”.';
				$attachment = sdc_editorial_register_attachment( $key, $id, $package['assets'][ $key ], $alt );
				if ( is_wp_error( $attachment ) ) { return $attachment; }
				$asset_ids[ $key ] = $attachment;
			}
			$content = $entry['content'];
			foreach ( $asset_ids as $key => $attachment ) {
				$content = str_replace( '"id":"__SDC_ID_' . $key . '__"', '"id":' . $attachment, $content );
				$content = str_replace( '__SDC_ID_' . $key . '__', (string) $attachment, $content );
				$content = str_replace( '__SDC_ASSET_' . $key . '__', esc_url( sdc_editorial_asset_url( $attachment ) ), $content );
			}
			$saved = wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $content, 'post_status' => 'draft' ) ), true );
			if ( is_wp_error( $saved ) ) { return $saved; }
			update_post_meta( $id, '_sdc_editorial_pdf_id', $asset_ids[ $entry['pdf_asset'] ] );
			if ( 'publikacja' === $entry['type'] ) {
				update_post_meta( $id, '_sdc_file_code', $entry['code'] );
				update_post_meta( $id, '_sdc_pdf_id', $asset_ids[ $entry['pdf_asset'] ] );
				set_post_thumbnail( $id, $asset_ids[ $entry['cover_asset'] ] );
				$kind = 'P1' === $entry['code'] ? 'Kodeks' : ( 'P4' === $entry['code'] ? 'Kompendium' : 'Poradnik' );
				wp_set_object_terms( $id, $kind, 'typ_publikacji' );
				update_post_meta( $id, '_sdc_series_color', array( 'P1' => 'morski', 'P2' => 'ceglany', 'P3' => 'ochra', 'P4' => 'zielony' )[ $entry['code'] ] );
			} else {
				// The infographic already occurs at the exact point in the body; avoid showing it twice.
				$categories = array();
				foreach ( $entry['seo']['categories'] as $name ) {
					$term = term_exists( $name, 'category' );
					if ( ! $term ) { $term = wp_insert_term( $name, 'category' ); }
					if ( is_wp_error( $term ) ) { return $term; }
					$categories[] = (int) $term['term_id'];
				}
				wp_set_post_categories( $id, $categories );
				wp_set_post_tags( $id, $entry['seo']['keywords'] );
			}
			update_post_meta( $id, '_sdc_editorial_import_complete', '1' );
			$imported[ $entry['code'] ] = $id;
		}
		update_option( 'sdc_editorial_v2_import', array( 'package_id' => $package['package_id'], 'posts' => $imported ), false );
		flush_rewrite_rules( false );
		return $imported;
	} finally { delete_option( 'sdc_editorial_import_lock' ); }
}

function sdc_editorial_pending( $id ) {
	return get_post_meta( $id, '_sdc_editorial_code', true ) && ( '1' !== get_post_meta( $id, '_sdc_author_approved', true ) || '1' !== get_post_meta( $id, '_sdc_license_approved', true ) );
}

function sdc_editorial_hold_publication( $data, $postarr ) {
	if ( ! empty( $postarr['ID'] ) && sdc_editorial_pending( $postarr['ID'] ) && in_array( $data['post_status'], array( 'publish', 'future' ), true ) ) {
		$data['post_status'] = 'draft';
	}
	return $data;
}
add_filter( 'wp_insert_post_data', 'sdc_editorial_hold_publication', 20, 2 );

function sdc_editorial_rest_hold( $prepared, $request ) {
	$id = (int) $request->get_param( 'id' );
	if ( $id && sdc_editorial_pending( $id ) && in_array( $prepared->post_status ?? '', array( 'publish', 'future' ), true ) ) {
		return new WP_Error( 'sdc_editorial_pending', 'Treść pozostaje szkicem: oczekujemy akceptacji autora i decyzji lidera o licencji.', array( 'status' => 409 ) );
	}
	return $prepared;
}
add_filter( 'rest_pre_insert_post', 'sdc_editorial_rest_hold', 20, 2 );
add_filter( 'rest_pre_insert_publikacja', 'sdc_editorial_rest_hold', 20, 2 );

function sdc_editorial_rewrite() { add_rewrite_rule( '^artykuly/([^/]+)/?$', 'index.php?post_type=post&name=$matches[1]', 'top' ); }
add_action( 'init', 'sdc_editorial_rewrite' );

function sdc_editorial_post_link( $url, $post ) {
	$path = get_post_meta( $post->ID, '_sdc_editorial_path', true );
	return $path && 'publish' === $post->post_status ? home_url( trailingslashit( $path ) ) : $url;
}
add_filter( 'post_link', 'sdc_editorial_post_link', 20, 2 );

function sdc_editorial_meta_block( $attrs, $content, $block ) {
	$id = $block->context['postId'] ?? get_the_ID();
	$author = get_post_meta( $id, '_sdc_editorial_author', true );
	if ( ! $author ) { return ''; }
	$label = function_exists( 'sdc_article_has_coauthors' ) && sdc_article_has_coauthors( $id ) ? 'Autorzy: ' : 'Autor: ';
	return '<p class="sd-editorial-meta">' . $label . esc_html( $author ) . '<br>Stan prawny: ' . esc_html( get_post_meta( $id, '_sdc_editorial_legal_as_of', true ) ) . '</p>';
}
function sdc_editorial_blocks() {
	register_block_type( 'stopdysk/editorial-meta', array( 'api_version' => 3, 'render_callback' => 'sdc_editorial_meta_block', 'uses_context' => array( 'postId', 'postType' ) ) );
}
add_action( 'init', 'sdc_editorial_blocks' );

function sdc_editorial_full_lead( $html, $parsed, $block ) {
	$id = (int) ( $block->context['postId'] ?? 0 );
	$lead = get_post_meta( $id, '_sdc_editorial_lead_html', true );
	if ( $lead && is_singular() && get_queried_object_id() === $id ) {
		return '<div class="wp-block-post-excerpt sd-lead"><p class="wp-block-post-excerpt__excerpt">' . wp_kses_post( $lead ) . '</p></div>';
	}
	return $html;
}
add_filter( 'render_block_core/post-excerpt', 'sdc_editorial_full_lead', 20, 3 );

function sdc_editorial_seo_title( $title ) {
	$custom = is_singular() ? get_post_meta( get_queried_object_id(), '_sdc_meta_title', true ) : '';
	return $custom ?: $title;
}
add_filter( 'pre_get_document_title', 'sdc_editorial_seo_title', 20 );

function sdc_editorial_seo_description() {
	if ( ! is_singular() ) { return; }
	$id = get_queried_object_id();
	$description = get_post_meta( $id, '_sdc_meta_description', true );
	if ( $description && ! defined( 'WPSEO_VERSION' ) && ! defined( 'RANK_MATH_VERSION' ) ) {
		echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
	}
	$author = get_post_meta( $id, '_sdc_editorial_author', true );
	if ( $author ) { echo '<meta name="author" content="' . esc_attr( $author ) . '">' . "\n"; }
}
add_action( 'wp_head', 'sdc_editorial_seo_description', 7 );
add_filter( 'wpseo_title', 'sdc_editorial_seo_title' );
add_filter( 'rank_math/frontend/title', 'sdc_editorial_seo_title' );
function sdc_editorial_external_description( $description ) {
	return is_singular() ? ( get_post_meta( get_queried_object_id(), '_sdc_meta_description', true ) ?: $description ) : $description;
}
add_filter( 'wpseo_metadesc', 'sdc_editorial_external_description' );
add_filter( 'rank_math/frontend/description', 'sdc_editorial_external_description' );

function sdc_editorial_table_accessibility( $html, $block ) {
	if ( false === strpos( $block['attrs']['className'] ?? '', 'sd-editorial-table' ) ) { return $html; }
	$tags = new WP_HTML_Tag_Processor( $html );
	if ( $tags->next_tag( 'figure' ) ) {
		$tags->set_attribute( 'tabindex', '0' ); $tags->set_attribute( 'role', 'region' );
		$tags->set_attribute( 'aria-label', 'Tabela — na wąskim ekranie przewijana w poziomie' );
	}
	return $tags->get_updated_html();
}
add_filter( 'render_block_core/table', 'sdc_editorial_table_accessibility', 20, 2 );

function sdc_editorial_admin_menu() {
	add_management_page( 'Treści ostateczne 2.0 — szkice', 'Treści ostateczne 2.0', 'manage_options', 'sdc-editorial-v2', 'sdc_editorial_admin_page' );
}
add_action( 'admin_menu', 'sdc_editorial_admin_menu' );

function sdc_editorial_import_action() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Brak uprawnień.' ); }
	check_admin_referer( 'sdc_editorial_import' );
	$result = sdc_editorial_import_package();
	if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); }
	wp_safe_redirect( admin_url( 'tools.php?page=sdc-editorial-v2' ) ); exit;
}
add_action( 'admin_post_sdc_editorial_import', 'sdc_editorial_import_action' );

function sdc_editorial_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	echo '<div class="wrap"><h1>Treści ostateczne 2.0</h1><p>Autor: Krzysztof Martyniak. Stan prawny: 5 listopada 2026 r.</p>';
	$released = get_option( 'sdc_editorial_release_state', array() );
	if ( ! empty( $released['complete'] ) ) { echo '<div class="notice notice-success inline"><p>Opublikowano 12 artykułów i 4 publikacje. Akceptacje zostały potwierdzone przez operatora.</p></div>'; }
	else { echo '<p>Publikacja wymaga potwierdzenia akceptacji autora i licencji. <a href="' . esc_url( admin_url( 'tools.php?page=sdc-editorial-release' ) ) . '">Przejdź do publikacji zatwierdzonych materiałów</a>.</p>'; }
	$state = get_option( 'sdc_editorial_v2_import', array() );
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'sdc_editorial_import' );
	echo '<input type="hidden" name="action" value="sdc_editorial_import">';
	submit_button( empty( $state['posts'] ) ? 'Przygotuj 16 szkiców' : 'Sprawdź import — zachowaj istniejące szkice' );
	echo '</form><table class="widefat striped"><thead><tr><th>Kod</th><th>Tytuł</th><th>Status</th><th>Plan po potwierdzeniu</th><th>Podgląd</th></tr></thead><tbody>';
	foreach ( sdc_editorial_package()['entries'] ?? array() as $entry ) {
		$id = $state['posts'][ $entry['code'] ] ?? 0;
		$title = $id ? '<a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( $entry['title'] ) . '</a>' : esc_html( $entry['title'] );
		$plan = $entry['planned_date'] ?: $entry['planned_month'];
		echo '<tr><td>' . esc_html( $entry['code'] ) . '</td><td>' . $title . '</td><td>' . esc_html( $id ? get_post_status_object( get_post_status( $id ) )->label : 'Nie zaimportowano' ) . '</td><td>' . esc_html( $plan ) . '</td><td>';
		if ( $id ) { echo '<a href="' . esc_url( 'publish' === get_post_status( $id ) ? get_permalink( $id ) : get_preview_post_link( $id ) ) . '">Zobacz materiał</a>'; }
		echo '</td></tr>';
	}
	echo '</tbody></table><p>Kolumna planu zachowuje pierwotny kalendarz redakcyjny. Faktyczny status i daty publikacji znajdują się w zestawieniu opublikowanych materiałów. Oryginalne PDF artykułów pozostają załącznikami redakcyjnymi; publiczne PDF z datą i źródłem są dostępne przy artykułach.</p></div>';
}

function sdc_editorial_meta_boxes() {
	add_meta_box( 'sdc-editorial-state', 'Treść ostateczna 2.0 — akceptacje', 'sdc_editorial_meta_box', array( 'post', 'publikacja' ), 'side' );
}
add_action( 'add_meta_boxes', 'sdc_editorial_meta_boxes' );
function sdc_editorial_meta_box( $post ) {
	$code = get_post_meta( $post->ID, '_sdc_editorial_code', true );
	if ( ! $code ) { echo '<p>Materiał spoza paczki 2.0.</p>'; return; }
	echo '<p><strong>' . esc_html( $code ) . '</strong> · ' . esc_html( get_post_meta( $post->ID, '_sdc_editorial_author', true ) ) . '<br>Stan prawny: 5 listopada 2026 r.</p>';
	echo '<p>Plan: ' . esc_html( get_post_meta( $post->ID, '_sdc_editorial_planned_date', true ) ?: get_post_meta( $post->ID, '_sdc_editorial_planned_month', true ) ) . '. Bez automatycznej publikacji.</p>';
	echo '<p>Akceptacja autora: <strong>' . ( '1' === get_post_meta( $post->ID, '_sdc_author_approved', true ) ? 'potwierdzona' : 'oczekuje' ) . '</strong><br>Decyzja o licencji: <strong>' . ( '1' === get_post_meta( $post->ID, '_sdc_license_approved', true ) ? 'potwierdzona' : 'oczekuje' ) . '</strong></p>';
	$path = get_post_meta( $post->ID, '_sdc_editorial_path', true );
	if ( $path ) { echo '<p>Proponowany adres: <code>' . esc_html( $path ) . '</code></p>'; }
	$title = get_post_meta( $post->ID, '_sdc_meta_title', true );
	$description = get_post_meta( $post->ID, '_sdc_meta_description', true );
	if ( $title ) { echo '<p><strong>SEO — tytuł</strong><br>' . esc_html( $title ) . '</p><p><strong>SEO — opis</strong><br>' . esc_html( $description ) . '</p>'; }
	$pdf = (int) get_post_meta( $post->ID, '_sdc_editorial_pdf_id', true );
	if ( $pdf ) { echo '<p><a href="' . esc_url( sdc_editorial_asset_url( $pdf ) ) . '">PDF źródłowy — podgląd redakcyjny</a></p>'; }
	if ( 'post' === $post->post_type ) { echo '<p>PDF artykułu jest załącznikiem redakcyjnym. Strona prezentuje pełny tekst z MD bez wersji dla mediów i notatek SEO.</p>'; }
}
