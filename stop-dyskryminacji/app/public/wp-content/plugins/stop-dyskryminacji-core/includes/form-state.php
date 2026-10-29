<?php
/** Encrypted validation recovery, bound to an essential HttpOnly browser cookie. */
defined( 'ABSPATH' ) || exit;

const SDC_FORM_SESSION_COOKIE = 'sdc_help_session';
const SDC_FORM_STATE_TTL = 30 * MINUTE_IN_SECONDS;

function sdc_form_private_headers() {
	if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
	if ( headers_sent() ) { return; }
	nocache_headers();
	header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
	header( 'Referrer-Policy: no-referrer' );
	header( 'Vary: Cookie', false );
}

function sdc_form_state_token( $value ) {
	return is_string( $value ) && preg_match( '/\A[a-f0-9]{64}\z/D', $value ) ? $value : '';
}

function sdc_form_session( $create = false ) {
	$session = sdc_form_state_token( $_COOKIE[ SDC_FORM_SESSION_COOKIE ] ?? '' );
	if ( $session || ! $create ) { return $session; }
	if ( headers_sent() ) { return ''; }
	$session = bin2hex( random_bytes( 32 ) );
	$set = setcookie( SDC_FORM_SESSION_COOKIE, $session, array(
		'expires' => 0, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax',
	) );
	if ( ! $set ) { return ''; }
	$_COOKIE[ SDC_FORM_SESSION_COOKIE ] = $session;
	return $session;
}

function sdc_form_state_owner( $session ) {
	return hash_hmac( 'sha256', $session, wp_salt( 'nonce' ) );
}

function sdc_save_form_state( array $errors, array $old ) {
	sdc_form_private_headers();
	$session = sdc_form_session( true );
	if ( ! $session ) { return new WP_Error( 'sdc_form_cookie', __( 'Nie udało się bezpiecznie zachować formularza. Możesz poprawić dane poniżej.', 'stop-dyskryminacji-core' ) ); }
	unset( $old['zgoda'], $old['sensitive'] );
	$cipher = sdc_encrypt_array( array( 'errors' => $errors, 'old' => $old ) );
	if ( is_wp_error( $cipher ) ) { return $cipher; }
	$token = bin2hex( random_bytes( 32 ) );
	$saved = set_transient( 'sdc_form_v2_' . $token, array(
		'owner' => sdc_form_state_owner( $session ), 'cipher' => $cipher, 'expires' => time() + SDC_FORM_STATE_TTL,
	), SDC_FORM_STATE_TTL );
	return $saved ? $token : new WP_Error( 'sdc_form_state', __( 'Nie udało się bezpiecznie zachować formularza. Możesz poprawić dane poniżej.', 'stop-dyskryminacji-core' ) );
}

function sdc_load_form_state( $token ) {
	$token = sdc_form_state_token( $token );
	$session = sdc_form_session();
	if ( ! $token || ! $session ) { return false; }
	$state = get_transient( 'sdc_form_v2_' . $token );
	if ( ! is_array( $state ) || ! isset( $state['owner'], $state['cipher'], $state['expires'] ) || ! is_int( $state['expires'] ) || ! is_string( $state['cipher'] ) || $state['expires'] <= time() || ! is_string( $state['owner'] ) || ! hash_equals( $state['owner'], sdc_form_state_owner( $session ) ) ) { return false; }
	$decoded = sdc_decrypt_array( $state['cipher'] );
	return ! is_wp_error( $decoded ) && is_array( $decoded ) ? $decoded : false;
}

/** No shared caching of forms, including the nonce and all validation responses. */
function sdc_prepare_help_request() {
	if ( is_singular() && has_block( 'stopdysk/help-form', get_post_field( 'post_content', get_queried_object_id() ) ) ) { sdc_form_private_headers(); }
}
add_action( 'template_redirect', 'sdc_prepare_help_request', 0 );
