<?php
/**
 * Motyw „STOP DYSKRYMINACJI" — bootstrap.
 *
 * Projekt nr FEWP.06.10-IP.01-0136/25, dofinansowany przez Unię Europejską
 * ze środków EFS+ w ramach programu Fundusze Europejskie dla Wielkopolski 2021–2027.
 *
 * Podział odpowiedzialności:
 *   - motyw  (ten katalog)          → wyłącznie prezentacja: tokeny, CSS, szablony, wzorce;
 *   - wtyczka stop-dyskryminacji-core → funkcje: typy treści, moduł zgłoszeniowy,
 *     bloki dynamiczne, GA4, zgoda cookie, oznakowanie FE/RP/UE/herb.
 *
 * Dzięki temu oznakowanie i moduł zgłoszeniowy przetrwają zmianę motywu.
 *
 * @package StopDyskryminacji
 */

defined( 'ABSPATH' ) || exit;

define( 'SD_THEME_VERSION', wp_get_theme()->get( 'Version' ) );
define( 'SD_THEME_DIR', get_stylesheet_directory() );
define( 'SD_THEME_URI', get_stylesheet_directory_uri() );

require_once SD_THEME_DIR . '/inc/setup.php';
require_once SD_THEME_DIR . '/inc/assets.php';
require_once SD_THEME_DIR . '/inc/patterns.php';
require_once SD_THEME_DIR . '/inc/accessibility.php';
require_once SD_THEME_DIR . '/inc/performance.php';
