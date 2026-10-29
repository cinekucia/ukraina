<?php
/**
 * Bloki dynamiczne platformy.
 *
 * Wszystkie bloki są renderowane po stronie serwera i rejestrowane bez etapu
 * budowania (brak webpacka, brak node_modules). Powód jest praktyczny:
 * repozytorium przekazywane Zamawiającemu ma być możliwe do uruchomienia
 * i poprawienia bez odtwarzania środowiska narzędziowego. Podgląd w edytorze
 * zapewnia wp.serverSideRender, który jest częścią WordPressa.
 *
 * @package StopDyskryminacjiCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rejestracja bloków.
 */
function sdc_register_blocks() {

	$blocks = array(
		'eu-lockup'              => array(
			'title'       => __( 'Zestaw znaków FE / RP / UE / herb', 'stop-dyskryminacji-core' ),
			'description' => __( 'Obowiązkowe oznakowanie projektu. Nie usuwaj — jego brak oznacza pomniejszenie dofinansowania.', 'stop-dyskryminacji-core' ),
			'icon'        => 'awards',
			'render'      => 'sdc_render_eu_lockup',
			'attributes'  => array( 'context' => array( 'type' => 'string', 'default' => 'header' ) ),
		),
		'help-form'              => array(
			'title'       => __( 'Formularz „Potrzebuję pomocy"', 'stop-dyskryminacji-core' ),
			'description' => __( 'Moduł zgłoszeniowy z szyfrowaniem treści i rejestrem dostępu.', 'stop-dyskryminacji-core' ),
			'icon'        => 'sos',
			'render'      => 'sdc_render_help_form',
		),
		'self-assessment'        => array(
			'title'       => __( 'Samoocena firmy', 'stop-dyskryminacji-core' ),
			'description' => __( 'Kwestionariusz dla pracodawcy z wynikiem punktowym i rekomendacjami.', 'stop-dyskryminacji-core' ),
			'icon'        => 'chart-bar',
			'render'      => 'sdc_render_self_assessment',
		),
		'publication-downloads'  => array(
			'title'       => __( 'Pobrania publikacji', 'stop-dyskryminacji-core' ),
			'description' => __( 'Przyciski pobierania PDF, EPUB i wersji edytowalnej.', 'stop-dyskryminacji-core' ),
			'icon'        => 'download',
			'render'      => 'sdc_render_publication_downloads',
		),
		'publication-formats'    => array(
			'title'       => __( 'Dostępne formaty publikacji', 'stop-dyskryminacji-core' ),
			'description' => __( 'Krótka lista formatów na karcie publikacji.', 'stop-dyskryminacji-core' ),
			'icon'        => 'media-default',
			'render'      => 'sdc_render_publication_formats',
		),
		'publication-filters'    => array(
			'title'       => __( 'Filtry publikacji', 'stop-dyskryminacji-core' ),
			'description' => __( 'Filtrowanie po typie, przesłance i grupie odbiorców. Działa bez JavaScriptu.', 'stop-dyskryminacji-core' ),
			'icon'        => 'filter',
			'render'      => 'sdc_render_publication_filters',
		),
		'institution-finder'     => array(
			'title'       => __( 'Nawigator instytucji pomocowych', 'stop-dyskryminacji-core' ),
			'description' => __( 'Wykaz instytucji w Wielkopolsce z danymi kontaktowymi.', 'stop-dyskryminacji-core' ),
			'icon'        => 'location-alt',
			'render'      => 'sdc_render_institution_finder',
		),
		'help-cta'               => array(
			'title'       => __( 'Przycisk „Potrzebuję pomocy”', 'stop-dyskryminacji-core' ),
			'description' => __( 'Główne wezwanie do działania w nagłówku. Napis i adres dostosowują się do języka strony.', 'stop-dyskryminacji-core' ),
			'icon'        => 'sos',
			'render'      => 'sdc_render_help_cta',
		),
		'language-switcher'      => array(
			'title'       => __( 'Przełącznik języka', 'stop-dyskryminacji-core' ),
			'description' => __( 'Wybór wersji językowej PL / UA / EN. Pokazuje tylko te wersje, które faktycznie istnieją.', 'stop-dyskryminacji-core' ),
			'icon'        => 'translation',
			'render'      => 'sdc_render_language_switcher',
		),
		'share'                  => array(
			'title'       => __( 'Udostępnij', 'stop-dyskryminacji-core' ),
			'description' => __( 'Przyciski udostępniania i kopiowania odnośnika.', 'stop-dyskryminacji-core' ),
			'icon'        => 'share',
			'render'      => 'sdc_render_share',
		),
		'breadcrumbs'            => array(
			'title'       => __( 'Ścieżka nawigacji', 'stop-dyskryminacji-core' ),
			'description' => __( 'Pokazuje położenie strony w serwisie.', 'stop-dyskryminacji-core' ),
			'icon'        => 'menu-alt',
			'render'      => 'sdc_render_breadcrumbs',
		),
		'reading-time'           => array(
			'title'       => __( 'Czas czytania', 'stop-dyskryminacji-core' ),
			'description' => __( 'Szacowany czas czytania artykułu.', 'stop-dyskryminacji-core' ),
			'icon'        => 'clock',
			'render'      => 'sdc_render_reading_time',
		),
		'last-updated'           => array(
			'title'       => __( 'Data aktualizacji', 'stop-dyskryminacji-core' ),
			'description' => __( 'Informacja, kiedy treść była ostatnio sprawdzana.', 'stop-dyskryminacji-core' ),
			'icon'        => 'calendar-alt',
			'render'      => 'sdc_render_last_updated',
		),
		'sitemap'                => array(
			'title'       => __( 'Mapa strony', 'stop-dyskryminacji-core' ),
			'description' => __( 'Spis wszystkich stron, publikacji i artykułów. Aktualizuje się sam.', 'stop-dyskryminacji-core' ),
			'icon'        => 'networking',
			'render'      => 'sdc_render_sitemap',
		),
		'brand'                  => array(
			'title'       => __( 'Logo serwisu', 'stop-dyskryminacji-core' ),
			'description' => __( 'Sygnet „Dymek równości" i nazwa projektu — odnośnik do strony głównej bieżącej wersji językowej. Sygnet nie jest wyższy od flagi UE w pasku znaków.', 'stop-dyskryminacji-core' ),
			'icon'        => 'format-chat',
			'render'      => 'sdc_render_brand',
		),
	);

	foreach ( $blocks as $slug => $conf ) {
		register_block_type(
			'stopdysk/' . $slug,
			array(
				'api_version'     => 3,
				'title'           => $conf['title'],
				'description'     => $conf['description'],
				'category'        => 'sd-bloki',
				'icon'            => $conf['icon'],
				'render_callback' => $conf['render'],
				'attributes'      => isset( $conf['attributes'] ) ? $conf['attributes'] : array(),
				'supports'        => array( 'html' => false, 'align' => array( 'wide', 'full' ) ),
			)
		);
	}
}
add_action( 'init', 'sdc_register_blocks' );

/**
 * Kategoria bloków projektu.
 *
 * @param array $categories Kategorie.
 * @return array
 */
function sdc_block_category( $categories ) {
	array_unshift(
		$categories,
		array(
			'slug'  => 'sd-bloki',
			'title' => __( 'STOP DYSKRYMINACJI', 'stop-dyskryminacji-core' ),
			'icon'  => null,
		)
	);
	return $categories;
}
add_filter( 'block_categories_all', 'sdc_block_category' );

/**
 * Skrypt edytora — rejestruje podgląd bloków bez etapu budowania.
 */
function sdc_block_editor_assets() {
	wp_enqueue_script(
		'sdc-blocks',
		SDC_URL . 'assets/js/blocks.js',
		array( 'wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-i18n', 'wp-block-editor', 'wp-components' ),
		SDC_VERSION,
		true
	);
}
add_action( 'enqueue_block_editor_assets', 'sdc_block_editor_assets' );

/**
 * Panel „Pliki do pobrania" — tylko w edytorze publikacji.
 */
function sdc_publication_files_editor_assets() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'publikacja' !== $screen->post_type ) {
		return;
	}
	wp_enqueue_script(
		'sdc-publication-files',
		SDC_URL . 'assets/js/publication-files.js',
		array( 'wp-plugins', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data', 'wp-block-editor', 'wp-notices', 'wp-i18n' ),
		SDC_VERSION,
		true
	);
}
add_action( 'enqueue_block_editor_assets', 'sdc_publication_files_editor_assets' );

/* =========================================================================
 * Funkcje renderujące bloki pomocnicze
 * ====================================================================== */

/**
 * Ścieżka nawigacji.
 *
 * @return string
 */
function sdc_render_breadcrumbs() {
	$in_section = function_exists( 'sdc_section_home_url' );

	// Strona główna sekcji UA/EN jest stroną główną — jak ta polska, bez okruszków.
	if ( is_front_page() || ( $in_section && sdc_is_section_home() ) ) {
		return '';
	}

	// „Strona główna" to strona główna BIEŻĄCEJ sekcji, nie zawsze polska.
	$home  = $in_section ? sdc_section_home_url() : home_url( '/' );
	$items = array( array( 'url' => $home, 'label' => __( 'Strona główna', 'stop-dyskryminacji-core' ) ) );

	if ( is_singular() ) {
		$post_type = get_post_type_object( get_post_type() );
		if ( $post_type && $post_type->has_archive ) {
			$archive = get_post_type_archive_link( get_post_type() );
			if ( $archive ) {
				$items[] = array( 'url' => $archive, 'label' => $post_type->labels->name );
			}
		}
		foreach ( array_reverse( (array) get_post_ancestors( get_the_ID() ) ) as $ancestor ) {
			// Strona /ua/ jako przodek to ta sama „Strona główna" — nie powtarzamy jej.
			if ( untrailingslashit( get_permalink( $ancestor ) ) === untrailingslashit( $home ) ) {
				continue;
			}
			$items[] = array( 'url' => get_permalink( $ancestor ), 'label' => get_the_title( $ancestor ) );
		}
		$items[] = array( 'url' => '', 'label' => get_the_title() );
	} elseif ( is_archive() ) {
		$items[] = array( 'url' => '', 'label' => wp_strip_all_tags( get_the_archive_title() ) );
	} elseif ( is_search() ) {
		$items[] = array( 'url' => '', 'label' => __( 'Wyniki wyszukiwania', 'stop-dyskryminacji-core' ) );
	}

	if ( count( $items ) < 2 ) {
		return '';
	}

	$html = '<nav class="sd-breadcrumbs" aria-label="' . esc_attr__( 'Ścieżka nawigacji', 'stop-dyskryminacji-core' ) . '"><ol>';

	foreach ( $items as $i => $item ) {
		$last = ( count( $items ) - 1 ) === $i;
		$html .= '<li>';
		if ( $i ) { $html .= '<span class="sd-breadcrumbs__separator" aria-hidden="true">/</span>'; }
		if ( $item['url'] && ! $last ) {
			$html .= '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a>';
		} else {
			$html .= '<span aria-current="page">' . esc_html( $item['label'] ) . '</span>';
		}
		$html .= '</li>';
	}

	return $html . '</ol></nav>';
}

/**
 * Szacowany czas czytania.
 *
 * Przyjęte 200 słów na minutę — wartość zbliżona do średniego tempa czytania
 * tekstu użytkowego po polsku.
 *
 * @return string
 */
function sdc_render_reading_time() {
	$content = get_post_field( 'post_content', get_the_ID() );

	if ( ! $content ) {
		return '';
	}

	$words   = str_word_count( wp_strip_all_tags( strip_shortcodes( $content ) ), 0, 'ąćęłńóśźżĄĆĘŁŃÓŚŹŻ' );
	$minutes = max( 1, (int) ceil( $words / 200 ) );

	return sprintf(
		'<span class="sd-reading-time">%s %s</span>',
		wp_kses( sdc_icon( 'clock', 15 ), sdc_kses_svg() ),
		esc_html( sprintf( _n( '%d minuta czytania', '%d min czytania', $minutes, 'stop-dyskryminacji-core' ), $minutes ) )
	);
}

/**
 * Data ostatniej aktualizacji treści.
 *
 * Na portalu poradniczym to informacja istotna merytorycznie: przepisy się
 * zmieniają, a czytelnik musi wiedzieć, na ile aktualna jest porada.
 *
 * @return string
 */
function sdc_render_last_updated() {
	$id = get_the_ID();

	if ( ! $id ) {
		return '';
	}

	$modified = get_post_modified_time( 'U', true, $id );

	return sprintf(
		'<p class="sd-last-updated"><small>%s <time datetime="%s">%s</time></small></p>',
		esc_html__( 'Ostatnia aktualizacja treści:', 'stop-dyskryminacji-core' ),
		esc_attr( gmdate( 'Y-m-d', $modified ) ),
		esc_html( wp_date( 'j F Y', $modified ) )
	);
}

/**
 * Przyciski udostępniania.
 *
 * Nie używamy wtyczek społecznościowych ani osadzonych widżetów — one ładują
 * skrypty firm trzecich i ustawiają pliki cookie jeszcze przed zgodą.
 * Zwykłe odnośniki nie ustawiają niczego, działają bez JavaScriptu
 * i nie psują wyniku Lighthouse.
 *
 * @return string
 */
function sdc_render_share() {
	if ( ! is_singular() ) {
		return '';
	}

	$url   = get_permalink();
	$title = get_the_title();

	$targets = array(
		'facebook' => array(
			'label' => 'Facebook',
			'url'   => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $url ),
		),
		'linkedin' => array(
			'label' => 'LinkedIn',
			'url'   => 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode( $url ),
		),
		'email'    => array(
			'label' => __( 'E-mail', 'stop-dyskryminacji-core' ),
			'url'   => 'mailto:?subject=' . rawurlencode( $title ) . '&body=' . rawurlencode( $url ),
		),
	);

	ob_start();
	?>
	<div class="sd-share">
		<span class="sd-share__label"><strong><?php esc_html_e( 'Udostępnij:', 'stop-dyskryminacji-core' ); ?></strong></span>
		<?php foreach ( $targets as $key => $target ) : ?>
			<a class="sd-share__btn" href="<?php echo esc_url( $target['url'] ); ?>"
				<?php echo 'email' === $key ? '' : 'target="_blank" rel="noopener nofollow"'; ?>>
				<?php echo esc_html( $target['label'] ); ?>
				<?php if ( 'email' !== $key ) : ?>
					<span class="sd-sr-only"><?php esc_html_e( '(otwiera się w nowej karcie)', 'stop-dyskryminacji-core' ); ?></span>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>

		<?php /* Przycisk pojawia się dopiero, gdy JS działa — bez niego byłby martwy. */ ?>
		<button type="button" class="sd-share__btn" data-sd-copy="<?php echo esc_url( $url ); ?>" hidden>
			<span data-sd-copy-label><?php esc_html_e( 'Kopiuj odnośnik', 'stop-dyskryminacji-core' ); ?></span>
		</button>
		<span class="sd-sr-only" id="sd-live" role="status" aria-live="polite"></span>
	</div>
	<?php
	return ob_get_clean();
}

/* =========================================================================
 * Publikacje
 * ====================================================================== */

/**
 * Pliki publikacji dostępne do pobrania.
 *
 * @param int $post_id Identyfikator publikacji.
 * @return array
 */
function sdc_publication_files( $post_id ) {
	$origin = (int) get_post_meta( $post_id, '_sdc_translation_of', true );
	$origin = $origin && 'publikacja' === get_post_type( $origin ) ? $origin : 0;
	$map = array(
		'pdf'  => array( 'meta' => '_sdc_pdf_id', 'label' => __( 'Pobierz PDF', 'stop-dyskryminacji-core' ), 'icon' => 'pdf' ),
		'epub' => array( 'meta' => '_sdc_epub_id', 'label' => __( 'Pobierz EPUB (e-book)', 'stop-dyskryminacji-core' ), 'icon' => 'epub' ),
		'docx' => array( 'meta' => '_sdc_docx_id', 'label' => __( 'Pobierz wersję edytowalną (DOCX)', 'stop-dyskryminacji-core' ), 'icon' => 'doc' ),
	);

	$files = array();

	foreach ( $map as $format => $conf ) {
		$attachment_id = (int) get_post_meta( $post_id, $conf['meta'], true );
		$in_polish = function_exists( 'sdc_current_language' ) && 'pl' !== sdc_current_language() && 'pl' === get_post_meta( $attachment_id, '_sdc_asset_lang', true );
		if ( ! $attachment_id && $origin ) {
			$attachment_id = (int) get_post_meta( $origin, $conf['meta'], true );
			$in_polish = true;
		}
		if ( ! $attachment_id ) {
			continue;
		}
		$url  = wp_get_attachment_url( $attachment_id );
		$path = get_attached_file( $attachment_id );
		if ( ! $url ) {
			continue;
		}
		$files[ $format ] = array(
			'url'   => $url,
			'label' => $conf['label'] . ( $in_polish ? ' ' . __( '(po polsku)', 'stop-dyskryminacji-core' ) : '' ),
			'icon'  => $conf['icon'],
			'size'  => ( $path && file_exists( $path ) ) ? size_format( filesize( $path ), 1 ) : ( get_post_meta( $attachment_id, '_sdc_editorial_bytes', true ) ? size_format( (int) get_post_meta( $attachment_id, '_sdc_editorial_bytes', true ), 1 ) : '' ),
		);
	}

	return $files;
}

/**
 * Przyciski pobierania na stronie publikacji.
 *
 * Atrybuty data-sd-* są odczytywane przez GA4 (zdarzenie „pobranie_publikacji"),
 * które rozlicza wskaźnik 1 000 pobrań.
 *
 * @return string
 */
function sdc_render_publication_downloads() {
	$files = sdc_publication_files( get_the_ID() );

	if ( ! $files ) {
		return '<p class="sd-publication-pending">' . esc_html__( 'Pliki tej publikacji nie są jeszcze udostępnione. Pobieranie będzie możliwe po zatwierdzeniu materiałów do publikacji.', 'stop-dyskryminacji-core' ) . '</p>';
	}

	$title = get_the_title();
	$html  = '<div class="sd-downloads">';

	foreach ( $files as $format => $file ) {
		$html .= sprintf(
			'<a class="sd-download" href="%1$s" download data-sd-download data-sd-format="%2$s" data-sd-title="%3$s">
				<span class="sd-download__icon">%4$s</span>
				<span><span class="sd-download__label">%5$s</span><span class="sd-download__size">%6$s</span></span>
			</a>',
			esc_url( $file['url'] ),
			esc_attr( $format ),
			esc_attr( $title ),
			wp_kses( sdc_icon( $file['icon'], 20 ), sdc_kses_svg() ),
			esc_html( $file['label'] ),
			esc_html( strtoupper( $format ) . ( $file['size'] ? ' · ' . $file['size'] : '' ) )
		);
	}

	return $html . '</div>';
}

/**
 * Lista formatów na karcie publikacji.
 *
 * @return string
 */
function sdc_render_publication_formats() {
	$files = sdc_publication_files( get_the_ID() );

	if ( ! $files ) {
		return '';
	}

	$tags = array();
	foreach ( array_keys( $files ) as $format ) {
		$tags[] = '<span class="sd-tag">' . esc_html( strtoupper( $format ) ) . '</span>';
	}

	return '<p class="sd-card__meta">' . implode( ' ', $tags ) . '</p>';
}

/**
 * Filtry publikacji — zwykły formularz GET, działa bez JavaScriptu.
 *
 * @return string
 */
function sdc_render_publication_filters() {
	$taxonomies = array(
		'typ_publikacji'  => __( 'Rodzaj', 'stop-dyskryminacji-core' ),
		'przeslanka'      => __( 'Przesłanka', 'stop-dyskryminacji-core' ),
		'grupa_odbiorcow' => __( 'Dla kogo', 'stop-dyskryminacji-core' ),
	);

	// Dzięki temu atrybut wyrównania („szeroki", „pełny") ustawiony w edytorze
	// trafia na element formularza — inaczej filtry zostają w wąskiej kolumnie
	// tekstu, podczas gdy siatka publikacji pod nimi jest szeroka.
	$wrapper = get_block_wrapper_attributes( array( 'class' => 'sd-filters' ) );

	ob_start();
	?>
	<form <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wartość z get_block_wrapper_attributes() jest już zabezpieczona. ?> method="get" action="<?php echo esc_url( get_post_type_archive_link( 'publikacja' ) ); ?>">
		<?php
		foreach ( $taxonomies as $taxonomy => $label ) :
			$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true ) );
			if ( is_wp_error( $terms ) || ! $terms ) {
				continue;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtr publicznego wykazu, bez skutków ubocznych.
			$current = isset( $_GET[ $taxonomy ] ) && is_string( $_GET[ $taxonomy ] ) ? sanitize_title( wp_unslash( $_GET[ $taxonomy ] ) ) : '';
			?>
			<div class="sd-field">
				<label for="sdc-f-<?php echo esc_attr( $taxonomy ); ?>"><?php echo esc_html( $label ); ?></label>
				<select class="sd-select" id="sdc-f-<?php echo esc_attr( $taxonomy ); ?>" name="<?php echo esc_attr( $taxonomy ); ?>">
					<option value=""><?php esc_html_e( 'Wszystkie', 'stop-dyskryminacji-core' ); ?></option>
					<?php foreach ( $terms as $term ) : ?>
						<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $current, $term->slug ); ?>>
							<?php echo esc_html( $term->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
		<?php endforeach; ?>

		<div class="sd-field">
			<button type="submit" class="sd-btn sd-btn--secondary"><?php esc_html_e( 'Filtruj', 'stop-dyskryminacji-core' ); ?></button>
		</div>
	</form>
	<?php
	return ob_get_clean();
}

/**
 * Nawigator instytucji pomocowych.
 *
 * Rekomendacja nr 9 z benchmarku: wykaz instytucji, do których można się
 * zwrócić w Wielkopolsce, zamiast odsyłania do ogólnopolskich stron.
 *
 * @return string
 */
function sdc_render_institution_finder() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$current = isset( $_GET['typ_instytucji'] ) ? sanitize_title( wp_unslash( $_GET['typ_instytucji'] ) ) : '';

	$query = new WP_Query(
		array(
			'post_type'      => 'instytucja',
			'posts_per_page' => 50,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
			'tax_query'      => $current ? array( array( 'taxonomy' => 'typ_instytucji', 'field' => 'slug', 'terms' => $current ) ) : array(),
		)
	);

	if ( ! $query->have_posts() ) {
		return current_user_can( 'edit_posts' )
			? '<p>' . esc_html__( 'Dodaj instytucje w panelu: Instytucje pomocowe → Dodaj instytucję.', 'stop-dyskryminacji-core' ) . '</p>'
			: '';
	}

	$terms = get_terms( array( 'taxonomy' => 'typ_instytucji', 'hide_empty' => true ) );

	ob_start();

	if ( ! is_wp_error( $terms ) && $terms ) :
		?>
		<form class="sd-filters" method="get">
			<div class="sd-field">
				<label for="sdc-inst-typ"><?php esc_html_e( 'Rodzaj instytucji', 'stop-dyskryminacji-core' ); ?></label>
				<select class="sd-select" id="sdc-inst-typ" name="typ_instytucji">
					<option value=""><?php esc_html_e( 'Wszystkie', 'stop-dyskryminacji-core' ); ?></option>
					<?php foreach ( $terms as $term ) : ?>
						<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $current, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="sd-field">
				<button type="submit" class="sd-btn sd-btn--secondary"><?php esc_html_e( 'Pokaż', 'stop-dyskryminacji-core' ); ?></button>
			</div>
		</form>
		<?php
	endif;

	echo '<p class="sd-results-count">' . esc_html( sprintf( _n( 'Znaleziono %d instytucję', 'Znaleziono %d instytucji', $query->found_posts, 'stop-dyskryminacji-core' ), $query->found_posts ) ) . '</p>';
	echo '<div class="sd-grid sd-grid--2">';

	while ( $query->have_posts() ) {
		$query->the_post();
		$id = get_the_ID();
		?>
		<div class="sd-institution">
			<?php
			/*
			 * Poziom H2, nie H3. Karty instytucji są sekcjami bezpośrednio pod
			 * tytułem strony (H1) — wstawienie H3 tworzyłoby przeskok poziomów,
			 * czyli uwagę w audycie dostępności (WCAG 1.3.1).
			 */
			?>
			<h2><?php the_title(); ?></h2>
			<?php if ( get_the_content() ) : ?>
				<div><?php echo wp_kses_post( wpautop( get_the_content() ) ); ?></div>
			<?php endif; ?>
			<dl>
				<?php
				$rows = array(
					'_sdc_scope'   => __( 'Zakres', 'stop-dyskryminacji-core' ),
					'_sdc_address' => __( 'Adres', 'stop-dyskryminacji-core' ),
					'_sdc_hours'   => __( 'Godziny', 'stop-dyskryminacji-core' ),
				);
				foreach ( $rows as $meta => $label ) :
					$value = (string) get_post_meta( $id, $meta, true );
					if ( '' === $value ) {
						continue;
					}
					?>
					<dt><?php echo esc_html( $label ); ?></dt>
					<dd><?php echo esc_html( $value ); ?></dd>
				<?php endforeach; ?>

				<?php $phone = (string) get_post_meta( $id, '_sdc_phone', true ); ?>
				<?php if ( $phone ) : ?>
					<dt><?php esc_html_e( 'Telefon', 'stop-dyskryminacji-core' ); ?></dt>
					<dd><a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></dd>
				<?php endif; ?>

				<?php $email = (string) get_post_meta( $id, '_sdc_email', true ); ?>
				<?php if ( $email ) : ?>
					<dt><?php esc_html_e( 'E-mail', 'stop-dyskryminacji-core' ); ?></dt>
					<dd><a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a></dd>
				<?php endif; ?>

				<?php $www = (string) get_post_meta( $id, '_sdc_www', true ); ?>
				<?php if ( $www ) : ?>
					<dt><?php esc_html_e( 'Strona', 'stop-dyskryminacji-core' ); ?></dt>
					<dd><a href="<?php echo esc_url( $www ); ?>" class="sd-link-external" target="_blank" rel="noopener"><?php echo esc_html( wp_parse_url( $www, PHP_URL_HOST ) ); ?><span class="sd-sr-only"> <?php esc_html_e( '(otwiera się w nowej karcie)', 'stop-dyskryminacji-core' ); ?></span></a></dd>
				<?php endif; ?>
			</dl>
		</div>
		<?php
	}

	echo '</div>';
	wp_reset_postdata();

	return ob_get_clean();
}

/**
 * Mapa strony.
 *
 * WCAG 2.4.5 (wiele sposobów): do każdej strony serwisu musi dać się dotrzeć
 * więcej niż jedną drogą. Pierwszą jest menu, drugą — ta mapa, z wyszukiwarką.
 * Spis buduje się sam z opublikowanych treści, więc nie trzeba go aktualizować.
 *
 * Pomijamy sekcje UA i EN (mają własne menu i stopki ze wszystkimi stronami)
 * oraz strony podziękowania po wysłaniu formularza.
 *
 * @return string
 */
function sdc_render_sitemap() {
	if ( 'pl' !== sdc_current_language() ) {
		$root = get_page_by_path( sdc_multilingual_prefix( sdc_current_language() ) );
		$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => -1,
			'meta_key' => '_sdc_page_lang', 'meta_value' => sdc_current_language(), 'orderby' => 'title', 'order' => 'ASC' ) );
		$html = render_block( array( 'blockName' => 'core/search', 'attrs' => array( 'label' => __( 'Szukaj w serwisie', 'stop-dyskryminacji-core' ), 'buttonText' => __( 'Szukaj', 'stop-dyskryminacji-core' ), 'buttonPosition' => 'button-outside' ), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
		$html .= '<h2>' . esc_html__( 'Strony', 'stop-dyskryminacji-core' ) . '</h2><ul>';
		foreach ( $pages as $page ) {
			if ( '/dziekujemy/' === get_post_meta( $page->ID, '_sdc_source_path', true ) || ( $root && $page->ID === $root->ID ) ) { continue; }
			$html .= '<li><a href="' . esc_url( get_permalink( $page ) ) . '">' . esc_html( $page->post_title ) . '</a></li>';
		}
		return $html . '</ul>';
	}
	$exclude = array();

	if ( function_exists( 'sdc_language_prefixes' ) ) {
		foreach ( sdc_language_prefixes() as $prefix ) {
			$root = '' !== $prefix ? get_page_by_path( $prefix ) : null;
			if ( $root ) {
				$exclude[] = (int) $root->ID;
			}
		}
	}
	$thanks = get_page_by_path( 'dziekujemy' );
	if ( $thanks ) {
		$exclude[] = (int) $thanks->ID;
	}

	ob_start();
	?>
	<div class="sd-sitemap">
		<?php
		// Blok wyszukiwarki z rdzenia — ten sam co na stronie 404, z etykietą widoczną nad polem.
		echo render_block( // phpcs:ignore WordPress.Security.EscapeOutput -- znaczniki bloku rdzenia
			array(
				'blockName'    => 'core/search',
				'attrs'        => array(
					'label'          => __( 'Szukaj w serwisie', 'stop-dyskryminacji-core' ),
					'buttonText'     => __( 'Szukaj', 'stop-dyskryminacji-core' ),
					'buttonPosition' => 'button-outside',
				),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
		?>

		<h2><?php esc_html_e( 'Strony', 'stop-dyskryminacji-core' ); ?></h2>
		<ul>
			<?php
			wp_list_pages(
				array(
					'title_li'     => '',
					'exclude_tree' => $exclude,
					'sort_column'  => 'menu_order, post_title',
				)
			);
			?>
		</ul>

		<?php
		$groups = array(
			'publikacja' => __( 'Publikacje', 'stop-dyskryminacji-core' ),
			'praktyka'   => __( 'Dobre praktyki', 'stop-dyskryminacji-core' ),
		);
		foreach ( $groups as $type => $label ) :
			$items = get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
			if ( ! $items ) {
				continue;
			}
			?>
			<h2><?php echo esc_html( $label ); ?></h2>
			<ul>
				<?php foreach ( $items as $item ) : ?>
					<li><a href="<?php echo esc_url( get_permalink( $item ) ); ?>"><?php echo esc_html( get_the_title( $item ) ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		<?php endforeach; ?>

		<?php
		$categories = get_categories( array( 'hide_empty' => true ) );
		if ( $categories ) :
			?>
			<h2><?php esc_html_e( 'Baza wiedzy', 'stop-dyskryminacji-core' ); ?></h2>
			<?php foreach ( $categories as $category ) : ?>
				<h3><a href="<?php echo esc_url( get_category_link( $category ) ); ?>"><?php echo esc_html( $category->name ); ?></a></h3>
				<ul>
					<?php foreach ( get_posts( array( 'category' => $category->term_id, 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ) as $article ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $article ) ); ?>"><?php echo esc_html( get_the_title( $article ) ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
