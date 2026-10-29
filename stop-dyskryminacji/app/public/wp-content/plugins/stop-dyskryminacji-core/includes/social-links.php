<?php
/** Plain profile links under articles; no third-party embeds. */
defined( 'ABSPATH' ) || exit;
function sdc_social_profiles() {
	return array( 'Facebook' => 'https://www.facebook.com/PROUkraina.org', 'LinkedIn' => 'https://www.linkedin.com/company/stowarzyszenie-pro-ukraina/', 'Instagram' => 'https://www.instagram.com/stowarzyszenie_proukraina/' );
}
function sdc_article_social_links( $content ) {
	if ( is_admin() || is_feed() || doing_filter( 'get_the_excerpt' ) || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() || false !== strpos( $content, 'sdc-social-profiles' ) ) { return $content; }
	$lang = function_exists( 'sdc_current_language' ) ? sdc_current_language() : 'pl';
	$titles = array( 'pl' => 'Obserwuj Pro Ukrainę', 'en' => 'Follow Pro Ukraina', 'uk' => 'Стежте за Pro Ukraina' );
	$html = '<section class="sdc-social-profiles" aria-labelledby="sdc-social-title"><h2 id="sdc-social-title">' . esc_html( $titles[ $lang ] ?? $titles['pl'] ) . '</h2><ul>';
	foreach ( sdc_social_profiles() as $name => $url ) { $html .= '<li><a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '<span class="screen-reader-text"> — Pro Ukraina</span></a></li>'; }
	return $content . $html . '</ul></section>';
}
add_filter( 'the_content', 'sdc_article_social_links', 60 );
function sdc_social_links_style() {
	if ( is_singular( 'post' ) ) { wp_enqueue_style( 'sdc-social-links', SDC_URL . 'assets/css/social-links.css', array(), SDC_VERSION ); }
}
add_action( 'wp_enqueue_scripts', 'sdc_social_links_style' );
