<?php
/** Reviewed page translations, installed once when an administrator opens WordPress. */
defined( 'ABSPATH' ) || exit;

function sdc_multilingual_data() {
	static $data = null;
	if ( null === $data ) {
		$data = json_decode( file_get_contents( SDC_PATH . 'data/multilingual-pages.json' ), true );
	}
	return $data;
}

function sdc_content_dictionary( $lang ) {
	static $dictionary = null;
	if ( null === $dictionary ) {
		$dictionary = json_decode( file_get_contents( SDC_PATH . 'data/content-translations.json' ), true );
	}
	return $dictionary[ $lang ] ?? array();
}

function sdc_content_translate( $text, $lang = null ) {
	$lang = $lang ?? sdc_current_language();
	$dictionary = sdc_content_dictionary( $lang );
	$key = trim( html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	if ( ! isset( $dictionary[ $key ] ) ) {
		return $text;
	}
	preg_match( '/^(\s*)(.*?)(\s*)$/su', $text, $parts );
	return $parts[1] . $dictionary[ $key ] . $parts[3];
}

/** Include interactive labels and JavaScript messages missing from older MO files. */
function sdc_content_gettext( $translation, $text, $domain ) {
	if ( ! in_array( $domain, array( 'stop-dyskryminacji-core', 'stop-dyskryminacji' ), true ) || 'pl' === sdc_current_language() ) { return $translation; }
	$mapped = sdc_content_translate( $text );
	return $mapped !== $text ? $mapped : $translation;
}
add_filter( 'gettext', 'sdc_content_gettext', 20, 3 );

/** Shared media keep language-specific alternatives without changing the Polish attachment. */
function sdc_localized_image_attributes( $attributes, $attachment = null ) {
	$lang = sdc_current_language();
	if ( 'pl' === $lang || is_admin() || empty( $attributes['alt'] ) ) { return $attributes; }
	$translated = sdc_content_translate( $attributes['alt'], $lang );
	if ( $translated !== $attributes['alt'] ) {
		$attributes['alt'] = $translated;
		$attributes['lang'] = $lang;
	}
	if ( $attachment && preg_match( '/^P[1-4]_COVER$/D', get_post_meta( $attachment->ID, '_sdc_editorial_asset_key', true ) ) ) {
		$parent = (int) get_post_field( 'post_parent', $attachment->ID ); $page = sdc_translation_id( $parent, $lang );
		if ( $page ) { $attributes['alt'] = ( 'en' === $lang ? 'Cover of the Polish-language publication: ' : 'Обкладинка польськомовної публікації: ' ) . get_the_title( $page ); $attributes['lang'] = $lang; }
	}
	return $attributes;
}
add_filter( 'wp_get_attachment_image_attributes', 'sdc_localized_image_attributes', 20, 2 );

/** Find the source without relying on database IDs from the Local installation. */
function sdc_multilingual_origin( $item ) {
	if ( '/' === $item['source'] ) {
		return (int) get_option( 'page_on_front' );
	}
	if ( 'archive' === $item['source_type'] ) {
		return 0;
	}
	$path = trim( $item['source'], '/' );
	if ( 'page' !== $item['source_type'] ) {
		$path = basename( $path );
	}
	$post = get_page_by_path( $path, OBJECT, $item['source_type'] );
	return $post ? (int) $post->ID : 0;
}

function sdc_multilingual_prefix( $lang ) {
	$option = 'lang_url_' . $lang;
	$value = sdc_option( $option, 'uk' === $lang ? '/ua/' : '/en/' );
	$path = trim( (string) wp_parse_url( $value, PHP_URL_PATH ), '/' );
	$home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
	if ( $home && 0 === strpos( $path . '/', $home . '/' ) ) {
		$path = trim( substr( $path, strlen( $home ) ), '/' );
	}
	return $path;
}

/** One-time update; the completion option preserves later editorial changes. */
function sdc_install_multilingual_content() {
	$data = sdc_multilingual_data();
	if ( get_option( 'sdc_multilingual_content_version' ) === $data['version'] ) {
		return true;
	}
	// Do not manufacture a second site's content on an unrelated installation.
	if ( ! get_page_by_path( 'czy-to-dyskryminacja' ) || ! get_page_by_path( 'dla-pracownika' ) ) {
		return new WP_Error( 'sdc_missing_polish_pages', 'Najpierw należy zaimportować polskie strony projektu.' );
	}
	// An atomic option prevents two administrator requests creating duplicates.
	$lock = (int) get_option( 'sdc_multilingual_content_lock' );
	if ( $lock && time() - $lock < 300 ) {
		return new WP_Error( 'sdc_translation_busy', 'Trwa tworzenie wersji językowych.' );
	}
	if ( $lock ) {
		delete_option( 'sdc_multilingual_content_lock' );
	}
	if ( ! add_option( 'sdc_multilingual_content_lock', time(), '', false ) ) {
		return new WP_Error( 'sdc_translation_busy', 'Trwa tworzenie wersji językowych.' );
	}
	try {
		foreach ( array( 'en' => '/en/', 'uk' => '/ua/' ) as $lang => $default ) {
			if ( ! sdc_option( 'lang_url_' . $lang ) ) {
				update_option( 'sdc_lang_url_' . $lang, $default );
			}
			$pages = $data['pages'];
			// The language home must precede every child, regardless of sort stability.
			usort( $pages, function ( $a, $b ) use ( $lang ) {
				$depth = function ( $item ) use ( $lang ) { return '' === $item['translations'][ $lang ]['path'] ? -1 : substr_count( $item['translations'][ $lang ]['path'], '/' ); };
				return $depth( $a ) <=> $depth( $b );
			} );
			foreach ( $pages as $item ) {
				$origin = sdc_multilingual_origin( $item );
				if ( ! $origin && 'archive' !== $item['source_type'] ) {
					return new WP_Error( 'sdc_missing_source', 'Brak polskiej strony: ' . $item['source'] );
				}
				$translation = $item['translations'][ $lang ];
				$path = trim( sdc_multilingual_prefix( $lang ) . '/' . $translation['path'], '/' );
				$existing = $origin ? sdc_translation_id( $origin, $lang ) : 0;
				$at_path = get_page_by_path( $path );
				if ( ! $existing && $at_path ) {
					$linked_origin = (int) get_post_meta( $at_path->ID, '_sdc_translation_of', true );
					if ( $linked_origin && $linked_origin !== $origin ) {
						return new WP_Error( 'sdc_translation_conflict', 'Adres jest przypisany do innego tłumaczenia: ' . $path );
					}
					$existing = (int) $at_path->ID;
				}
				$parent_path = dirname( $path );
				$parent = '.' === $parent_path ? null : get_page_by_path( $parent_path );
				if ( '.' !== $parent_path && ! $parent ) {
					return new WP_Error( 'sdc_translation_parent', 'Brak strony nadrzędnej: ' . $parent_path );
				}
				$post = array(
					'post_type' => 'page', 'post_status' => 'publish',
					'post_title' => $translation['title'], 'post_content' => $translation['content'],
					'post_excerpt' => $translation['excerpt'], 'post_name' => basename( $path ),
					'post_parent' => $parent ? $parent->ID : 0,
					'comment_status' => 'closed', 'ping_status' => 'closed',
				);
				if ( $existing ) {
					wp_save_post_revision( $existing );
					$post['ID'] = $existing;
				}
				if ( $origin ) {
					$original = get_post( $origin );
					$post['post_date'] = $original->post_date;
					$post['post_date_gmt'] = $original->post_date_gmt;
					$post['menu_order'] = $original->menu_order;
				}
				$id = $existing ? wp_update_post( wp_slash( $post ), true ) : wp_insert_post( wp_slash( $post ), true );
				if ( is_wp_error( $id ) ) {
					return $id;
				}
				update_post_meta( $id, '_sdc_page_lang', $lang );
				update_post_meta( $id, '_sdc_translation_of', $origin );
				update_post_meta( $id, '_sdc_source_path', $item['source'] );
				update_post_meta( $id, '_sdc_source_type', $item['source_type'] );
				update_post_meta( $id, '_wp_page_template', $item['template'] ?: 'default' );
				if ( $origin && get_post_thumbnail_id( $origin ) ) {
					set_post_thumbnail( $id, get_post_thumbnail_id( $origin ) );
				}
			}
			$links = array();
			$polish_nav = sdc_navigation_id( 'pl' );
			if ( ! $polish_nav ) {
				return new WP_Error( 'sdc_missing_navigation', 'Brak polskiego menu nawigacji.' );
			}
			foreach ( parse_blocks( get_post_field( 'post_content', $polish_nav ) ) as $block ) {
				$links[] = sdc_translate_navigation_block( $block, $lang );
			}
			$nav = sdc_navigation_id( $lang );
			$nav_post = wp_slash( array(
				'ID' => $nav, 'post_type' => 'wp_navigation', 'post_status' => 'publish',
				'post_title' => 'en' === $lang ? 'Menu (EN)' : 'Меню (UA)',
				'post_content' => serialize_blocks( $links ),
			) );
			$nav_id = $nav ? wp_update_post( $nav_post, true ) : wp_insert_post( $nav_post, true );
			if ( is_wp_error( $nav_id ) ) { return $nav_id; }
			update_post_meta( $nav_id, '_sdc_nav_lang', $lang );
		}
		update_option( 'sdc_multilingual_content_version', $data['version'], false );
		return true;
	} finally {
		delete_option( 'sdc_multilingual_content_lock' );
	}
}

function sdc_multilingual_admin_update() {
	if ( current_user_can( 'manage_options' ) ) {
		$result = sdc_install_multilingual_content();
		if ( is_wp_error( $result ) ) {
			add_action( 'admin_notices', function () use ( $result ) {
				echo '<div class="notice notice-warning"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
			} );
		}
	}
}
add_action( 'admin_init', 'sdc_multilingual_admin_update' );

function sdc_translate_navigation_block( $block, $lang ) {
	foreach ( array( 'label', 'description' ) as $key ) {
		if ( isset( $block['attrs'][ $key ] ) ) {
			$block['attrs'][ $key ] = sdc_content_translate( $block['attrs'][ $key ], $lang );
		}
	}
	if ( isset( $block['attrs']['url'] ) ) {
		$block['attrs']['url'] = sdc_localized_content_url( $block['attrs']['url'], $lang );
	}
	foreach ( $block['innerBlocks'] as &$child ) {
		$child = sdc_translate_navigation_block( $child, $lang );
	}
	return $block;
}

/** Localise known published links and retain query strings and fragments. */
function sdc_localized_content_url( $url, $lang = null ) {
	$lang = $lang ?? sdc_current_language();
	if ( 'pl' === $lang || '' === $url || '#' === $url[0] ) { return $url; }
	$parts = wp_parse_url( html_entity_decode( $url, ENT_QUOTES, 'UTF-8' ) );
	if ( false === $parts || ( ! empty( $parts['host'] ) && $parts['host'] !== wp_parse_url( home_url(), PHP_URL_HOST ) )
		|| ( ! empty( $parts['scheme'] ) && ! in_array( $parts['scheme'], array( 'http', 'https' ), true ) ) ) {
		return $url;
	}
	$path = '/' . trim( $parts['path'] ?? '', '/' ) . '/';
	if ( '//' === $path ) { $path = '/'; }
	$home_path = rtrim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
	if ( $home_path && 0 === strpos( $path, $home_path . '/' ) ) { $path = substr( $path, strlen( $home_path ) ); }
	$data = sdc_multilingual_data();
	$target = $data['paths'][ $lang ][ $path ] ?? '';
	if ( ! $target ) { return $url; }
	$default_prefix = 'uk' === $lang ? '/ua/' : '/en/';
	$target = '/' . sdc_multilingual_prefix( $lang ) . '/' . substr( $target, strlen( $default_prefix ) );
	$page = get_page_by_path( trim( $target, '/' ) );
	if ( ! $page || 'publish' !== $page->post_status ) { return $url; }
	return get_permalink( $page ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' )
		. ( isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '' );
}

/** Translate remaining template labels and dynamic institution text safely. */
function sdc_localize_rendered_block( $html, $block ) {
	$lang = sdc_current_language();
	if ( 'pl' === $lang || is_admin() || '' === $html ) { return $html; }
	// Never replace JavaScript, CSS, Gutenberg comments or tag markup as text.
	$html = preg_replace_callback( '#<script\b[^>]*>.*?</script>|<style\b[^>]*>.*?</style>|<!--.*?-->|<[^>]+>|([^<>]+)#si', function ( $match ) use ( $lang ) {
		return isset( $match[1] ) ? esc_html( sdc_content_translate( html_entity_decode( $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ), $lang ) ) : $match[0];
	}, $html );
	$processor = new WP_HTML_Tag_Processor( $html );
	while ( $processor->next_tag() ) {
		foreach ( array( 'aria-label', 'title', 'alt', 'placeholder' ) as $attribute ) {
			$value = $processor->get_attribute( $attribute );
			if ( is_string( $value ) ) { $processor->set_attribute( $attribute, sdc_content_translate( $value, $lang ) ); }
		}
		if ( $processor->has_class( 'sd-lang__item' ) ) { continue; }
		foreach ( array( 'href', 'action' ) as $attribute ) {
			$value = $processor->get_attribute( $attribute );
			if ( is_string( $value ) ) {
				$localized = sdc_localized_content_url( $value, $lang );
				if ( $value !== $localized ) { $processor->set_attribute( $attribute, $localized ); }
			}
		}
	}
	return $processor->get_updated_html();
}
add_filter( 'render_block', 'sdc_localize_rendered_block', 20, 2 );

/** Query blocks show translated cards, keeping Polish lists unchanged. */
function sdc_translated_query_vars( $args, $block ) {
	$lang = sdc_current_language();
	$query = $block->context['query'] ?? array();
	$type = $query['sdcSourceType'] ?? ( $query['postType'] ?? '' );
	if ( 'pl' === $lang || ! in_array( $type, array( 'post', 'publikacja', 'praktyka' ), true ) ) { return $args; }
	$args['post_type'] = 'page';
	$args['meta_query'] = array(
		array( 'key' => '_sdc_page_lang', 'value' => $lang ),
		array( 'key' => '_sdc_source_type', 'value' => $type ),
	);
	$tax_query = array();
	foreach ( array( 'typ_publikacji', 'przeslanka', 'grupa_odbiorcow', 'category' ) as $taxonomy ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public, read-only filters.
		if ( ! empty( $_GET[ $taxonomy ] ) && is_string( $_GET[ $taxonomy ] ) ) {
			$tax_query[] = array( 'taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => sanitize_title( wp_unslash( $_GET[ $taxonomy ] ) ) );
		}
	}
	if ( $tax_query ) {
		$ids = get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'tax_query' => $tax_query ) );
		$args['meta_query'][] = array( 'key' => '_sdc_translation_of', 'value' => $ids ?: array( 0 ), 'compare' => 'IN', 'type' => 'NUMERIC' );
	}
	unset( $args['tax_query'] );
	return $args;
}
add_filter( 'query_loop_block_query_vars', 'sdc_translated_query_vars', 10, 2 );

function sdc_translated_post_terms( $html, $parsed, $instance ) {
	$lang = sdc_current_language();
	$id = (int) ( $instance->context['postId'] ?? 0 );
	$origin = (int) get_post_meta( $id, '_sdc_translation_of', true );
	if ( 'pl' === $lang || ! $origin ) { return $html; }
	$taxonomy = $parsed['attrs']['term'] ?? '';
	$terms = $taxonomy ? get_the_terms( $origin, $taxonomy ) : false;
	if ( ! is_array( $terms ) ) { return ''; }
	$links = array();
	$archive = 'publikacja' === get_post_type( $origin ) ? '/publikacje/' : '/aktualnosci/';
	foreach ( $terms as $term ) {
		$links[] = '<a href="' . esc_url( add_query_arg( $taxonomy, $term->slug, sdc_localized_content_url( home_url( $archive ), $lang ) ) ) . '">' . esc_html( sdc_content_translate( $term->name, $lang ) ) . '</a>';
	}
	$class = $parsed['attrs']['className'] ?? '';
	return '<div class="wp-block-post-terms ' . esc_attr( $class ) . '">' . implode( esc_html( $parsed['attrs']['separator'] ?? ', ' ), $links ) . '</div>';
}
add_filter( 'render_block_core/post-terms', 'sdc_translated_post_terms', 10, 3 );

/** A translated archive has no Polish page ID; switch through its source path. */
function sdc_translated_archive_url( $fallback, $lang ) {
	$path = get_post_meta( get_queried_object_id(), '_sdc_source_path', true );
	if ( is_post_type_archive( 'publikacja' ) ) { $path = '/publikacje/'; }
	if ( is_post_type_archive( 'praktyka' ) ) { $path = '/dobre-praktyki/'; }
	if ( ! $path || ! in_array( $path, array( '/publikacje/', '/dobre-praktyki/' ), true ) ) { return $fallback; }
	$source = home_url( $path );
	return 'pl' === $lang ? $source : sdc_localized_content_url( $source, $lang );
}

/** A search submitted to /en/ or /ua/ must not remain a singular page request. */
function sdc_language_search_request( $vars ) {
	if ( is_admin() || ! isset( $vars['s'] ) || ! is_string( $vars['s'] ) || 'pl' === sdc_current_language() ) { return $vars; }
	global $wp_rewrite;
	$prefix = sdc_multilingual_prefix( sdc_current_language() );
	$pagination_base = isset( $wp_rewrite->pagination_base ) ? $wp_rewrite->pagination_base : 'page';
	$pattern = '#^' . preg_quote( $prefix, '#' ) . '(?:/' . preg_quote( $pagination_base, '#' ) . '/([0-9]+))?$#';
	if ( preg_match( $pattern, trim( (string) ( $vars['pagename'] ?? '' ), '/' ), $match ) ) {
		if ( ! empty( $match[1] ) ) { $vars['paged'] = (int) $match[1]; }
		elseif ( ! empty( $vars['page'] ) ) { $vars['paged'] = (int) $vars['page']; }
		unset( $vars['pagename'], $vars['page_id'], $vars['page'] );
	}
	return $vars;
}
add_filter( 'request', 'sdc_language_search_request' );

function sdc_translate_search_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) { return; }
	$lang = sdc_current_language();
	// Search the current public language section; never include editorial attachments.
	$query->set( 'post_status', 'publish' );
	$query->set( 'post_type', 'pl' === $lang ? array( 'post', 'page', 'publikacja', 'praktyka', 'instytucja' ) : 'page' );
	$language = 'pl' === $lang
		? array( 'relation' => 'OR', array( 'key' => '_sdc_page_lang', 'compare' => 'NOT EXISTS' ), array( 'key' => '_sdc_page_lang', 'value' => 'pl' ) )
		: array( 'key' => '_sdc_page_lang', 'value' => $lang );
	$existing = $query->get( 'meta_query' );
	$query->set( 'meta_query', $existing ? array( 'relation' => 'AND', $existing, $language ) : array( $language ) );
}
add_action( 'pre_get_posts', 'sdc_translate_search_query' );

/** The Polish publication block uses its own query, independent of the archive. */
function sdc_filter_publication_query( $args, $block ) {
	$type = $block->context['query']['postType'] ?? '';
	if ( 'pl' !== sdc_current_language() || 'publikacja' !== $type ) { return $args; }
	$filters = array();
	foreach ( array( 'typ_publikacji', 'przeslanka', 'grupa_odbiorcow' ) as $taxonomy ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only filters.
		if ( ! empty( $_GET[ $taxonomy ] ) && is_string( $_GET[ $taxonomy ] ) ) {
			$filters[] = array( 'taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => sanitize_title( wp_unslash( $_GET[ $taxonomy ] ) ) );
		}
	}
	if ( $filters ) {
		$args['tax_query'] = empty( $args['tax_query'] ) ? $filters : array( 'relation' => 'AND', $args['tax_query'], $filters );
	}
	return $args;
}
add_filter( 'query_loop_block_query_vars', 'sdc_filter_publication_query', 20, 2 );
