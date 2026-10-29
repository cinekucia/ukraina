<?php
/**
 * Plugin Name:       STOP DYSKRYMINACJI — moduł podstawowy
 * Plugin URI:        https://stopdyskryminacji.pl
 * Description:       Funkcje platformy: typy treści, moduł zgłoszeniowy z szyfrowaniem, obowiązkowe oznakowanie FE/RP/UE/herb, zgoda na pliki cookie, GA4 z trzema zdarzeniami, narzędzia dla pracodawców.
 * Version:           1.0.23
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Filip Kucia
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       stop-dyskryminacji-core
 * Domain Path:       /languages
 *
 * Projekt nr FEWP.06.10-IP.01-0136/25, dofinansowany przez Unię Europejską
 * ze środków EFS+ w ramach programu Fundusze Europejskie dla Wielkopolski 2021–2027.
 *
 * DLACZEGO TO JEST WTYCZKA, A NIE CZĘŚĆ MOTYWU
 * --------------------------------------------
 * Trzy rzeczy muszą przetrwać zmianę albo awarię motywu:
 *   1. oznakowanie FE/RP/UE/herb — jego brak to pomniejszenie dofinansowania
 *      (Załącznik nr 10 rozdz. 10);
 *   2. moduł zgłoszeniowy razem z kluczem szyfrowania i rejestrem dostępu;
 *   3. typy treści z zaimportowanymi publikacjami i artykułami.
 * Dlatego całość funkcji jest tutaj, a motyw odpowiada wyłącznie za wygląd.
 *
 * @package StopDyskryminacjiCore
 */

defined( 'ABSPATH' ) || exit;

define( 'SDC_VERSION', '1.0.23' );
define( 'SDC_FILE', __FILE__ );
define( 'SDC_PATH', plugin_dir_path( __FILE__ ) );
define( 'SDC_URL', plugin_dir_url( __FILE__ ) );

/**
 * Nazwa tabeli rejestru czynności (art. 30 ust. 2 RODO oraz § 3 umowy powierzenia).
 *
 * @return string
 */
function sdc_audit_table() {
	global $wpdb;
	return $wpdb->prefix . 'sdc_audit';
}

require_once SDC_PATH . 'includes/helpers.php';
require_once SDC_PATH . 'includes/privacy-2026-10-26.php';
require_once SDC_PATH . 'includes/help-updates-2026-10-27.php';
require_once SDC_PATH . 'includes/epub-2026-10-26.php';
require_once SDC_PATH . 'includes/content-types.php';
require_once SDC_PATH . 'includes/branding.php';
require_once SDC_PATH . 'includes/languages.php';
require_once SDC_PATH . 'includes/multilingual-content.php';
require_once SDC_PATH . 'includes/consent.php';
require_once SDC_PATH . 'includes/analytics.php';
require_once SDC_PATH . 'includes/statistics.php';
require_once SDC_PATH . 'includes/social-links.php';
require_once SDC_PATH . 'includes/crypto.php';
require_once SDC_PATH . 'includes/audit.php';
require_once SDC_PATH . 'includes/form-state.php';
require_once SDC_PATH . 'includes/submissions.php';
require_once SDC_PATH . 'includes/submissions-admin.php';
require_once SDC_PATH . 'includes/self-assessment.php';
require_once SDC_PATH . 'includes/blocks.php';
require_once SDC_PATH . 'includes/publication-files.php';
require_once SDC_PATH . 'includes/editorial-import.php';
require_once SDC_PATH . 'includes/editorial-release.php';
require_once SDC_PATH . 'includes/editorial-corrections.php';
require_once SDC_PATH . 'includes/seo.php';
require_once SDC_PATH . 'includes/article-authors.php';
require_once SDC_PATH . 'includes/news.php';
require_once SDC_PATH . 'includes/accessibility-fixes.php';
require_once SDC_PATH . 'includes/audit-2026-10-22.php';
require_once SDC_PATH . 'includes/a11y-toolbar.php';
require_once SDC_PATH . 'includes/settings.php';
require_once SDC_PATH . 'includes/site-health.php';

/**
 * Aktywacja: tabela rejestru, harmonogramy, przepisanie reguł adresów.
 */
function sdc_activate() {
	sdc_register_post_types();
	sdc_register_taxonomies();
	sdc_create_audit_table();

	if ( ! wp_next_scheduled( 'sdc_daily_maintenance' ) ) {
		// 3:15 w nocy — poza szczytem ruchu.
		wp_schedule_event( strtotime( 'tomorrow 03:15' ), 'daily', 'sdc_daily_maintenance' );
	}

	// Klucz szyfrowania musi trafić do wp-config.php, nie do bazy danych —
	// inaczej zrzut bazy zawiera i szyfrogram, i klucz. Tutaj tylko podpowiedź.
	if ( ! sdc_encryption_key_configured() && ! get_option( 'sdc_suggested_key' ) ) {
		add_option( 'sdc_suggested_key', sdc_generate_key_base64() );
	}

	add_option( 'sdc_retention_days', 90 );
	add_option( 'sdc_response_days', 5 );

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'sdc_activate' );

/**
 * Dezaktywacja: usuwamy harmonogramy, zostawiamy dane i rejestr.
 */
function sdc_deactivate() {
	wp_clear_scheduled_hook( 'sdc_daily_maintenance' );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'sdc_deactivate' );

/**
 * Tłumaczenia.
 */
function sdc_load_textdomain() {
	load_plugin_textdomain( 'stop-dyskryminacji-core', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'sdc_load_textdomain' );
