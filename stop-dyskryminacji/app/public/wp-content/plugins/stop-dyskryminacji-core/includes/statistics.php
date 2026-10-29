<?php
/** First-party, consent-based aggregate statistics. No case contents or IP addresses. */
defined( 'ABSPATH' ) || exit;

function sdc_stats_table() { global $wpdb; return $wpdb->prefix . 'sdc_stats_events'; }
function sdc_stats_enabled() { return '0' !== (string) get_option( 'sdc_local_stats_enabled', '1' ); }

function sdc_stats_install() {
	if ( ! current_user_can( 'manage_options' ) || '1' === get_option( 'sdc_stats_db_version' ) ) { return; }
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table = sdc_stats_table(); $collate = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		event_id char(32) NOT NULL,
		occurred_at datetime NOT NULL,
		event_day date NOT NULL,
		visitor_hash char(64) NOT NULL,
		session_hash char(64) NOT NULL,
		page_key varchar(80) NOT NULL,
		target_key varchar(80) NOT NULL DEFAULT '',
		kind varchar(12) NOT NULL,
		source varchar(16) NOT NULL DEFAULT 'direct',
		is_test tinyint(1) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		UNIQUE KEY event_id (event_id),
		KEY event_day (event_day),
		KEY visitor_time (visitor_hash,occurred_at),
		KEY occurred_at (occurred_at)
	) {$collate};" );
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table ) {
		update_option( 'sdc_stats_db_version', '1', false );
		add_option( 'sdc_stats_started_at', gmdate( 'Y-m-d H:i:s' ), '', false );
	}
}
add_action( 'admin_init', 'sdc_stats_install' );

/** Only published, non-sensitive resources can become stored dimensions. */
function sdc_stats_catalog() {
	$cached = get_transient( 'sdc_stats_catalog_v1' );
	if ( is_array( $cached ) ) { return $cached; }
	$out = array( 'items' => array(), 'paths' => array(), 'downloads' => array() );
	foreach ( get_posts( array( 'post_type' => array( 'page', 'post', 'publikacja', 'praktyka', 'instytucja' ), 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
		$url = get_permalink( $id ); $path = wp_parse_url( $url, PHP_URL_PATH );
		if ( (int) sdc_option( 'thankyou_page_id', 0 ) === (int) $id || preg_match( '~/(dziekujemy|thank-you|dyakuyemo)/?$~', $path ) ) { continue; }
		$key = 'p:' . $id;
		$out['items'][ $key ] = array( 'title' => get_the_title( $id ), 'url' => $url );
		$out['paths'][ $path ] = $key;
	}
	foreach ( array( 'home' => array( '/', 'Strona główna' ), 'news' => array( '/aktualnosci/', 'Aktualności' ), 'publications' => array( '/publikacje/', 'Publikacje' ) ) as $key => $item ) {
		$url = home_url( $item[0] ); $key = 'route:' . $key;
		$out['items'][ $key ] = array( 'title' => $item[1], 'url' => $url );
		$out['paths'][ wp_parse_url( $url, PHP_URL_PATH ) ] = $key;
	}
	$total = wp_count_posts( 'post' ); $pages = min( 100, (int) ceil( (int) $total->publish / max( 1, (int) get_option( 'posts_per_page', 10 ) ) ) );
	for ( $n = 2; $n <= $pages; $n++ ) {
		$key = 'route:news:' . $n; $url = home_url( '/aktualnosci/page/' . $n . '/' );
		$out['items'][ $key ] = array( 'title' => 'Aktualności — strona ' . $n, 'url' => $url );
		$out['paths'][ wp_parse_url( $url, PHP_URL_PATH ) ] = $key;
	}
	foreach ( get_posts( array( 'post_type' => 'attachment', 'post_status' => array( 'private', 'inherit', 'publish' ), 'post_mime_type' => array( 'application/pdf', 'application/epub+zip' ), 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_sdc_asset_released', 'meta_value' => '1' ) ) as $id ) {
		if ( ! function_exists( 'sdc_editorial_asset_is_public' ) || ! sdc_editorial_asset_is_public( $id ) ) { continue; }
		$key = 'asset:' . $id; $url = sdc_editorial_asset_url( $id );
		$out['items'][ $key ] = array( 'title' => ( 'application/epub+zip' === get_post_mime_type( $id ) ? 'EPUB: ' : 'PDF: ' ) . get_the_title( wp_get_post_parent_id( $id ) ), 'url' => $url );
		$out['downloads'][ $url ] = $key;
	}
	set_transient( 'sdc_stats_catalog_v1', $out, 300 );
	return $out;
}
function sdc_stats_invalidate_catalog() { delete_transient( 'sdc_stats_catalog_v1' ); }
add_action( 'save_post', 'sdc_stats_invalidate_catalog' );
add_action( 'deleted_post', 'sdc_stats_invalidate_catalog' );

function sdc_stats_context( $key, $test = false ) {
	$data = array( 'page' => $key, 'day' => (int) floor( time() / DAY_IN_SECONDS ), 'test' => $test ? 1 : 0 );
	$encoded = rtrim( strtr( base64_encode( wp_json_encode( $data ) ), '+/', '-_' ), '=' );
	return $encoded . '.' . hash_hmac( 'sha256', 'sdc-stats-v1|' . $encoded, wp_salt( 'nonce' ) );
}

/** Public write endpoint accepts fixed keys, never raw URLs or free text. */
function sdc_stats_validate_event( $data, $origin, $content_type, $logged_in = false ) {
	if ( ! sdc_stats_enabled() || '1' !== get_option( 'sdc_stats_db_version' ) ) { return new WP_Error( 'disabled', 'Pomiar niedostępny.' ); }
	$site = wp_parse_url( home_url( '/' ) ); $actual = wp_parse_url( $origin );
	if ( ! is_array( $actual ) || strtolower( $actual['host'] ?? '' ) !== strtolower( $site['host'] ?? '' ) || ( $actual['scheme'] ?? '' ) !== ( $site['scheme'] ?? '' ) || (int) ( $actual['port'] ?? 0 ) !== (int) ( $site['port'] ?? 0 ) ) { return new WP_Error( 'origin', 'Niedozwolone źródło.' ); }
	if ( 0 !== stripos( $content_type, 'application/json' ) || ! is_array( $data ) || count( $data ) !== 8 || array_diff( array_keys( $data ), array( 'context', 'kind', 'target', 'visitor', 'session', 'event', 'consent', 'source' ) ) ) { return new WP_Error( 'schema', 'Nieprawidłowy pomiar.' ); }
	foreach ( $data as $value ) { if ( ! is_string( $value ) || strlen( $value ) > 600 ) { return new WP_Error( 'schema', 'Nieprawidłowy pomiar.' ); } }
	if ( 'granted' !== $data['consent'] || ! preg_match( '/^[a-f0-9]{64}$/D', $data['visitor'] ) || ! preg_match( '/^[a-f0-9]{32}$/D', $data['session'] ) || ! preg_match( '/^[a-f0-9]{32}$/D', $data['event'] ) ) { return new WP_Error( 'consent', 'Brak zgody lub identyfikatora.' ); }
	$parts = explode( '.', $data['context'] );
	if ( 2 !== count( $parts ) || ! hash_equals( hash_hmac( 'sha256', 'sdc-stats-v1|' . $parts[0], wp_salt( 'nonce' ) ), $parts[1] ) ) { return new WP_Error( 'context', 'Nieprawidłowy kontekst.' ); }
	$context = json_decode( base64_decode( strtr( $parts[0], '-_', '+/' ), true ), true ); $day = (int) floor( time() / DAY_IN_SECONDS );
	if ( ! is_array( $context ) || ! isset( $context['day'], $context['page'], $context['test'] ) || $context['day'] > $day || $context['day'] < $day - 7 ) { return new WP_Error( 'expired', 'Odśwież stronę.' ); }
	if ( $logged_in && ( empty( $context['test'] ) || ! current_user_can( 'manage_options' ) ) ) { return new WP_Error( 'staff', 'Ruch zespołu jest pomijany.' ); }
	$catalog = sdc_stats_catalog(); $page = $context['page'];
	if ( ! is_string( $page ) || ! isset( $catalog['items'][ $page ] ) || 0 !== strpos( $page, 'p:' ) && 0 !== strpos( $page, 'route:' ) ) { return new WP_Error( 'page', 'Niepubliczna strona.' ); }
	$target = $data['target']; $kind = $data['kind'];
	if ( ! in_array( $kind, array( 'view', 'click', 'download', 'social', 'contact' ), true ) || ! in_array( $data['source'], array( 'direct', 'facebook', 'instagram', 'linkedin', 'email', 'referral' ), true ) ) { return new WP_Error( 'kind', 'Nieprawidłowy rodzaj.' ); }
	$valid = 'view' === $kind && '' === $target;
	if ( 'click' === $kind ) { $valid = isset( $catalog['items'][ $target ] ) && ( 0 === strpos( $target, 'p:' ) || 0 === strpos( $target, 'route:' ) ) || in_array( $target, array( 'other:internal', 'other:external' ), true ); }
	if ( 'download' === $kind ) { $valid = isset( $catalog['items'][ $target ] ) && 0 === strpos( $target, 'asset:' ) || 'other:file' === $target; }
	if ( 'social' === $kind ) { $valid = in_array( $target, array( 'social:facebook', 'social:instagram', 'social:linkedin' ), true ); }
	if ( 'contact' === $kind ) { $valid = in_array( $target, array( 'contact:email', 'contact:phone' ), true ); }
	if ( ! $valid ) { return new WP_Error( 'target', 'Nieprawidłowy cel.' ); }
	return array( 'event_id' => $data['event'], 'occurred_at' => gmdate( 'Y-m-d H:i:s' ), 'event_day' => current_time( 'Y-m-d' ), 'visitor_hash' => hash_hmac( 'sha256', 'visitor|' . $data['visitor'], wp_salt( 'auth' ) ), 'session_hash' => hash_hmac( 'sha256', 'session|' . $data['visitor'] . '|' . $data['session'], wp_salt( 'auth' ) ), 'page_key' => $page, 'target_key' => $target, 'kind' => $kind, 'source' => $data['source'], 'is_test' => empty( $context['test'] ) ? 0 : 1 );
}

function sdc_stats_store_event( array $event ) {
	global $wpdb; $table = sdc_stats_table();
	$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE visitor_hash = %s AND occurred_at >= %s", $event['visitor_hash'], gmdate( 'Y-m-d H:i:s', time() - MINUTE_IN_SECONDS ) ) );
	if ( $count >= 120 ) { return new WP_Error( 'rate', 'Zbyt wiele pomiarów.' ); }
	$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE event_id = %s", $event['event_id'] ) );
	if ( $exists ) { return true; }
	return false !== $wpdb->insert( $table, $event, array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' ) );
}
function sdc_stats_receive() {
	nocache_headers(); header( 'X-Robots-Tag: noindex, nofollow', true );
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || (int) ( $_SERVER['CONTENT_LENGTH'] ?? 0 ) > 3000 ) { wp_send_json_error( null, 400 ); }
	$raw = file_get_contents( 'php://input', false, null, 0, 3001 );
	if ( strlen( $raw ) > 3000 ) { wp_send_json_error( null, 400 ); }
	$event = sdc_stats_validate_event( json_decode( $raw, true ), $_SERVER['HTTP_ORIGIN'] ?? '', $_SERVER['CONTENT_TYPE'] ?? '', is_user_logged_in() );
	if ( is_wp_error( $event ) ) { wp_send_json_error( null, 403 ); }
	$ok = sdc_stats_store_event( $event );
	if ( is_wp_error( $ok ) ) { wp_send_json_error( null, 429 ); }
	if ( ! $ok ) { wp_send_json_error( null, 503 ); }
	wp_send_json_success( null, 200 );
}
add_action( 'wp_ajax_nopriv_sdc_stats_track', 'sdc_stats_receive' );
add_action( 'wp_ajax_sdc_stats_track', 'sdc_stats_receive' );

function sdc_stats_enqueue() {
	wp_register_script( 'sdc-local-stats', SDC_URL . 'assets/js/statistics.js', array( 'sdc-analytics' ), SDC_VERSION, true );
	$test = current_user_can( 'manage_options' ) && isset( $_GET['sdc_stats_test'], $_GET['_wpnonce'] ) && '1' === $_GET['sdc_stats_test'] && is_string( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'sdc_stats_test' );
	if ( is_admin() || ( is_user_logged_in() && ! $test ) || is_preview() || is_search() || is_404() || ! sdc_stats_enabled() || '1' !== get_option( 'sdc_stats_db_version' ) ) { return; }
	global $wp;
	$catalog = sdc_stats_catalog(); $path = wp_parse_url( home_url( '/' . trim( $wp->request, '/' ) . ( $wp->request ? '/' : '' ) ), PHP_URL_PATH );
	if ( ! isset( $catalog['paths'][ $path ] ) ) { return; }
	$config = array( 'endpoint' => admin_url( 'admin-ajax.php?action=sdc_stats_track' ), 'context' => sdc_stats_context( $catalog['paths'][ $path ], $test ), 'paths' => $catalog['paths'], 'downloads' => $catalog['downloads'] );
	wp_enqueue_script( 'sdc-local-stats' );
	wp_add_inline_script( 'sdc-local-stats', 'window.sdLocalStatsConfig = ' . wp_json_encode( $config ) . ';', 'before' );
	if ( $test ) { add_action( 'wp_body_open', 'sdc_stats_test_notice', 4 ); }
}
add_action( 'wp_enqueue_scripts', 'sdc_stats_enqueue' );
function sdc_stats_test_notice() { echo '<p role="status" style="padding:16px;border:2px solid #0b5968;margin:16px">Test techniczny statystyk administratora. Dane z tej strony są oznaczone jako testowe i pomijane w wynikach.</p>'; }

function sdc_stats_cleanup() {
	if ( '1' !== get_option( 'sdc_stats_db_version' ) ) { return; }
	global $wpdb; $table = sdc_stats_table();
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE occurred_at < %s", gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS ) ) );
}
add_action( 'sdc_daily_maintenance', 'sdc_stats_cleanup' );

function sdc_stats_range( $query ) {
	$today = current_datetime()->setTime( 0, 0 ); $oldest = $today->modify( '-89 days' );
	$days = isset( $query['days'] ) && is_scalar( $query['days'] ) && in_array( (string) $query['days'], array( '1', '7', '30', '90' ), true ) ? (int) $query['days'] : 30;
	$from = $today->modify( '-' . ( $days - 1 ) . ' days' ); $to = $today;
	foreach ( array( 'from', 'to' ) as $field ) {
		if ( isset( $query[ $field ] ) && is_string( $query[ $field ] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $query[ $field ] ) ) {
			$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $query[ $field ], wp_timezone() );
			if ( $date && $date->format( 'Y-m-d' ) === $query[ $field ] ) { $$field = max( $oldest, min( $today, $date ) ); }
		}
	}
	if ( $from > $to ) { $from = $to; }
	return array( 'from' => $from->format( 'Y-m-d' ), 'to' => $to->format( 'Y-m-d' ) );
}

/** Counts only; never decrypts or selects case contents or reporter attributes. */
function sdc_stats_submissions( array $range ) {
	global $wpdb;
	$sql = "FROM {$wpdb->posts} p WHERE p.post_type = 'zgloszenie' AND p.post_status = 'private' AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} payload WHERE payload.post_id=p.ID AND payload.meta_key='_sdc_payload' AND payload.meta_value <> '') AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} test WHERE test.post_id=p.ID AND test.meta_key='_sdc_test_submission' AND test.meta_value='1')";
	$start = $range['from'] . ' 00:00:00'; $end = ( new DateTimeImmutable( $range['to'], wp_timezone() ) )->modify( '+1 day' )->format( 'Y-m-d' ) . ' 00:00:00';
	$where = $wpdb->prepare( ' AND p.post_date >= %s AND p.post_date < %s', $start, $end );
	return array( 'range' => (int) $wpdb->get_var( 'SELECT COUNT(*) ' . $sql . $where ), 'retained' => (int) $wpdb->get_var( 'SELECT COUNT(*) ' . $sql ), 'daily' => $wpdb->get_results( 'SELECT DATE(p.post_date) AS day, COUNT(*) AS total ' . $sql . $where . ' GROUP BY DATE(p.post_date) ORDER BY day', ARRAY_A ) );
}
function sdc_stats_report( array $range ) {
	global $wpdb; $table = sdc_stats_table();
	$where = $wpdb->prepare( ' WHERE is_test=0 AND event_day >= %s AND event_day <= %s', $range['from'], $range['to'] );
	$metrics = "COUNT(CASE WHEN kind='view' THEN 1 END) AS views, COUNT(DISTINCT CASE WHEN kind='view' THEN visitor_hash END) AS visitors, COUNT(DISTINCT CASE WHEN kind='view' THEN session_hash END) AS sessions, COUNT(CASE WHEN kind<>'view' THEN 1 END) AS clicks, COUNT(CASE WHEN kind='download' THEN 1 END) AS downloads";
	return array( 'totals' => $wpdb->get_row( "SELECT {$metrics} FROM {$table}" . $where, ARRAY_A ), 'pages' => $wpdb->get_results( "SELECT page_key, {$metrics} FROM {$table}" . $where . ' GROUP BY page_key ORDER BY views DESC, clicks DESC LIMIT 100', ARRAY_A ), 'daily' => $wpdb->get_results( "SELECT event_day AS day, {$metrics} FROM {$table}" . $where . ' GROUP BY event_day ORDER BY event_day', ARRAY_A ), 'targets' => $wpdb->get_results( "SELECT target_key, kind, COUNT(*) AS clicks FROM {$table}" . $where . " AND kind<>'view' GROUP BY target_key,kind ORDER BY clicks DESC LIMIT 100", ARRAY_A ), 'sources' => $wpdb->get_results( "SELECT source, COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors FROM {$table}" . $where . " AND kind='view' GROUP BY source ORDER BY views DESC", ARRAY_A ), 'submissions' => sdc_stats_submissions( $range ) );
}

function sdc_stats_menu() {
	add_menu_page( 'Statystyki platformy', 'Statystyki', 'manage_options', 'sdc-statistics', 'sdc_stats_admin_page', 'dashicons-chart-bar', 27 );
}
add_action( 'admin_menu', 'sdc_stats_menu' );
function sdc_stats_admin_css( $hook ) {
	if ( 'toplevel_page_sdc-statistics' === $hook ) { wp_enqueue_style( 'sdc-statistics-admin', SDC_URL . 'assets/css/statistics-admin.css', array(), SDC_VERSION ); }
}
add_action( 'admin_enqueue_scripts', 'sdc_stats_admin_css' );

function sdc_stats_label( $key, $catalog ) {
	$labels = array( 'other:internal' => 'Inne linki wewnętrzne', 'other:external' => 'Inne linki zewnętrzne', 'other:file' => 'Inne pliki do pobrania', 'social:facebook' => 'Facebook Pro Ukraina', 'social:instagram' => 'Instagram Pro Ukraina', 'social:linkedin' => 'LinkedIn Pro Ukraina', 'contact:email' => 'Kontakt e-mail', 'contact:phone' => 'Kontakt telefoniczny' );
	return $catalog['items'][ $key ]['title'] ?? ( $labels[ $key ] ?? 'Materiał wycofany z publikacji' );
}
function sdc_stats_number( $value ) { return number_format_i18n( (int) $value ); }

function sdc_stats_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Brak dostępu.' ); }
	$range = sdc_stats_range( $_GET );
	echo '<div class="wrap sdc-stats"><h1>Statystyki platformy</h1>';
	if ( '1' !== get_option( 'sdc_stats_db_version' ) ) { echo '<div class="notice notice-error"><p>Nie udało się przygotować tabel statystyk. Sprawdź uprawnienia bazy danych.</p></div></div>'; return; }
	$data = sdc_stats_report( $range ); $catalog = sdc_stats_catalog(); $totals = $data['totals'];
	echo '<p>Odwiedziny, podstrony, kliknięcia i liczba zgłoszeń — w jednym miejscu.</p><form method="get" class="sdc-stats-filters"><input type="hidden" name="page" value="sdc-statistics"><label>Od <input type="date" name="from" value="' . esc_attr( $range['from'] ) . '"></label><label>Do <input type="date" name="to" value="' . esc_attr( $range['to'] ) . '"></label><button class="button button-primary">Pokaż</button>';
	foreach ( array( 1 => 'Dzisiaj', 7 => '7 dni', 30 => '30 dni', 90 => '90 dni' ) as $n => $label ) { echo '<a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'sdc-statistics', 'days' => $n ), admin_url( 'admin.php' ) ) ) . '">' . esc_html( $label ) . '</a>'; }
	$export = wp_nonce_url( add_query_arg( array( 'action' => 'sdc_stats_export', 'from' => $range['from'], 'to' => $range['to'] ), admin_url( 'admin-post.php' ) ), 'sdc_stats_export' );
	echo '<a class="button" href="' . esc_url( $export ) . '">Pobierz raport CSV</a></form><div class="sdc-stats-cards">';
	foreach ( array( array( 'Użytkownicy', $totals['visitors'], 'Przeglądarki, które zgodziły się na pomiar.' ), array( 'Wizyty', $totals['sessions'], 'Sesja kończy się po 30 minutach bez aktywności.' ), array( 'Odsłony', $totals['views'], 'Otwarcia publicznych podstron.' ), array( 'Kliknięcia w linki', $totals['clicks'], 'Łącznie, w tym linki do PDF-ów.' ), array( 'Kliknięcia pobrania', $totals['downloads'], 'Kliknięcia; nie potwierdzenie ukończenia pobierania.' ), array( 'Zgłoszenia', $data['submissions']['range'], 'Zapisane w bazie w wybranym okresie; bez oznaczonych testów.' ) ) as $card ) {
		echo '<section class="sdc-stats-card"><h2>' . esc_html( $card[0] ) . '</h2><strong>' . esc_html( sdc_stats_number( $card[1] ) ) . '</strong><p>' . esc_html( $card[2] ) . '</p></section>';
	}
	echo '</div><div class="notice notice-info inline"><p><strong>Jak czytać wyniki:</strong> pomiar ruchu rozpoczęto ' . esc_html( get_date_from_gmt( get_option( 'sdc_stats_started_at' ), 'd.m.Y H:i' ) ) . '. Dane o wcześniejszym ruchu nie są odtwarzane. Liczymy wyłącznie wizyty po zgodzie; blokady pomiaru i odmowy powodują niedoszacowanie. „Użytkownik” oznacza przeglądarkę, nie potwierdzoną osobę. Ruch zalogowanych administratorów i oznaczone testy nie wchodzą do wyników.</p><p>Dane ruchu: ostatnie 90 dni. Zgłoszenia: tylko nadal przechowywane poprawne rekordy; po usunięciu zgodnym z retencją nie są odtwarzane. Łącznie w bazie bez oznaczonych testów: <strong>' . esc_html( sdc_stats_number( $data['submissions']['retained'] ) ) . '</strong>. Strefa czasu: ' . esc_html( wp_timezone_string() ) . '.</p></div>';
	if ( ! sdc_stats_enabled() ) { echo '<div class="notice notice-warning inline"><p>Zbieranie lokalnych statystyk jest wstrzymane.</p></div>'; }
	if ( ! sdc_ga4_measurement_id() ) { echo '<p><strong>GA4:</strong> brak identyfikatora pomiaru. Ten panel działa niezależnie. Nie stanowi dowodu konfiguracji Google Analytics.</p>'; }
	global $wpdb; $table = sdc_stats_table(); $test_result = $wpdb->get_row( "SELECT COUNT(*) AS total, MAX(occurred_at) AS latest FROM {$table} WHERE is_test=1", ARRAY_A );
	echo '<p><a class="button" href="' . esc_url( wp_nonce_url( home_url( '/?sdc_stats_test=1' ), 'sdc_stats_test' ) ) . '">Otwórz test techniczny pomiaru</a> Oznaczone zdarzenia testowe: <strong>' . esc_html( sdc_stats_number( $test_result['total'] ) ) . '</strong>' . ( $test_result['latest'] ? '; ostatnie: ' . esc_html( get_date_from_gmt( $test_result['latest'], 'd.m.Y H:i:s' ) ) : '' ) . '. Testy nie zwiększają wyników powyżej.</p>';
	echo '<h2>Odwiedzane podstrony</h2><div class="sdc-stats-table"><table class="widefat striped"><thead><tr><th scope="col">Podstrona</th><th scope="col">Użytkownicy</th><th scope="col">Wizyty</th><th scope="col">Odsłony</th><th scope="col">Kliknięcia</th><th scope="col">Pobrania</th></tr></thead><tbody>';
	foreach ( $data['pages'] as $row ) {
		$item = $catalog['items'][ $row['page_key'] ] ?? null;
		echo '<tr><th scope="row">' . ( $item ? '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['title'] ) . '</a>' : esc_html( sdc_stats_label( $row['page_key'], $catalog ) ) ) . '</th>';
		foreach ( array( 'visitors', 'sessions', 'views', 'clicks', 'downloads' ) as $key ) { echo '<td>' . esc_html( sdc_stats_number( $row[ $key ] ) ) . '</td>'; } echo '</tr>';
	}
	if ( ! $data['pages'] ) { echo '<tr><td colspan="6">Brak zarejestrowanych odwiedzin po zgodzie w tym okresie.</td></tr>'; }
	echo '</tbody></table></div><p class="description">Maksymalnie 100 podstron. Użytkowników i sesji z różnych podstron nie należy sumować — ta sama przeglądarka może odwiedzić kilka stron.</p>';
	echo '<div class="sdc-stats-columns"><section><h2>Co klikano?</h2><table class="widefat striped"><thead><tr><th scope="col">Cel linku</th><th scope="col">Kliknięcia</th></tr></thead><tbody>';
	foreach ( $data['targets'] as $row ) { echo '<tr><th scope="row">' . esc_html( sdc_stats_label( $row['target_key'], $catalog ) ) . '</th><td>' . esc_html( sdc_stats_number( $row['clicks'] ) ) . '</td></tr>'; }
	if ( ! $data['targets'] ) { echo '<tr><td colspan="2">Brak zarejestrowanych kliknięć.</td></tr>'; }
	echo '</tbody></table></section><section><h2>Skąd przychodzono?</h2><table class="widefat striped"><thead><tr><th scope="col">Źródło</th><th scope="col">Użytkownicy</th><th scope="col">Odsłony</th></tr></thead><tbody>';
	$source_labels = array( 'direct' => 'Bezpośrednio / nieustalone', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'email' => 'Mailing', 'referral' => 'Inne strony' );
	foreach ( $data['sources'] as $row ) { echo '<tr><th scope="row">' . esc_html( $source_labels[ $row['source'] ] ?? 'Inne' ) . '</th><td>' . esc_html( sdc_stats_number( $row['visitors'] ) ) . '</td><td>' . esc_html( sdc_stats_number( $row['views'] ) ) . '</td></tr>'; }
	if ( ! $data['sources'] ) { echo '<tr><td colspan="3">Brak danych o źródłach.</td></tr>'; }
	echo '</tbody></table><p class="description">Źródło według rozpoznanego serwisu odsyłającego lub dozwolonego utm_source; nie mierzy zasięgu postów.</p></section></div>';
	echo '<h2>Dzień po dniu</h2><div class="sdc-stats-table"><table class="widefat striped"><thead><tr><th scope="col">Data</th><th scope="col">Użytkownicy</th><th scope="col">Wizyty</th><th scope="col">Odsłony</th><th scope="col">Kliknięcia</th><th scope="col">Zgłoszenia</th></tr></thead><tbody>';
	$daily = array(); foreach ( $data['daily'] as $row ) { $daily[ $row['day'] ] = $row; } foreach ( $data['submissions']['daily'] as $row ) { $daily[ $row['day'] ]['submissions'] = $row['total']; } krsort( $daily );
	foreach ( $daily as $day => $row ) { echo '<tr><th scope="row">' . esc_html( $day ) . '</th>'; foreach ( array( 'visitors', 'sessions', 'views', 'clicks', 'submissions' ) as $key ) { echo '<td>' . esc_html( sdc_stats_number( $row[ $key ] ?? 0 ) ) . '</td>'; } echo '</tr>'; }
	if ( ! $daily ) { echo '<tr><td colspan="6">Brak danych w wybranym okresie.</td></tr>'; }
	echo '</tbody></table></div><h2>Profile Pro Ukrainy</h2><ul><li><a href="https://www.facebook.com/PROUkraina.org">Facebook — Pro Ukraina</a></li><li><a href="https://www.linkedin.com/company/stowarzyszenie-pro-ukraina/">LinkedIn — Pro Ukraina</a></li><li><a href="https://www.instagram.com/stowarzyszenie_proukraina/">Instagram — Pro Ukraina</a></li></ul><p>Linki do tych profili znajdują się pod artykułami. Ten panel pokazuje ruch w portalu i kliknięcia, a nie prywatne statystyki kont społecznościowych.</p><h2>Prywatność i ustawienia</h2><p>Brak zapisu adresów IP, pełnych adresów z parametrami, treści formularzy, adresów e-mail i numerów telefonu. Losowe identyfikatory przeglądarki oraz sesji są zapisywane dopiero po zgodzie i przechowywane na serwerze w postaci skrótu. Zgłoszenia są liczone oddzielnie, bez łączenia z identyfikatorem odwiedzającego.</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="sdc_stats_toggle">';
	wp_nonce_field( 'sdc_stats_toggle' ); echo '<input type="hidden" name="enabled" value="' . ( sdc_stats_enabled() ? '0' : '1' ) . '"><button class="button">' . ( sdc_stats_enabled() ? 'Wstrzymaj zbieranie statystyk' : 'Włącz zbieranie statystyk' ) . '</button></form></div>';
}

function sdc_stats_export() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Brak dostępu.', '', array( 'response' => 403 ) ); }
	check_admin_referer( 'sdc_stats_export' ); $range = sdc_stats_range( $_GET ); $data = sdc_stats_report( $range ); $catalog = sdc_stats_catalog();
	nocache_headers(); header( 'Content-Type: text/csv; charset=utf-8' ); header( 'Content-Disposition: attachment; filename="statystyki-' . $range['from'] . '-' . $range['to'] . '.csv"' );
	$out = fopen( 'php://output', 'w' ); fwrite( $out, "\xEF\xBB\xBF" );
	$write = static function( $values ) use ( $out ) { fputcsv( $out, array_map( static function( $value ) { $value = (string) $value; return preg_match( '/^[=+@\-\t\r]/', $value ) ? "'" . $value : $value; }, $values ), ';' ); };
	$write( array( 'Raport statystyk po zgodzie; przeglądarki, nie potwierdzone osoby', $range['from'], $range['to'] ) );
	$write( array( 'Wskaźnik', 'Wartość' ) ); foreach ( $data['totals'] as $key => $value ) { $write( array( $key, (int) $value ) ); } $write( array( 'Zgłoszenia bez testów w bazie, wybrany okres', $data['submissions']['range'] ) );
	$write( array( 'Podstrona', 'Adres', 'Użytkownicy', 'Wizyty', 'Odsłony', 'Kliknięcia', 'Pobrania' ) );
	foreach ( $data['pages'] as $row ) { $write( array( sdc_stats_label( $row['page_key'], $catalog ), $catalog['items'][ $row['page_key'] ]['url'] ?? '', $row['visitors'], $row['sessions'], $row['views'], $row['clicks'], $row['downloads'] ) ); }
	$write( array( 'Cel linku', 'Rodzaj', 'Kliknięcia' ) ); foreach ( $data['targets'] as $row ) { $write( array( sdc_stats_label( $row['target_key'], $catalog ), $row['kind'], $row['clicks'] ) ); }
	fclose( $out ); exit;
}
add_action( 'admin_post_sdc_stats_export', 'sdc_stats_export' );
function sdc_stats_toggle() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Brak dostępu.', '', array( 'response' => 403 ) ); }
	check_admin_referer( 'sdc_stats_toggle' ); update_option( 'sdc_local_stats_enabled', isset( $_POST['enabled'] ) && '1' === $_POST['enabled'] ? '1' : '0', false );
	wp_safe_redirect( admin_url( 'admin.php?page=sdc-statistics' ) ); exit;
}
add_action( 'admin_post_sdc_stats_toggle', 'sdc_stats_toggle' );
function sdc_stats_dashboard() {
	if ( current_user_can( 'manage_options' ) ) { wp_add_dashboard_widget( 'sdc_statistics', 'STOP DYSKRYMINACJI — statystyki', 'sdc_stats_widget' ); }
}
add_action( 'wp_dashboard_setup', 'sdc_stats_dashboard' );
function sdc_stats_widget() {
	if ( ! current_user_can( 'manage_options' ) || '1' !== get_option( 'sdc_stats_db_version' ) ) { return; }
	$data = sdc_stats_report( sdc_stats_range( array( 'days' => '30' ) ) );
	echo '<p>Ostatnie 30 dni: <strong>' . esc_html( sdc_stats_number( $data['totals']['visitors'] ) ) . '</strong> użytkowników po zgodzie, <strong>' . esc_html( sdc_stats_number( $data['totals']['views'] ) ) . '</strong> odsłon, <strong>' . esc_html( sdc_stats_number( $data['submissions']['range'] ) ) . '</strong> zgłoszeń bez oznaczonych testów.</p><p><a href="' . esc_url( admin_url( 'admin.php?page=sdc-statistics' ) ) . '">Otwórz pełne statystyki</a></p>';
}

/** Technical disclosure; leaves the supplied legal clause and its wording intact. */
function sdc_stats_policy_notice( $content ) {
	if ( is_page() && '2026-10-26' === get_post_meta( get_queried_object_id(), '_sdc_privacy_version', true ) ) { return $content; }
	if ( is_admin() || doing_filter( 'get_the_excerpt' ) || ! is_page() || ! in_the_loop() || ! is_main_query() || '1' !== get_option( 'sdc_stats_db_version' ) ) { return $content; }
	$source = get_post_meta( get_queried_object_id(), '_sdc_source_path', true );
	if ( ! is_page( array( 'polityka-cookies', 'polityka-prywatnosci' ) ) && ! in_array( $source, array( '/polityka-cookies/', '/polityka-prywatnosci/' ), true ) ) { return $content; }
	$lang = sdc_current_language();
	$copy = array(
		'pl' => array( 'Lokalne statystyki odwiedzin', 'Po zgodzie na statystyki zapisujemy odsłony i kliknięcia publicznych stron oraz losowe identyfikatory przeglądarki i sesji. Identyfikator przeglądarki jest ważny 30 dni; sesja kończy się po 30 minutach bez aktywności. Identyfikatory zapisujemy w pamięci przeglądarki, a na serwerze przechowujemy ich skróty. Dane ruchu usuwamy po 90 dniach. Zmiana przeglądarki lub usunięcie pamięci może spowodować ponowne policzenie użytkownika.', 'Lokalny moduł nie zapisuje adresów IP, pełnych adresów stron z parametrami, treści formularzy, danych kontaktowych ani pełnych adresów stron odsyłających. Nie przesyła tych statystyk do platform społecznościowych. Liczba zgłoszeń jest ustalana osobno na podstawie zapisanych rekordów, bez łączenia z identyfikatorem przeglądarki. Odmowa zgody nie ogranicza korzystania ze strony ani formularza.', 'Zmień zgodę na statystyki' ),
		'en' => array( 'Local website statistics', 'After analytics consent, we record public page views and link clicks using random browser and session identifiers. The browser identifier lasts 30 days; a session ends after 30 minutes of inactivity. Identifiers are stored in browser storage and hashed on this website’s server. Traffic data is deleted after 90 days. Changing browsers or clearing storage may count the same person again.', 'The local module does not store IP addresses, full URLs with query parameters, form contents, contact details or full referring URLs. It does not send these statistics to social platforms. Submission totals are counted separately from saved records, without linking them to browser identifiers. Refusing consent does not restrict the website or help form.', 'Change analytics consent' ),
		'uk' => array( 'Локальна статистика сайту', 'Після згоди на статистику ми записуємо перегляди публічних сторінок і натискання посилань за допомогою випадкових ідентифікаторів браузера та сесії. Ідентифікатор браузера діє 30 днів; сесія завершується після 30 хвилин бездіяльності. Ідентифікатори зберігаються у сховищі браузера, а на сервері сайту — у вигляді хешів. Дані відвідувань видаляються через 90 днів. Зміна браузера або очищення сховища може призвести до повторного підрахунку користувача.', 'Локальний модуль не зберігає IP-адреси, повні URL із параметрами, вміст форм, контактні дані чи повні адреси сторінок переходу. Ця статистика не передається соціальним платформам. Кількість звернень рахується окремо за збереженими записами, без зв’язку з ідентифікаторами браузера. Відмова від згоди не обмежує користування сайтом або формою допомоги.', 'Змінити згоду на статистику' ),
	);
	$text = $copy[ $lang ] ?? $copy['pl'];
	return $content . '<section class="sdc-statistics-notice"><h2>' . esc_html( $text[0] ) . '</h2><p>' . esc_html( $text[1] ) . '</p><p>' . esc_html( $text[2] ) . '</p><p><button type="button" class="sd-btn sd-btn--secondary" data-sd-consent-reset>' . esc_html( $text[3] ) . '</button></p></section>';
}
add_filter( 'the_content', 'sdc_stats_policy_notice', 55 );
