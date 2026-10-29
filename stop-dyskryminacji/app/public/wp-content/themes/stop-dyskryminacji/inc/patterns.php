<?php
/**
 * Kategorie wzorców bloków.
 *
 * Wzorce z katalogu /patterns rejestruje WordPress automatycznie.
 * Tutaj powstają tylko kategorie, w których redaktor je znajdzie
 * pod przyciskiem „+ Dodaj blok → Wzorce".
 *
 * @package StopDyskryminacji
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rejestracja kategorii wzorców.
 */
function sd_register_pattern_categories() {
	$categories = array(
		'sd-strony'     => __( 'STOP DYSKRYMINACJI — gotowe strony', 'stop-dyskryminacji' ),
		'sd-sekcje'     => __( 'STOP DYSKRYMINACJI — sekcje', 'stop-dyskryminacji' ),
		'sd-tresc'      => __( 'STOP DYSKRYMINACJI — elementy treści', 'stop-dyskryminacji' ),
		'sd-oznakowanie'=> __( 'STOP DYSKRYMINACJI — oznakowanie projektu', 'stop-dyskryminacji' ),
	);

	foreach ( $categories as $slug => $label ) {
		if ( ! WP_Block_Pattern_Categories_Registry::get_instance()->is_registered( $slug ) ) {
			register_block_pattern_category( $slug, array( 'label' => $label ) );
		}
	}
}
add_action( 'init', 'sd_register_pattern_categories', 9 );
