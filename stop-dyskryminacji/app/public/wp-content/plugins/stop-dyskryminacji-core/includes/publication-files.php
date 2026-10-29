<?php
/**
 * Pliki publikacji: przypisanie po nazwie pliku i zestawienie plików.
 *
 * Plik wgrany w Media → Dodaj nowy plik trafia do publikacji sam, jeżeli jego
 * nazwa zaczyna się od:
 *   - kodu publikacji (pole „Kod w nazwach plików", np. P4 → „P4_kompendium.pdf"),
 *   - adresu publikacji („kompendium-dobrych-praktyk.pdf") albo
 *   - jej tytułu („Kompendium dobrych praktyk.pdf").
 * Wielkość liter, polskie znaki, spacje i podkreślniki nie mają znaczenia.
 * Format wynika z rozszerzenia: PDF, EPUB albo DOCX. Nowszy plik zastępuje
 * poprzedni — poprzedni zostaje w bibliotece mediów.
 *
 * Plików wgranych w edytorze konkretnej treści automat nie rusza: w panelu
 * „Pliki do pobrania" przypisuje je redaktor, a plik nazwany jak inna
 * publikacja nie może do niej trafić przy okazji.
 *
 * @package StopDyskryminacjiCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Formaty plików publikacji według rozszerzenia.
 *
 * @return array
 */
function sdc_publication_file_formats() {
	return array(
		'pdf'  => array( 'meta' => '_sdc_pdf_id', 'mime' => 'application/pdf', 'label' => 'PDF' ),
		'epub' => array( 'meta' => '_sdc_epub_id', 'mime' => 'application/epub+zip', 'label' => 'EPUB' ),
		'docx' => array( 'meta' => '_sdc_docx_id', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'label' => 'DOCX' ),
	);
}

/**
 * Nazwa pliku sprowadzona do postaci adresu: „Kodeks Równości_2026.pdf" → „kodeks-rownosci-2026".
 *
 * @param string $name Nazwa albo ścieżka pliku.
 * @return string
 */
function sdc_publication_file_key( $name ) {
	$base = preg_replace( '/\.[A-Za-z0-9]+$/', '', wp_basename( (string) $name ) );
	return sanitize_title( str_replace( '_', '-', $base ) );
}

/**
 * Pliki dokumentów zapisywane pod czytelną nazwą — małe litery, bez polskich znaków
 * i spacji. Nazwa jest częścią adresu do pobrania. Obrazów to nie dotyczy.
 *
 * @param string $filename Nazwa pliku po sanityzacji WordPressa.
 * @return string
 */
function sdc_clean_document_file_name( $filename ) {
	$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
	if ( ! array_key_exists( $ext, sdc_publication_file_formats() ) ) {
		return $filename;
	}
	$base = sdc_publication_file_key( $filename );
	return ( '' !== $base ? $base : 'plik' ) . '.' . $ext;
}
add_filter( 'sanitize_file_name', 'sdc_clean_document_file_name', 20 );

/**
 * Klucze dopasowania każdej publikacji: kod, adres i tytuł w postaci adresu.
 *
 * @return array Tablica [ ID publikacji => [ 'code' => klucz|'' , 'names' => [ klucze ] ] ].
 */
function sdc_publication_match_keys() {
	$keys = array();
	$ids  = get_posts(
		array(
			'post_type'      => 'publikacja',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'fields'         => 'ids',
		)
	);
	foreach ( $ids as $id ) {
		$code        = sanitize_title( (string) get_post_meta( $id, '_sdc_file_code', true ) );
		$keys[ $id ] = array(
			'code'  => $code,
			'names' => array_values( array_unique( array_filter( array( get_post_field( 'post_name', $id ), sanitize_title( get_the_title( $id ) ) ) ) ) ),
		);
	}
	return $keys;
}

/**
 * Publikacja, do której należy plik o danym kluczu nazwy.
 *
 * Kod ma pierwszeństwo przed adresem i tytułem; przy kilku pasujących adresach
 * wygrywa najdłuższy (najbardziej szczegółowy).
 *
 * @param string $file_key Klucz nazwy pliku (sdc_publication_file_key()).
 * @param array  $keys     Klucze publikacji (sdc_publication_match_keys()).
 * @return int ID publikacji albo 0.
 */
function sdc_match_publication( $file_key, $keys ) {
	$starts = function ( $key ) use ( $file_key ) {
		return '' !== $key && ( $file_key === $key || 0 === strpos( $file_key, $key . '-' ) );
	};

	foreach ( $keys as $id => $k ) {
		if ( $starts( $k['code'] ) ) {
			return (int) $id;
		}
	}

	$best     = 0;
	$best_len = 0;
	foreach ( $keys as $id => $k ) {
		foreach ( $k['names'] as $name ) {
			if ( $starts( $name ) && strlen( $name ) > $best_len ) {
				$best     = (int) $id;
				$best_len = strlen( $name );
			}
		}
	}
	return $best;
}

/**
 * Przypisanie pliku z biblioteki mediów do publikacji według nazwy.
 *
 * @param int        $attachment_id ID załącznika.
 * @param array|null $keys          Klucze publikacji — przy wielu plikach liczone raz.
 * @return array Wynik: status (przypisano|brak-dopasowania|pominieto), publikacja, format, plik.
 */
function sdc_assign_publication_file( $attachment_id, $keys = null ) {
	$file    = (string) get_attached_file( $attachment_id );
	$ext     = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
	$formats = sdc_publication_file_formats();
	$result  = array( 'status' => 'pominieto', 'publication' => 0, 'format' => $ext, 'file' => wp_basename( $file ) );

	if ( ! isset( $formats[ $ext ] ) || get_post_mime_type( $attachment_id ) !== $formats[ $ext ]['mime'] ) {
		return $result;
	}

	$pub_id = sdc_match_publication( sdc_publication_file_key( $file ), null === $keys ? sdc_publication_match_keys() : $keys );
	if ( ! $pub_id ) {
		$result['status'] = 'brak-dopasowania';
		return $result;
	}

	$parent = (int) get_post_field( 'post_parent', $attachment_id );
	if ( ( $parent && $parent !== $pub_id ) || ! current_user_can( 'edit_post', $pub_id ) ) {
		return $result;
	}

	// Starszy plik nie zastępuje nowszego (identyfikatory rosną z każdym wgraniem).
	$current = (int) get_post_meta( $pub_id, $formats[ $ext ]['meta'], true );
	if ( $current > (int) $attachment_id && get_post( $current ) ) {
		$result['status'] = 'starszy';
		return $result;
	}

	update_post_meta( $pub_id, $formats[ $ext ]['meta'], (int) $attachment_id );
	if ( ! $parent ) {
		// Biblioteka mediów pokaże „Przesłano do: <publikacja>". Bez wp_update_post —
		// jesteśmy w trakcie zapisu załącznika.
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_parent' => $pub_id ), array( 'ID' => (int) $attachment_id ) );
		clean_post_cache( $attachment_id );
	}

	$result['status']      = 'przypisano';
	$result['publication'] = $pub_id;
	return $result;
}

/**
 * Przypisane pliki: [ ID publikacji => [ pdf|epub|docx => ID załącznika albo 0 ] ].
 *
 * @param int[] $publication_ids Publikacje.
 * @return array
 */
function sdc_publication_file_slots( $publication_ids ) {
	$slots = array();
	foreach ( $publication_ids as $pub_id ) {
		foreach ( sdc_publication_file_formats() as $ext => $format ) {
			$slots[ $pub_id ][ $ext ] = (int) get_post_meta( $pub_id, $format['meta'], true );
		}
	}
	return $slots;
}

/**
 * Po wgraniu pliku — przypisanie, jeżeli nazwa wskazuje publikację.
 *
 * @param int $attachment_id ID nowego załącznika.
 */
function sdc_auto_assign_publication_file( $attachment_id ) {
	sdc_assign_publication_file( $attachment_id );
}
add_action( 'add_attachment', 'sdc_auto_assign_publication_file' );

/* =========================================================================
 * Zestawienie: Publikacje → Pliki do pobrania
 * ====================================================================== */

/**
 * Pozycja w menu Publikacje.
 */
function sdc_publication_files_menu() {
	add_submenu_page(
		'edit.php?post_type=publikacja',
		__( 'Pliki do pobrania', 'stop-dyskryminacji-core' ),
		__( 'Pliki do pobrania', 'stop-dyskryminacji-core' ),
		'edit_others_posts',
		'sdc-pliki-publikacji',
		'sdc_render_publication_files_page'
	);
}
add_action( 'admin_menu', 'sdc_publication_files_menu' );

/**
 * Opis pliku do tabeli: nazwa i rozmiar albo „brak".
 *
 * @param int $attachment_id ID załącznika albo 0.
 * @return string HTML.
 */
function sdc_publication_file_cell( $attachment_id ) {
	$path = $attachment_id ? get_attached_file( $attachment_id ) : '';
	$url  = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
	if ( ! $url ) {
		return '<span style="color:#b32d2e">' . esc_html__( 'brak', 'stop-dyskryminacji-core' ) . '</span>';
	}
	$size = ( $path && file_exists( $path ) ) ? size_format( filesize( $path ), 1 ) : '';
	return sprintf(
		'<a href="%1$s">%2$s</a>%3$s',
		esc_url( $url ),
		esc_html( wp_basename( $url ) ),
		$size ? ' <span class="description">· ' . esc_html( $size ) . '</span>' : ''
	);
}

/**
 * Ekran zestawienia.
 */
function sdc_render_publication_files_page() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		return;
	}
	$formats = sdc_publication_file_formats();
	$keys    = sdc_publication_match_keys();
	$report  = get_transient( 'sdc_map_result_' . get_current_user_id() );
	if ( $report ) {
		delete_transient( 'sdc_map_result_' . get_current_user_id() );
	}

	// Ten sam kod w dwóch publikacjach — pliki trafiłyby tylko do jednej.
	$codes = array_filter( wp_list_pluck( $keys, 'code' ) );
	$dupes = array_unique( array_diff_assoc( $codes, array_unique( $codes ) ) );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Pliki do pobrania', 'stop-dyskryminacji-core' ); ?></h1>

		<?php if ( $report ) : ?>
			<div class="notice notice-success"><p>
				<?php
				printf(
					/* translators: 1: liczba zmienionych przypisań, 2: liczba plików bez dopasowania */
					esc_html__( 'Nowe przypisania: %1$d. Pliki bez dopasowania: %2$d.', 'stop-dyskryminacji-core' ),
					count( $report['changes'] ),
					count( $report['unmatched'] )
				);
				?>
			</p>
			<?php if ( $report['changes'] ) : ?>
				<ul style="list-style:disc;margin-left:2em">
					<?php foreach ( $report['changes'] as $line ) : ?>
						<li><?php echo esc_html( $line ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( $report['unmatched'] ) : ?>
				<p><?php esc_html_e( 'Tych plików nie udało się przypisać — popraw nazwę albo wskaż plik ręcznie w publikacji:', 'stop-dyskryminacji-core' ); ?></p>
				<ul style="list-style:disc;margin-left:2em">
					<?php foreach ( $report['unmatched'] as $name ) : ?>
						<li><code><?php echo esc_html( $name ); ?></code></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php foreach ( $dupes as $dupe ) : ?>
			<div class="notice notice-warning"><p>
				<?php
				printf(
					/* translators: %s: kod publikacji */
					esc_html__( 'Kod %s jest wpisany w więcej niż jednej publikacji — pliki trafią tylko do pierwszej z nich.', 'stop-dyskryminacji-core' ),
					'<code>' . esc_html( strtoupper( $dupe ) ) . '</code>'
				);
				?>
			</p></div>
		<?php endforeach; ?>

		<p style="max-width:52em">
			<?php esc_html_e( 'Wgraj pliki w Media → Dodaj nowy plik — można przeciągnąć wiele naraz. Plik trafi do publikacji sam, jeżeli jego nazwa zaczyna się od kodu publikacji (np. P4_kompendium.pdf), jej adresu albo tytułu. Format wynika z rozszerzenia: PDF, EPUB, DOCX. Nowszy plik zastępuje poprzedni.', 'stop-dyskryminacji-core' ); ?>
		</p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'media-new.php' ) ); ?>"><?php esc_html_e( 'Wgraj pliki', 'stop-dyskryminacji-core' ); ?></a>
		</p>

		<table class="widefat striped" style="max-width:1100px">
			<caption class="screen-reader-text"><?php esc_html_e( 'Pliki przypisane do publikacji', 'stop-dyskryminacji-core' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Publikacja', 'stop-dyskryminacji-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Kod', 'stop-dyskryminacji-core' ); ?></th>
					<?php foreach ( $formats as $format ) : ?>
						<th scope="col"><?php echo esc_html( $format['label'] ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! $keys ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'Nie ma jeszcze publikacji.', 'stop-dyskryminacji-core' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( array_keys( $keys ) as $id ) : ?>
					<?php $code = (string) get_post_meta( $id, '_sdc_file_code', true ); ?>
					<tr>
						<th scope="row">
							<a href="<?php echo esc_url( get_edit_post_link( $id ) ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></a>
							<?php echo 'publish' !== get_post_status( $id ) ? ' <span class="description">(' . esc_html( get_post_status_object( get_post_status( $id ) )->label ) . ')</span>' : ''; ?>
						</th>
						<td><?php echo '' !== $code ? '<code>' . esc_html( $code ) . '</code>' : '—'; ?></td>
						<?php foreach ( $formats as $format ) : ?>
							<td><?php echo sdc_publication_file_cell( (int) get_post_meta( $id, $format['meta'], true ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- zbudowane z esc_* ?></td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Pliki wgrane wcześniej', 'stop-dyskryminacji-core' ); ?></h2>
		<p style="max-width:52em">
			<?php esc_html_e( 'Jeżeli pliki były w bibliotece mediów, zanim nadano im właściwe nazwy albo wpisano kody publikacji, przypisz je tutaj. Przy kilku pasujących plikach wygrywa najnowszy. Pliki wgrane w edytorze innych treści zostają pominięte.', 'stop-dyskryminacji-core' ); ?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="sdc_map_publication_files">
			<?php wp_nonce_field( 'sdc_map_publication_files' ); ?>
			<?php submit_button( __( 'Przypisz pliki z biblioteki mediów', 'stop-dyskryminacji-core' ), 'secondary', 'submit', false ); ?>
		</form>
	</div>
	<?php
}

/**
 * Przypisanie plików już obecnych w bibliotece mediów — od najstarszych, więc
 * przy kilku pasujących wygrywa najnowszy.
 *
 * @return array changes — opisy nowych przypisań; unmatched — nazwy plików bez
 *               dopasowania, których nie używa żadna publikacja.
 */
function sdc_map_library_files() {
	$ids = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => wp_list_pluck( sdc_publication_file_formats(), 'mime' ),
			'posts_per_page' => -1,
			'orderby'        => 'date ID',
			'order'          => 'ASC',
			'fields'         => 'ids',
		)
	);

	$keys   = sdc_publication_match_keys();
	$before = sdc_publication_file_slots( array_keys( $keys ) );

	$unmatched = array();
	foreach ( $ids as $id ) {
		$r = sdc_assign_publication_file( $id, $keys );
		if ( 'brak-dopasowania' === $r['status'] ) {
			$unmatched[ $id ] = $r['file'];
		}
	}

	// W raporcie tylko to, co się zmieniło, i pliki, których nie używa żadna publikacja.
	$after   = sdc_publication_file_slots( array_keys( $keys ) );
	$changes = array();
	$used    = array();
	foreach ( $after as $pub_id => $slots ) {
		foreach ( $slots as $ext => $aid ) {
			$used[ $aid ] = true;
			if ( $aid && $aid !== $before[ $pub_id ][ $ext ] ) {
				$changes[] = sprintf( '%s — %s: %s', get_the_title( $pub_id ), strtoupper( $ext ), wp_basename( (string) get_attached_file( $aid ) ) );
			}
		}
	}
	return array(
		'changes'   => $changes,
		'unmatched' => array_values( array_diff_key( $unmatched, $used ) ),
	);
}

/**
 * Przycisk „Przypisz pliki z biblioteki mediów".
 */
function sdc_handle_map_publication_files() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'Brak uprawnień.', 'stop-dyskryminacji-core' ), 403 );
	}
	check_admin_referer( 'sdc_map_publication_files' );

	set_transient( 'sdc_map_result_' . get_current_user_id(), sdc_map_library_files(), 5 * MINUTE_IN_SECONDS );
	wp_safe_redirect( admin_url( 'edit.php?post_type=publikacja&page=sdc-pliki-publikacji' ) );
	exit;
}
add_action( 'admin_post_sdc_map_publication_files', 'sdc_handle_map_publication_files' );
