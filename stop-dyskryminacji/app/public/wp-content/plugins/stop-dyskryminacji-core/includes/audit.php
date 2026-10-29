<?php
/**
 * Rejestr czynności przetwarzania.
 *
 * Wymagania:
 *   - art. 30 ust. 2 RODO — rejestr kategorii czynności przetwarzania;
 *   - § 3 ust. 1 umowy powierzenia — „rejestrowanie dostępu do zgłoszeń
 *     pozwalające ustalić, kto i kiedy je odczytał, z retencją logów
 *     nie krótszą niż 12 miesięcy";
 *   - kryterium odbioru „Moduł zgłoszeniowy — zdefiniowana retencja, rejestr czynności".
 *
 * Rejestr trafia do własnej tabeli, a nie do pliku, z trzech powodów:
 *   1. plik w wp-content bywa wystawiony publicznie przy złej konfiguracji serwera,
 *   2. tabelę obejmuje kopia zapasowa bazy, którą i tak testujemy,
 *   3. po tabeli da się wyszukiwać, co jest potrzebne przy żądaniu z art. 15 RODO.
 *
 * W rejestrze nie zapisujemy treści zgłoszeń — wyłącznie kto, kiedy i co zrobił.
 *
 * @package StopDyskryminacjiCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Utworzenie tabeli rejestru.
 */
function sdc_create_audit_table() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = sdc_audit_table();
	$collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		logged_at DATETIME NOT NULL,
		user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		user_login VARCHAR(60) NOT NULL DEFAULT '',
		action VARCHAR(40) NOT NULL,
		object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		detail VARCHAR(255) NOT NULL DEFAULT '',
		PRIMARY KEY (id),
		KEY logged_at (logged_at),
		KEY object_id (object_id),
		KEY action (action)
	) {$collate};";

	dbDelta( $sql );

	update_option( 'sdc_audit_db_version', '1.0.0' );
}

/**
 * Dopisanie wpisu do rejestru.
 *
 * @param string $action    Nazwa czynności, np. „zgloszenie_odczyt".
 * @param int    $object_id Identyfikator obiektu, którego dotyczy czynność.
 * @param string $detail    Krótki opis — bez danych osobowych i bez treści zgłoszenia.
 * @return bool
 */
function sdc_audit_log( $action, $object_id = 0, $detail = '' ) {
	global $wpdb;

	$user = wp_get_current_user();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- własna tabela rejestru, bez cache z założenia.
	$ok = $wpdb->insert(
		sdc_audit_table(),
		array(
			'logged_at'  => current_time( 'mysql', true ),
			'user_id'    => $user ? (int) $user->ID : 0,
			'user_login' => $user && $user->user_login ? $user->user_login : 'system',
			'action'     => substr( (string) $action, 0, 40 ),
			'object_id'  => (int) $object_id,
			'detail'     => substr( (string) $detail, 0, 255 ),
		),
		array( '%s', '%d', '%s', '%s', '%d', '%s' )
	);

	return (bool) $ok;
}

/**
 * Odczyt rejestru.
 *
 * @param array $args Parametry: per_page, page, action, object_id.
 * @return array
 */
function sdc_audit_query( $args = array() ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'per_page'  => 50,
			'page'      => 1,
			'action'    => '',
			'object_id' => 0,
		)
	);

	$table  = sdc_audit_table();
	$where  = array( '1=1' );
	$params = array();

	if ( $args['action'] ) {
		$where[]  = 'action = %s';
		$params[] = $args['action'];
	}
	if ( $args['object_id'] ) {
		$where[]  = 'object_id = %d';
		$params[] = (int) $args['object_id'];
	}

	$params[] = max( 1, (int) $args['per_page'] );
	$params[] = ( max( 1, (int) $args['page'] ) - 1 ) * max( 1, (int) $args['per_page'] );

	$sql = 'SELECT * FROM ' . $table . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY logged_at DESC LIMIT %d OFFSET %d';

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
}

/**
 * Czyszczenie rejestru po okresie retencji.
 *
 * Umowa powierzenia wymaga retencji nie krótszej niż 12 miesięcy, dlatego
 * dolna granica to 365 dni i nie da się jej obniżyć z panelu.
 */
function sdc_audit_prune() {
	global $wpdb;

	$days  = max( 365, (int) sdc_option( 'audit_retention_days', 400 ) );
	$table = sdc_audit_table();

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	$deleted = $wpdb->query(
		$wpdb->prepare(
			'DELETE FROM ' . $table . ' WHERE logged_at < %s',
			gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS )
		)
	);

	if ( $deleted > 0 ) {
		sdc_audit_log( 'rejestr_czyszczenie', 0, sprintf( 'Usunięto %d wpisów starszych niż %d dni', $deleted, $days ) );
	}
}

/**
 * Codzienna konserwacja: retencja zgłoszeń i czyszczenie rejestru.
 */
function sdc_daily_maintenance() {
	sdc_apply_submission_retention();
	sdc_audit_prune();
}
add_action( 'sdc_daily_maintenance', 'sdc_daily_maintenance' );

/**
 * Logowanie logowania i wylogowania redaktorów — pozwala powiązać
 * odczyt zgłoszenia z konkretną sesją użytkownika.
 *
 * @param string $login Login użytkownika.
 */
function sdc_audit_login( $login ) {
	$user = get_user_by( 'login', $login );
	sdc_audit_log( 'logowanie', $user ? $user->ID : 0, 'Zalogowanie do panelu' );
}
add_action( 'wp_login', 'sdc_audit_login' );

/* -------------------------------------------------------------------------
 * PRZEGLĄD CAŁEGO REJESTRU (tylko administrator)
 *
 * Przy pojedynczym zgłoszeniu widać jego historię. Przy kontroli Administratora
 * (umowa powierzenia § 6), przy naruszeniu ochrony danych (§ 5) i przy żądaniu
 * z art. 15 RODO potrzebny jest cały rejestr — z filtrem i eksportem.
 * ---------------------------------------------------------------------- */

/**
 * Czytelne nazwy czynności. Panel czyta polski administrator, więc bez tłumaczeń.
 *
 * @return array
 */
function sdc_audit_action_labels() {
	return array(
		'zgloszenie_utworzone'   => 'Przyjęcie zgłoszenia',
		'zgloszenie_odczyt'      => 'Odczyt treści zgłoszenia',
		'zgloszenie_odczyt_blad' => 'Nieudany odczyt (błąd odszyfrowania)',
		'zgloszenie_eksport'     => 'Eksport zgłoszenia',
		'zgloszenie_usuniete'    => 'Trwałe usunięcie zgłoszenia',
		'zgloszenie_retencja'    => 'Usunięcie po okresie retencji',
		'zgloszenie_wyszukanie'  => 'Wyszukanie po adresie e-mail',
		'zgloszenie_status'      => 'Zmiana statusu',
		'zgloszenie_odrzucone'   => 'Odrzucenie przy wysyłce',
		'zgloszenie_spam'        => 'Zablokowany robot',
		'zgloszenie_blad'        => 'Błąd zapisu',
		'logowanie'              => 'Zalogowanie do panelu',
		'rejestr_czyszczenie'    => 'Czyszczenie rejestru po okresie retencji',
		'rejestr_eksport'        => 'Eksport rejestru',
		'oznakowanie_brak'       => 'Brak zestawu znaków w szablonie',
	);
}

/**
 * Pozycja „Rejestr czynności" w menu zgłoszeń.
 */
function sdc_audit_admin_menu() {
	add_submenu_page(
		'edit.php?post_type=zgloszenie',
		__( 'Rejestr czynności', 'stop-dyskryminacji-core' ),
		__( 'Rejestr czynności', 'stop-dyskryminacji-core' ),
		'manage_options',
		'sdc-rejestr',
		'sdc_render_audit_page'
	);
}
add_action( 'admin_menu', 'sdc_audit_admin_menu' );

/**
 * Filtry z adresu strony.
 *
 * @return array
 */
function sdc_audit_filters() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- tylko odczyt i filtrowanie.
	$action = isset( $_GET['czynnosc'] ) ? sanitize_key( wp_unslash( $_GET['czynnosc'] ) ) : '';
	$object = isset( $_GET['zgloszenie'] ) ? absint( $_GET['zgloszenie'] ) : 0;
	$page   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	// phpcs:enable

	return array(
		'action'    => array_key_exists( $action, sdc_audit_action_labels() ) ? $action : '',
		'object_id' => $object,
		'page'      => $page,
	);
}

/**
 * Strona rejestru.
 */
function sdc_render_audit_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Brak uprawnień.', 'stop-dyskryminacji-core' ) );
	}

	$per_page = 50;
	$filters  = sdc_audit_filters();
	$rows     = sdc_audit_query( array_merge( $filters, array( 'per_page' => $per_page ) ) );
	$labels   = sdc_audit_action_labels();
	$base     = admin_url( 'edit.php?post_type=zgloszenie&page=sdc-rejestr' );
	$keep     = array( 'czynnosc' => $filters['action'], 'zgloszenie' => $filters['object_id'] ?: '' );
	$export   = wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'sdc_audit_export' ), $keep ), admin_url( 'admin-post.php' ) ), 'sdc_audit_export' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Rejestr czynności', 'stop-dyskryminacji-core' ); ?></h1>
		<p class="description">
			<?php esc_html_e( 'Kto, kiedy i co zrobił ze zgłoszeniami — bez treści zgłoszeń i bez danych osobowych zgłaszających. Wpisy są przechowywane co najmniej 12 miesięcy (umowa powierzenia, § 3 ust. 1).', 'stop-dyskryminacji-core' ); ?>
		</p>

		<form method="get" style="margin:1em 0;display:flex;flex-wrap:wrap;gap:.5em;align-items:center">
			<input type="hidden" name="post_type" value="zgloszenie">
			<input type="hidden" name="page" value="sdc-rejestr">
			<label for="sdc-audit-action"><?php esc_html_e( 'Czynność', 'stop-dyskryminacji-core' ); ?></label>
			<select id="sdc-audit-action" name="czynnosc">
				<option value=""><?php esc_html_e( 'Wszystkie', 'stop-dyskryminacji-core' ); ?></option>
				<?php foreach ( $labels as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $filters['action'], $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<label for="sdc-audit-object"><?php esc_html_e( 'Zgłoszenie (ID)', 'stop-dyskryminacji-core' ); ?></label>
			<input type="number" min="0" id="sdc-audit-object" name="zgloszenie" value="<?php echo $filters['object_id'] ? (int) $filters['object_id'] : ''; ?>" style="width:8em">
			<button type="submit" class="button"><?php esc_html_e( 'Filtruj', 'stop-dyskryminacji-core' ); ?></button>
			<a class="button button-secondary" href="<?php echo esc_url( $export ); ?>"><?php esc_html_e( 'Pobierz CSV', 'stop-dyskryminacji-core' ); ?></a>
		</form>

		<table class="widefat striped">
			<caption class="screen-reader-text"><?php esc_html_e( 'Wpisy rejestru czynności, od najnowszych', 'stop-dyskryminacji-core' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Czas (UTC)', 'stop-dyskryminacji-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Użytkownik', 'stop-dyskryminacji-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Czynność', 'stop-dyskryminacji-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Zgłoszenie', 'stop-dyskryminacji-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Szczegóły', 'stop-dyskryminacji-core' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'Brak wpisów dla wybranych filtrów.', 'stop-dyskryminacji-core' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row->logged_at ); ?></td>
						<td><?php echo esc_html( $row->user_login ); ?></td>
						<td><?php echo esc_html( $labels[ $row->action ] ?? $row->action ); ?></td>
						<td>
							<?php if ( $row->object_id ) : ?>
								<a href="<?php echo esc_url( add_query_arg( 'zgloszenie', (int) $row->object_id, $base ) ); ?>">#<?php echo (int) $row->object_id; ?></a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $row->detail ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p style="display:flex;gap:.5em">
			<?php if ( $filters['page'] > 1 ) : ?>
				<a class="button" href="<?php echo esc_url( add_query_arg( array_merge( $keep, array( 'paged' => $filters['page'] - 1 ) ), $base ) ); ?>"><?php esc_html_e( '← Nowsze', 'stop-dyskryminacji-core' ); ?></a>
			<?php endif; ?>
			<?php if ( count( $rows ) === $per_page ) : ?>
				<a class="button" href="<?php echo esc_url( add_query_arg( array_merge( $keep, array( 'paged' => $filters['page'] + 1 ) ), $base ) ); ?>"><?php esc_html_e( 'Starsze →', 'stop-dyskryminacji-core' ); ?></a>
			<?php endif; ?>
		</p>
	</div>
	<?php
}

/**
 * Eksport rejestru do CSV — na żądanie Administratora, przy kontroli, po naruszeniu.
 */
function sdc_audit_export() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Brak uprawnień.', 'stop-dyskryminacji-core' ) );
	}
	check_admin_referer( 'sdc_audit_export' );

	$filters = sdc_audit_filters();
	$labels  = sdc_audit_action_labels();

	sdc_audit_log( 'rejestr_eksport', 0, 'Eksport rejestru czynności do CSV' );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="rejestr-czynnosci-' . gmdate( 'Y-m-d' ) . '.csv"' );

	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" );   // BOM — polskie znaki poprawnie w arkuszu kalkulacyjnym
	fputcsv( $out, array( 'czas_utc', 'uzytkownik', 'czynnosc', 'kod_czynnosci', 'zgloszenie', 'szczegoly' ), ';' );

	$page = 1;
	do {
		$rows = sdc_audit_query( array_merge( $filters, array( 'per_page' => 500, 'page' => $page ) ) );
		foreach ( $rows as $row ) {
			fputcsv( $out, array( $row->logged_at, $row->user_login, $labels[ $row->action ] ?? $row->action, $row->action, $row->object_id ? $row->object_id : '', $row->detail ), ';' );
		}
		$page++;
	} while ( 500 === count( $rows ) && $page < 1000 );

	fclose( $out );
	exit;
}
add_action( 'admin_post_sdc_audit_export', 'sdc_audit_export' );
