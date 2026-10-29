<?php
/**
 * Obsługa zgłoszeń w panelu.
 *
 * Realizuje obowiązki z § 2 umowy powierzenia: „udostępnienie funkcji
 * wyszukania, wyeksportowania i trwałego usunięcia pojedynczego zgłoszenia"
 * (prawa z rozdziału III RODO), oraz z § 3 ust. 1: rejestrowanie dostępu
 * pozwalające ustalić, kto i kiedy odczytał zgłoszenie.
 *
 * Kluczowa zasada: odszyfrowanie treści następuje dopiero w chwili wyświetlenia
 * jej uprawnionej osobie i za każdym razem zostawia ślad w rejestrze.
 *
 * @package StopDyskryminacjiCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Kolumny listy zgłoszeń.
 *
 * Na liście nie pokazujemy niczego z treści — ani imienia, ani adresu e-mail.
 * Lista bywa otwarta na ekranie w biurze i widoczna dla osób postronnych.
 *
 * @param array $columns Kolumny.
 * @return array
 */
function sdc_submission_columns( $columns ) {
	return array(
		'cb'         => isset( $columns['cb'] ) ? $columns['cb'] : '',
		'title'      => __( 'Sygnatura', 'stop-dyskryminacji-core' ),
		'sdc_status' => __( 'Status', 'stop-dyskryminacji-core' ),
		'sdc_rola'   => __( 'Rola zgłaszającego', 'stop-dyskryminacji-core' ),
		'sdc_ground' => __( 'Przesłanka', 'stop-dyskryminacji-core' ),
		'sdc_flag'   => __( 'Uwagi', 'stop-dyskryminacji-core' ),
		'date'       => __( 'Wpłynęło', 'stop-dyskryminacji-core' ),
		'sdc_expiry' => __( 'Usunięcie', 'stop-dyskryminacji-core' ),
	);
}
add_filter( 'manage_zgloszenie_posts_columns', 'sdc_submission_columns' );

/**
 * Zawartość kolumn.
 *
 * @param string $column  Nazwa kolumny.
 * @param int    $post_id Identyfikator.
 */
function sdc_submission_column_content( $column, $post_id ) {
	switch ( $column ) {
		case 'sdc_status':
			$statuses = sdc_submission_statuses();
			$current  = (string) get_post_meta( $post_id, '_sdc_status', true );
			echo esc_html( $statuses[ $current ] ?? $current );
			break;

		case 'sdc_rola':
			$roles = sdc_reporter_roles();
			$role  = (string) get_post_meta( $post_id, '_sdc_rola', true );
			echo esc_html( $roles[ $role ] ?? '—' );
			break;

		case 'sdc_ground':
			$grounds = sdc_grounds();
			$ground  = (string) get_post_meta( $post_id, '_sdc_przeslanka', true );
			echo esc_html( $grounds[ $ground ] ?? '—' );
			break;

		case 'sdc_flag':
			if ( '1' === get_post_meta( $post_id, '_sdc_manual_review', true ) ) {
				echo '<span style="color:#8e0d17;font-weight:600">' . esc_html__( 'Do weryfikacji ręcznej', 'stop-dyskryminacji-core' ) . '</span>';
			} else {
				echo '—';
			}
			break;

		case 'sdc_expiry':
			echo esc_html( sdc_submission_retention_label( $post_id ) ); break;
	}
}
add_action( 'manage_zgloszenie_posts_custom_column', 'sdc_submission_column_content', 10, 2 );

/**
 * Statusy obsługi zgłoszenia.
 *
 * @return array
 */
function sdc_submission_statuses() {
	return array(
		'nowe'       => __( 'Nowe', 'stop-dyskryminacji-core' ),
		'w-toku'     => __( 'W toku', 'stop-dyskryminacji-core' ),
		'odpowiedz'  => __( 'Odpowiedziano', 'stop-dyskryminacji-core' ),
		'zamkniete'  => __( 'Zamknięte', 'stop-dyskryminacji-core' ),
	);
}

/**
 * Metaboksy na ekranie zgłoszenia.
 */
function sdc_submission_meta_boxes() {
	add_meta_box(
		'sdc_submission_content',
		__( 'Treść zgłoszenia (odszyfrowana)', 'stop-dyskryminacji-core' ),
		'sdc_submission_content_box',
		'zgloszenie',
		'normal',
		'high'
	);

	add_meta_box(
		'sdc_submission_actions',
		__( 'Obsługa i prawa osoby zgłaszającej', 'stop-dyskryminacji-core' ),
		'sdc_submission_actions_box',
		'zgloszenie',
		'side',
		'high'
	);

	add_meta_box(
		'sdc_submission_audit',
		__( 'Rejestr dostępu do tego zgłoszenia', 'stop-dyskryminacji-core' ),
		'sdc_submission_audit_box',
		'zgloszenie',
		'normal',
		'low'
	);
}
add_action( 'add_meta_boxes_zgloszenie', 'sdc_submission_meta_boxes' );

/**
 * Metaboks z odszyfrowaną treścią.
 *
 * @param WP_Post $post Zgłoszenie.
 */
function sdc_submission_content_box( $post ) {
	if ( ! current_user_can( 'edit_post', $post->ID ) ) { echo '<p>' . esc_html__( 'Brak uprawnień do odczytu zgłoszenia.', 'stop-dyskryminacji-core' ) . '</p>'; return; }
	$payload = (string) get_post_meta( $post->ID, '_sdc_payload', true );
	$data    = $payload ? sdc_decrypt_array( $payload ) : new WP_Error( 'sdc_empty', __( 'Zgłoszenie nie zawiera danych.', 'stop-dyskryminacji-core' ) );

	if ( is_wp_error( $data ) ) {
		printf(
			'<div class="notice notice-error inline"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'Nie można odczytać treści.', 'stop-dyskryminacji-core' ),
			esc_html( $data->get_error_message() )
		);
		sdc_audit_log( 'zgloszenie_odczyt_blad', $post->ID, $data->get_error_code() );
		return;
	}

	// Każde wyświetlenie treści zostawia ślad — to jest właśnie ten rejestr,
	// którego wymaga § 3 ust. 1 umowy powierzenia.
	sdc_audit_log( 'zgloszenie_odczyt', $post->ID, 'Wyświetlenie odszyfrowanej treści' );

	$roles   = sdc_reporter_roles();
	$grounds = sdc_grounds();
	?>
	<div class="notice notice-warning inline" style="margin:0 0 12px">
		<p><?php esc_html_e( 'Ten odczyt został odnotowany w rejestrze. Nie kopiuj treści poza panel i nie wprowadzaj jej do narzędzi zewnętrznych ani do narzędzi generatywnej sztucznej inteligencji (§ 3 ust. 1 umowy powierzenia).', 'stop-dyskryminacji-core' ); ?></p>
	</div>

	<table class="widefat striped" style="margin-bottom:12px">
		<tbody>
			<tr>
				<th scope="row" style="width:190px"><?php esc_html_e( 'Imię / określenie', 'stop-dyskryminacji-core' ); ?></th>
				<td><?php echo esc_html( $data['imie'] ?? '' ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'E-mail', 'stop-dyskryminacji-core' ); ?></th>
				<td><a href="mailto:<?php echo esc_attr( $data['email'] ?? '' ); ?>"><?php echo esc_html( $data['email'] ?? '' ); ?></a></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Telefon', 'stop-dyskryminacji-core' ); ?></th>
				<td><?php echo $data['telefon'] ? esc_html( $data['telefon'] ) : '<em>' . esc_html__( 'nie podano', 'stop-dyskryminacji-core' ) . '</em>'; ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Rola', 'stop-dyskryminacji-core' ); ?></th>
				<td><?php echo esc_html( $roles[ $data['rola'] ?? '' ] ?? '—' ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Przesłanka', 'stop-dyskryminacji-core' ); ?></th>
				<td><?php echo esc_html( $grounds[ $data['przeslanka'] ?? '' ] ?? '—' ); ?></td>
			</tr>
		</tbody>
	</table>

	<h3 style="margin:16px 0 6px"><?php esc_html_e( 'Opis sytuacji', 'stop-dyskryminacji-core' ); ?></h3>
	<div style="background:#fff;border:1px solid #c3c4c7;border-left:4px solid #8e0d17;padding:14px 16px;white-space:pre-wrap;line-height:1.6;max-height:520px;overflow:auto">
		<?php echo esc_html( $data['opis'] ?? '' ); ?>
	</div>
	<?php
}

/**
 * Metaboks obsługi: status, eksport, usunięcie.
 *
 * @param WP_Post $post Zgłoszenie.
 */
function sdc_submission_actions_box( $post ) {
	wp_nonce_field( 'sdc_submission_save', 'sdc_submission_nonce' );

	$status = (string) get_post_meta( $post->ID, '_sdc_status', true );
	?>
	<p>
		<label for="sdc-status"><strong><?php esc_html_e( 'Status obsługi', 'stop-dyskryminacji-core' ); ?></strong></label><br>
		<select name="sdc_status" id="sdc-status" style="width:100%">
			<?php foreach ( sdc_submission_statuses() as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<?php sdc_submission_retention_fields( $post->ID ); ?>

	<hr>

	<p><strong><?php esc_html_e( 'Żądania z RODO', 'stop-dyskryminacji-core' ); ?></strong></p>
	<p class="description">
		<?php esc_html_e( 'Użyj, gdy osoba zgłaszająca skorzysta z prawa dostępu (art. 15) albo prawa do usunięcia (art. 17).', 'stop-dyskryminacji-core' ); ?>
	</p>

	<p>
		<a class="button button-secondary" style="width:100%;text-align:center"
			href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sdc_export_submission&post=' . $post->ID ), 'sdc_export_' . $post->ID ) ); ?>">
			<?php esc_html_e( 'Eksportuj zgłoszenie (JSON)', 'stop-dyskryminacji-core' ); ?>
		</a>
	</p>

	<p>
		<a class="button" style="width:100%;text-align:center;color:#8e0d17;border-color:#8e0d17"
			href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sdc_erase_submission&post=' . $post->ID ), 'sdc_erase_' . $post->ID ) ); ?>"
			onclick="return confirm('<?php echo esc_js( __( 'Trwale usunąć to zgłoszenie? Operacji nie da się cofnąć.', 'stop-dyskryminacji-core' ) ); ?>');">
			<?php esc_html_e( 'Usuń trwale', 'stop-dyskryminacji-core' ); ?>
		</a>
	</p>

	<hr>
	<p class="description">
		<?php
		/* Last actual contact, independent of the case status. */
		printf(
			/* translators: %s: data */
			esc_html__( 'Automatyczne usunięcie: %s', 'stop-dyskryminacji-core' ),
			esc_html( sdc_submission_retention_label( $post->ID ) )
		);
		?>
	</p>
	<?php
}

/**
 * Metaboks z rejestrem dostępu do zgłoszenia.
 *
 * @param WP_Post $post Zgłoszenie.
 */
function sdc_submission_audit_box( $post ) {
	$rows = sdc_audit_query( array( 'object_id' => $post->ID, 'per_page' => 40 ) );

	if ( ! $rows ) {
		echo '<p>' . esc_html__( 'Brak wpisów.', 'stop-dyskryminacji-core' ) . '</p>';
		return;
	}
	?>
	<table class="widefat striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Kiedy (UTC)', 'stop-dyskryminacji-core' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Kto', 'stop-dyskryminacji-core' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Czynność', 'stop-dyskryminacji-core' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Szczegóły', 'stop-dyskryminacji-core' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<tr>
					<td><?php echo esc_html( $row->logged_at ); ?></td>
					<td><?php echo esc_html( $row->user_login ); ?></td>
					<td><code><?php echo esc_html( $row->action ); ?></code></td>
					<td><?php echo esc_html( $row->detail ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/**
 * Zapis statusu obsługi.
 *
 * @param int $post_id Identyfikator.
 */
function sdc_save_submission_meta( $post_id ) {
	if ( ! isset( $_POST['sdc_submission_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['sdc_submission_nonce'] ) ), 'sdc_submission_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$statuses = sdc_submission_statuses();
	$new      = sdc_post( 'sdc_status', 'key' );

	if ( isset( $statuses[ $new ] ) ) {
		$old = (string) get_post_meta( $post_id, '_sdc_status', true );
		$result = sdc_save_retention_dates( $post_id, $old, $new );
		if ( is_wp_error( $result ) ) {
			set_transient( 'sdc_contact_error_' . get_current_user_id(), $result->get_error_message(), 60 );
			return;
		}
		if ( $old !== $new ) {
			update_post_meta( $post_id, '_sdc_status', $new );
			sdc_audit_log( 'zgloszenie_status', $post_id, $old . ' → ' . $new );
		}
	}
}
add_action( 'save_post_zgloszenie', 'sdc_save_submission_meta' );

/**
 * Eksport pojedynczego zgłoszenia — art. 15 i 20 RODO.
 */
function sdc_export_submission() {
	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;

	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( esc_html__( 'Brak uprawnień.', 'stop-dyskryminacji-core' ), 403 );
	}
	check_admin_referer( 'sdc_export_' . $post_id );

	$data = sdc_decrypt_array( (string) get_post_meta( $post_id, '_sdc_payload', true ) );

	if ( is_wp_error( $data ) ) {
		wp_die( esc_html( $data->get_error_message() ) );
	}

	sdc_audit_log( 'zgloszenie_eksport', $post_id, 'Eksport do pliku JSON' );

	$export = array(
		'sygnatura'       => get_the_title( $post_id ),
		'wplynelo'        => get_post_time( 'c', true, $post_id ),
		'status'          => (string) get_post_meta( $post_id, '_sdc_status', true ),
		'dane_zgloszenia' => $data,
		'rejestr_dostepu' => array_map(
			function ( $row ) {
				return array(
					'kiedy'     => $row->logged_at,
					'kto'       => $row->user_login,
					'czynnosc'  => $row->action,
					'szczegoly' => $row->detail,
				);
			},
			sdc_audit_query( array( 'object_id' => $post_id, 'per_page' => 500 ) )
		),
	);

	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( get_the_title( $post_id ) ) . '.json"' );
	echo wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	exit;
}
add_action( 'admin_post_sdc_export_submission', 'sdc_export_submission' );

/**
 * Trwałe usunięcie pojedynczego zgłoszenia — art. 17 RODO.
 */
function sdc_erase_submission() {
	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;

	if ( ! $post_id || ! current_user_can( 'delete_post', $post_id ) ) {
		wp_die( esc_html__( 'Brak uprawnień.', 'stop-dyskryminacji-core' ), 403 );
	}
	check_admin_referer( 'sdc_erase_' . $post_id );

	$signature = get_the_title( $post_id );

	// Rejestr zapisujemy przed usunięciem — to jedyny moment, w którym
	// da się jeszcze powiązać czynność z sygnaturą sprawy.
	sdc_audit_log( 'zgloszenie_usuniete', $post_id, 'Usunięcie na żądanie: ' . $signature );

	wp_delete_post( $post_id, true );

	wp_safe_redirect( add_query_arg( 'sdc_erased', '1', admin_url( 'edit.php?post_type=zgloszenie' ) ) );
	exit;
}
add_action( 'admin_post_sdc_erase_submission', 'sdc_erase_submission' );

/**
 * Komunikat po usunięciu.
 */
function sdc_erased_notice() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['sdc_erased'] ) ) {
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html__( 'Zgłoszenie zostało trwale usunięte. Czynność odnotowano w rejestrze.', 'stop-dyskryminacji-core' )
		);
	}
}
add_action( 'admin_notices', 'sdc_erased_notice' );

/**
 * Wyłączenie edytora bloków dla zgłoszeń — to nie jest treść do redagowania.
 *
 * @param bool   $use  Czy użyć edytora bloków.
 * @param string $type Typ wpisu.
 * @return bool
 */
function sdc_disable_block_editor_for_submissions( $use, $type ) {
	return 'zgloszenie' === $type ? false : $use;
}
add_filter( 'use_block_editor_for_post_type', 'sdc_disable_block_editor_for_submissions', 10, 2 );

/**
 * Wyszukiwanie zgłoszeń po adresie e-mail na liście w panelu.
 *
 * Pole wyszukiwania listy zgłoszeń przeszukuje sygnatury (tytuły). Gdy wpisany
 * tekst jest adresem e-mail, szukamy po ślepym indeksie (sdc_email_index()) —
 * sam adres jest w bazie zaszyfrowany i zwykłe wyszukiwanie nic by nie znalazło.
 * Każde takie wyszukanie trafia do rejestru czynności, bez zapisywania adresu.
 *
 * @param WP_Query $query Zapytanie.
 */
function sdc_search_submissions_by_email( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() || 'zgloszenie' !== $query->get( 'post_type' ) ) {
		return;
	}

	$term = trim( (string) $query->get( 's' ) );
	if ( '' === $term || ! is_email( $term ) ) {
		return;
	}

	$index = sdc_email_index( $term );
	$query->set( 's', '' );
	$query->set( 'meta_query', array( array( 'key' => '_sdc_email_idx', 'value' => '' !== $index ? $index : '-' ) ) );

	sdc_audit_log( 'zgloszenie_wyszukanie', 0, 'Wyszukanie po adresie e-mail (skrót, bez zapisu adresu)' );
}
add_action( 'pre_get_posts', 'sdc_search_submissions_by_email' );

add_action( 'admin_notices', function () {
	$key = 'sdc_contact_error_' . get_current_user_id(); $error = get_transient( $key );
	if ( $error ) { delete_transient( $key ); echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>'; }
} );
