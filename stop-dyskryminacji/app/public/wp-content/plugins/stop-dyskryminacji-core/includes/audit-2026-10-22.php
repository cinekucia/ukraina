<?php
/** Targeted, revision-backed corrections from the consolidated accessibility audit. */
defined( 'ABSPATH' ) || exit;

function sdc_audit22_data() {
	static $data;
	if ( null === $data ) { $data = json_decode( file_get_contents( SDC_PATH . 'data/audit-2026-10-22.json' ), true ); }
	return $data;
}

function sdc_audit22_callout_blocks( array $blocks, array $definition ) {
	foreach ( $blocks as &$block ) {
		if ( 'core/group' === $block['blockName'] && false !== strpos( $block['attrs']['className'] ?? '', 'sd-callout' ) && 1 === count( $block['innerBlocks'] ) ) {
			$paragraph = $block['innerBlocks'][0];
			foreach ( $definition['titles'] as $title ) {
				$pattern = '~^\s*<p><strong>' . preg_quote( $title, '~' ) . '</strong><br\s*/?>(.*?)</p>\s*$~su';
				if ( 'core/paragraph' !== $paragraph['blockName'] || ! preg_match( $pattern, $paragraph['innerHTML'], $match ) ) { continue; }
				$level = (int) $definition['level'];
				$block['innerBlocks'] = parse_blocks( '<!-- wp:heading {"level":' . $level . '} --><h' . $level . ' class="wp-block-heading">' . $title . '</h' . $level . '><!-- /wp:heading --><!-- wp:paragraph --><p>' . $match[1] . '</p><!-- /wp:paragraph -->' );
				$block['attrs']['className'] .= ' sd-callout--text';
				$opening = $block['innerContent'][0];
				$opening = preg_replace( '/(class="[^"]*sd-callout[^\"]*)"/', '$1 sd-callout--text"', $opening, 1 );
				$closing = end( $block['innerContent'] );
				$block['innerContent'] = array( $opening, null, "\n", null, $closing );
				$block['innerHTML'] = $opening . "\n" . $closing;
				break;
			}
		}
		if ( $block['innerBlocks'] ) { $block['innerBlocks'] = sdc_audit22_callout_blocks( $block['innerBlocks'], $definition ); }
	}
	return $blocks;
}

/** Only exact audited text is replaced; unrelated/manual content stays intact. */
function sdc_audit22_content( $content, $source, $lang = 'pl' ) {
	$data = sdc_audit22_data();
	if ( isset( $data['callouts'][ $source ] ) && false === strpos( $content, 'sd-callout--text' ) ) {
		$content = serialize_blocks( sdc_audit22_callout_blocks( parse_blocks( $content ), $data['callouts'][ $source ] ) );
	}
	$content = strtr( $content, $data['replacements'] );
	if ( '/deklaracja-dostepnosci/' === $source && false === strpos( $content, '22 października 2026' ) && false === strpos( $content, '22 October 2026' ) && false === strpos( $content, '22 жовтня 2026' ) ) {
		// Replace only the known, dated opening; preserve contact and facility facts below it.
		$marker = array( 'pl' => 'Dostępne funkcje', 'en' => 'Accessibility features', 'uk' => 'Засоби доступності' )[ $lang ];
		$pattern = '~\A.*?(?=<!-- wp:heading(?:\s+\{[^}]*\})?\s*-->\s*<h2[^>]*>' . preg_quote( $marker, '~' ) . '</h2>)~su';
		if ( preg_match( $pattern, $content ) ) { $content = preg_replace( $pattern, $data['statements'][ $lang ], $content, 1 ); }
	}
	if ( '/polityka-cookies/' === $source && false === strpos( $content, 'sdc_help_session' ) ) { $content .= "\n" . $data['cookies'][ $lang ]; }
	return $content;
}

function sdc_apply_audit22() {
	if ( ! current_user_can( 'manage_options' ) ) { return new WP_Error( 'sdc_forbidden', 'Brak uprawnień.' ); }
	if ( get_option( 'sdc_audit22_done' ) ) { return get_option( 'sdc_audit22_done' ); }
	$posts = get_posts( array( 'post_type' => array( 'page', 'post', 'publikacja', 'wp_template', 'wp_template_part' ), 'post_status' => 'publish', 'numberposts' => -1 ) );
	$backup = get_option( 'sdc_audit22_before', array() ); $changes = array();
	foreach ( $posts as $post ) {
		if ( 'publish' !== $post->post_status || get_post_meta( $post->ID, '_sdc_editorial_code', true ) || get_post_meta( $post->ID, '_sdc_audit22', true ) ) { continue; }
		$source = get_post_meta( $post->ID, '_sdc_source_path', true );
		if ( ! $source ) { $source = '/' . trim( wp_parse_url( get_permalink( $post->ID ), PHP_URL_PATH ), '/' ) . '/'; }
		$lang = get_post_meta( $post->ID, '_sdc_page_lang', true ) ?: 'pl';
		if ( ! in_array( $lang, array( 'pl', 'en', 'uk' ), true ) ) { continue; }
		$content = sdc_audit22_content( $post->post_content, $source, $lang );
		$excerpt = strtr( $post->post_excerpt, sdc_audit22_data()['replacements'] );
		if ( $content === $post->post_content && $excerpt === $post->post_excerpt ) { continue; }
		$changes[ $post->ID ] = array( 'ID' => $post->ID, 'post_content' => $content, 'post_excerpt' => $excerpt );
		if ( ! isset( $backup[ $post->ID ] ) ) { $backup[ $post->ID ] = array( 'post_content' => $post->post_content, 'post_excerpt' => $post->post_excerpt ); }
	}
	update_option( 'sdc_audit22_before', $backup, false );
	$done = array();
	foreach ( $changes as $id => $change ) {
		wp_save_post_revision( $id ); $result = wp_update_post( wp_slash( $change ), true );
		if ( is_wp_error( $result ) ) { return $result; }
		update_post_meta( $id, '_sdc_audit22', '1' ); $done[] = $id;
	}
	$result = array( 'date' => current_time( 'mysql' ), 'changed' => $done );
	update_option( 'sdc_audit22_done', $result, false ); return $result;
}

function sdc_audit22_admin_page() {
	echo '<div class="wrap"><h1>Audyt scalony — 22 października 2026</h1><p>Korekta nagłówków ramek, nazw linków, wstępu formularza, informacji o dostępności i ciasteczku odzyskiwania formularza w PL, EN i UA. Poprzednie treści zostaną zachowane w kopii w bazie i wersjach stron. Szkice redakcyjne nie są zmieniane.</p>';
	$result = get_option( 'sdc_audit22_done' );
	if ( $result ) { echo '<div class="notice notice-success inline"><p>Poprawki zastosowane. Liczba zmienionych stron i szablonów: ' . count( $result['changed'] ) . '. Data: ' . esc_html( $result['date'] ) . '.</p></div>'; }
	echo '<p>Publiczny e-mail, telefon i dane dostępności siedziby nadal wymagają potwierdzenia operatora. Testy czytników ekranu i dostarczenia poczty wymagają osobnego odbioru.</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="sdc_audit22">';
	wp_nonce_field( 'sdc_audit22' ); submit_button( 'Zastosuj poprawki audytu z 22 października' ); echo '</form></div>';
}
add_action( 'admin_menu', function () { add_management_page( 'Audyt scalony 22.10', 'Audyt scalony 22.10', 'manage_options', 'sdc-audit22', 'sdc_audit22_admin_page' ); } );
add_action( 'admin_post_sdc_audit22', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Brak uprawnień.' ); }
	check_admin_referer( 'sdc_audit22' ); $result = sdc_apply_audit22();
	if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); }
	wp_safe_redirect( admin_url( 'tools.php?page=sdc-audit22' ) ); exit;
} );

add_filter( 'document_title_parts', function ( $parts ) {
	$id = get_queried_object_id(); $lang = sdc_current_language();
	if ( '/' === get_post_meta( $id, '_sdc_source_path', true ) && in_array( $lang, array( 'en', 'uk' ), true ) ) {
		$parts['title'] = 'en' === $lang ? 'Equal treatment at work' : 'Рівне ставлення на роботі';
		$parts['site'] = 'STOP DYSKRYMINACJI'; unset( $parts['tagline'] );
	}
	return $parts;
}, 30 );

/** Navigation landmark and a spoken pause in situation cards, also for saved templates. */
add_filter( 'render_block', function ( $html, $block ) {
	if ( 'core/group' === $block['blockName'] && false !== strpos( $block['attrs']['className'] ?? '', 'sd-footer__cols' ) ) {
		$html = preg_replace( '/\A\s*<div\b/', '<nav aria-label="' . esc_attr__( 'Nawigacja w stopce', 'stop-dyskryminacji-core' ) . '"', $html, 1 );
		$html = preg_replace( '~</div>\s*\z~', '</nav>', $html, 1 );
	}
	if ( false !== strpos( $html, 'sd-choice__hint' ) ) { $html = preg_replace( '~(<span class="sd-choice__hint">)(?!<span aria-hidden)~', '$1<span aria-hidden="true"> — </span><span class="sd-sr-only">: </span>', $html ); }
	return $html;
}, 30, 2 );
