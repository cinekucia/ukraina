<?php
/** Public copy corrections following the accessibility review of 17 October 2026. */
defined( 'ABSPATH' ) || exit;

function sdc_accessibility_copy_data() {
	return json_decode( file_get_contents( SDC_PATH . 'data/accessibility-copy.json' ), true );
}

/** Explicit, repeatable migration: only published public content, never editorial drafts. */
function sdc_apply_accessibility_copy() {
	if ( ! current_user_can( 'manage_options' ) ) { return new WP_Error( 'sdc_forbidden', 'Brak uprawnień.' ); }
	if ( get_option( 'sdc_accessibility_copy_v1' ) ) { return get_option( 'sdc_accessibility_copy_v1' ); }
	$data = sdc_accessibility_copy_data();
	$targets = array();
	foreach ( $data['pages'] as $item ) {
		$page = get_page_by_path( $item['path'] );
		if ( ! $page || 'publish' !== $page->post_status ) { return new WP_Error( 'sdc_missing_page', 'Brak opublikowanej strony: ' . $item['path'] ); }
		$targets[ $page->ID ] = $item['content'];
	}
	$items = get_posts( array( 'post_type' => array( 'page', 'post', 'publikacja', 'wp_template', 'wp_template_part' ), 'post_status' => 'publish', 'numberposts' => -1 ) );
	$changes = array();
	foreach ( $items as $post ) {
		if ( get_post_meta( $post->ID, '_sdc_editorial_code', true ) ) { continue; }
		$content = $targets[ $post->ID ] ?? strtr( $post->post_content, $data['replacements'] );
		$excerpt = strtr( $post->post_excerpt, $data['replacements'] );
		if ( $content !== $post->post_content || $excerpt !== $post->post_excerpt ) {
			$changes[ $post->ID ] = array( 'ID' => $post->ID, 'post_content' => $content, 'post_excerpt' => $excerpt );
		}
	}
	$backup = get_option( 'sdc_accessibility_copy_before_v1', array() );
	foreach ( $changes as $id => $change ) {
		if ( ! isset( $backup[ $id ] ) ) { $backup[ $id ] = array( 'post_content' => get_post_field( 'post_content', $id ), 'post_excerpt' => get_post_field( 'post_excerpt', $id ) ); }
	}
	update_option( 'sdc_accessibility_copy_before_v1', $backup, false );
	$done = array();
	foreach ( $changes as $id => $change ) {
		// Retrying after an interrupted request must preserve subsequent manual edits.
		if ( get_post_meta( $id, '_sdc_accessibility_copy_v1', true ) ) { continue; }
		wp_save_post_revision( $id );
		$result = wp_update_post( wp_slash( $change ), true );
		if ( is_wp_error( $result ) ) { return $result; }
		update_post_meta( $id, '_sdc_accessibility_copy_v1', '1' );
		$done[] = $id;
	}
	update_option( 'sdc_accessibility_copy_v1', array( 'date' => current_time( 'mysql' ), 'changed' => $done ), false );
	return get_option( 'sdc_accessibility_copy_v1' );
}

function sdc_accessibility_admin_page() {
	echo '<div class="wrap"><h1>Poprawki audytu dostępności</h1><p>Korekta informacji o publikacjach, kontaktu i deklaracji dostępności w PL, EN i UA. Kontakt korespondencyjny pochodzi z dokumentów projektu. E-mail, telefon i opis dostępności siedziby wymagają jeszcze potwierdzenia operatora.</p><p>Oryginalne treści zostaną zachowane w kopii w bazie i w wersjach stron. Ta operacja nie publikuje ani nie zmienia szkiców artykułów, publikacji ani ich plików.</p>';
	if ( get_option( 'sdc_accessibility_copy_v1' ) ) { echo '<div class="notice notice-success inline"><p>Poprawki treści z audytu zostały zastosowane.</p></div>'; }
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="sdc_accessibility_copy">';
	wp_nonce_field( 'sdc_accessibility_copy' ); submit_button( 'Zastosuj poprawki treści z audytu' ); echo '</form></div>';
}
add_action( 'admin_menu', function () { add_management_page( 'Poprawki dostępności', 'Poprawki dostępności', 'manage_options', 'sdc-accessibility', 'sdc_accessibility_admin_page' ); } );
add_action( 'admin_post_sdc_accessibility_copy', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Brak uprawnień.' ); }
	check_admin_referer( 'sdc_accessibility_copy' );
	$result = sdc_apply_accessibility_copy();
	if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); }
	wp_safe_redirect( admin_url( 'tools.php?page=sdc-accessibility' ) ); exit;
} );

function sdc_render_practice_status() {
	if ( get_posts( array( 'post_type' => 'praktyka', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids' ) ) ) { return ''; }
	$html = '<p>' . esc_html__( 'Baza dobrych praktyk jest w przygotowaniu. Nie opublikowano jeszcze opisów firm. W tym czasie możesz skorzystać z samooceny firmy i wzorów dokumentów.', 'stop-dyskryminacji-core' ) . '</p>';
	$html .= '<p><a href="' . esc_url( sdc_localized_content_url( home_url( '/samoocena/' ) ) ) . '">' . esc_html__( 'Samoocena firmy', 'stop-dyskryminacji-core' ) . '</a> · <a href="' . esc_url( sdc_localized_content_url( home_url( '/wzory-dokumentow/' ) ) ) . '">' . esc_html__( 'Wzory dokumentów', 'stop-dyskryminacji-core' ) . '</a></p>';
	return $html;
}
add_action( 'init', function () { register_block_type( 'stopdysk/practice-status', array( 'api_version' => 3, 'render_callback' => 'sdc_render_practice_status' ) ); } );
