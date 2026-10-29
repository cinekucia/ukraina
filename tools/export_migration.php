<?php
/**
 * Eksport serwisu z Local do przeniesienia na zwykły hosting WordPress.
 *
 * Lokalna kopia działa jako sieć WordPress (multisite) z jedną stroną, a hosting
 * produkcyjny to zwykła, pojedyncza instalacja. Wtyczki migracyjne w wersjach
 * darmowych nie przenoszą multisite, dlatego ten skrypt przygotowuje dwie rzeczy:
 *
 *   baza.sql     — treść i ustawienia serwisu do zaimportowania przez phpMyAdmin
 *                  do bazy świeżo zainstalowanego WordPressa;
 *   uploads.zip  — obrazy z biblioteki mediów (wp-content/uploads).
 *
 * Co trafia do eksportu: strony, wpisy, publikacje, instytucje, menu (także
 * UA i EN), taksonomie, ustawienia serwisu i wtyczki projektu.
 *
 * Czego celowo NIE ma w eksporcie:
 *   - kont użytkowników i ich haseł — zostaje konto administratora założone
 *     w instalatorze na hostingu;
 *   - zgłoszeń, rejestru czynności, klucza szyfrowania i jego propozycji;
 *   - danych i ustawień wtyczek migracyjnych, tymczasowych danych (transientów),
 *     wersji roboczych i poprawek;
 *   - adresu e-mail administratora, adresu witryny i ustawienia widoczności dla
 *     wyszukiwarek — zostają te wpisane w instalatorze na hostingu.
 *
 * Dodatkowo wymusza: brak publicznej rejestracji kont, zamknięte komentarze,
 * polskie ustawienia regionalne (strefa Europe/Warsaw, data „30 września 2026",
 * zegar 24-godzinny) — Local ma angielskie, a nadpisałyby wartości z instalatora.
 * Lista włączonych wtyczek zostaje ta z hostingu (np. Two Factor).
 *
 * Bezpiecznik dla bazy wspólnej z inną stroną: zanim baza.sql cokolwiek usunie,
 * sprawdza, czy pod podanym prefiksem działa WordPress z włączonym motywem
 * i wtyczką STOP DYSKRYMINACJI. Jeśli nie — import przerywa się błędem, a baza
 * zostaje bez zmian. Pomyłka w prefiksie nie usunie więc tabel innej witryny.
 *
 * Adresy http://stop-dyskryminacji.local są zamieniane na docelowy adres także
 * wewnątrz danych zserializowanych (bez rozjechania długości napisów) i w adresach
 * zapisanych w JSON bloków (http:\/\/...).
 *
 * Użycie (PHP z Local, z repozytorium):
 *   php -c tools/php-local.ini tools/export_migration.php --url=https://stopdyskryminacji.pl --prefix=sd_
 *
 *   --url     docelowy adres serwisu, dokładnie taki jak w instalatorze na hostingu
 *   --prefix  prefiks tabel instalacji na hostingu — wymagany (Narzędzia → Zdrowie
 *             witryny → Informacje → Baza danych → Prefiks tabel)
 *   --out     katalog wyników (domyślnie dist/migracja)
 *
 * @package StopDyskryminacji
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( "Tylko z wiersza poleceń.\n" );
}

$repo = dirname( __DIR__ );
$opts = getopt( '', array( 'url:', 'prefix:', 'out::', 'site-path::' ) );

$target = isset( $opts['url'] ) ? rtrim( (string) $opts['url'], '/' ) : '';
$prefix = isset( $opts['prefix'] ) ? (string) $opts['prefix'] : '';
$outdir = isset( $opts['out'] ) ? (string) $opts['out'] : $repo . '/dist/migracja';
$site   = isset( $opts['site-path'] ) ? (string) $opts['site-path'] : $repo . '/stop-dyskryminacji';

if ( ! preg_match( '#^https?://[a-z0-9.-]+(:\d+)?(/[^\s]*)?$#i', $target ) ) {
	exit( "Podaj docelowy adres, np. --url=https://stopdyskryminacji.pl\n" );
}
if ( '' === $prefix ) {
	exit( "Podaj prefiks tabel instalacji na hostingu, np. --prefix=sd_\n(Narzędzia → Zdrowie witryny → Informacje → Baza danych → Prefiks tabel)\n" );
}
if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $prefix ) ) {
	exit( "Prefiks tabel może zawierać tylko litery, cyfry i podkreślnik.\n" );
}

/*
 * Port MySQL w Local zmienia się po restarcie aplikacji — odczytujemy go
 * z konfiguracji Local zamiast wpisywać na sztywno.
 */
$sites_json = getenv( 'APPDATA' ) . '/Local/sites.json';
if ( is_readable( $sites_json ) ) {
	$sites = json_decode( (string) file_get_contents( $sites_json ), true );
	foreach ( (array) $sites as $s ) {
		if ( isset( $s['path'] ) && realpath( $s['path'] ) === realpath( $site ) && isset( $s['services']['mysql']['ports']['MYSQL'][0] ) ) {
			ini_set( 'mysqli.default_port', (string) $s['services']['mysql']['ports']['MYSQL'][0] );
		}
	}
}

define( 'WP_USE_THEMES', false );
require $site . '/app/public/wp-load.php';

global $wpdb;
$src = $wpdb->base_prefix;   // tabele głównej strony sieci

$source_url  = untrailingslashit( get_option( 'home' ) );
$source_host = (string) wp_parse_url( $source_url, PHP_URL_HOST );
$target_host = (string) wp_parse_url( $target, PHP_URL_HOST );

/* --------------------------------------------------------------------------
 * Zamiana adresów — bezpieczna dla danych zserializowanych
 * ----------------------------------------------------------------------- */

$from = array();
$to   = array();
foreach ( array( 'http://', 'https://' ) as $scheme ) {
	$from[] = $scheme . $source_host;
	$to[]   = $target;
	$from[] = str_replace( '/', '\\/', $scheme . $source_host );   // JSON w atrybutach bloków
	$to[]   = str_replace( '/', '\\/', $target );
}
$from[] = '//' . $source_host;              // adresy bez schematu — na końcu
$to[]   = '//' . $target_host;

function sdmig_replace_deep( $data, $from, $to ) {
	if ( is_string( $data ) ) {
		return sdmig_replace( $data, $from, $to );
	}
	if ( is_array( $data ) ) {
		$out = array();
		foreach ( $data as $k => $v ) {
			$out[ is_string( $k ) ? str_replace( $from, $to, $k ) : $k ] = sdmig_replace_deep( $v, $from, $to );
		}
		return $out;
	}
	return $data;   // obiekty zostawiamy bez zmian
}

function sdmig_replace( $value, $from, $to ) {
	if ( ! is_string( $value ) || '' === $value ) {
		return $value;
	}
	if ( is_serialized( $value ) ) {
		$data = @unserialize( $value, array( 'allowed_classes' => false ) ); // phpcs:ignore
		if ( false !== $data || 'b:0;' === $value ) {
			return serialize( sdmig_replace_deep( $data, $from, $to ) ); // phpcs:ignore
		}
	}
	return str_replace( $from, $to, $value );
}

/* --------------------------------------------------------------------------
 * Zapis SQL
 * ----------------------------------------------------------------------- */

if ( ! is_dir( $outdir ) && ! mkdir( $outdir, 0775, true ) ) {
	exit( "Nie mogę utworzyć katalogu $outdir\n" );
}
$sql_path = $outdir . '/baza.sql';
$fh       = fopen( $sql_path, 'w' );

function sdmig_q( $value ) {
	global $wpdb;
	if ( null === $value ) {
		return 'NULL';
	}
	return "'" . mysqli_real_escape_string( $wpdb->dbh, (string) $value ) . "'";
}

fwrite( $fh, "-- STOP DYSKRYMINACJI — treść serwisu do importu na hostingu\n" );
fwrite( $fh, "-- Wygenerowano: " . gmdate( 'Y-m-d H:i' ) . " UTC; źródło: $source_url; cel: $target; prefiks: $prefix\n" );
fwrite( $fh, "-- Importuj do bazy świeżo zainstalowanego WordPressa (phpMyAdmin → Import) —\n" );
fwrite( $fh, "-- najpierw włącz tam motyw i wtyczkę STOP DYSKRYMINACJI, inaczej import się nie wykona.\n" );
fwrite( $fh, "-- Zmienia wyłącznie tabele {$prefix}*. Nie zawiera kont użytkowników, zgłoszeń ani klucza szyfrowania.\n\n" );
fwrite( $fh, "SET NAMES utf8mb4;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\nSET FOREIGN_KEY_CHECKS = 0;\nSET time_zone = '+00:00';\n\n" );

/*
 * Bezpiecznik. DROP TABLE na złym prefiksie usunąłby treść innej witryny działającej
 * w tej samej bazie. Zanim cokolwiek zmienimy, sprawdzamy, czy pod tym prefiksem działa
 * WordPress z włączonym motywem i wtyczką projektu. Jeśli nie, zapytanie do nieistniejącej
 * tabeli przerywa import — phpMyAdmin i klient mysql zatrzymują się na pierwszym błędzie.
 */
$guard_table = 'PRZERWANO_wlacz_najpierw_motyw_i_wtyczke_STOP_DYSKRYMINACJI';
fwrite( $fh, "-- Bezpiecznik: jeżeli pod prefiksem {$prefix} nie działa WordPress z włączonym motywem\n" );
fwrite( $fh, "-- i wtyczką STOP DYSKRYMINACJI, import przerywa się tutaj i baza zostaje bez zmian:\n" );
fwrite( $fh, "--   błąd o braku tabeli {$prefix}options — instalacja na hostingu ma inny prefiks tabel;\n" );
fwrite( $fh, "--   błąd o braku tabeli {$guard_table} — włącz motyw i wtyczkę, potem importuj ponownie.\n" );
fwrite( $fh, "SET @sd_ok = (SELECT COUNT(*) FROM `{$prefix}options` WHERE (`option_name` = 'template' AND `option_value` = 'stop-dyskryminacji')"
	. " OR (`option_name` = 'active_plugins' AND `option_value` LIKE '%\"stop-dyskryminacji-core/stop-dyskryminacji-core.php\"%'));\n" );
fwrite( $fh, "SET @sd_q = IF(@sd_ok = 2, 'DO 0', 'SELECT 1 FROM {$guard_table}');\n" );
fwrite( $fh, "PREPARE sd_guard FROM @sd_q;\nEXECUTE sd_guard;\nDEALLOCATE PREPARE sd_guard;\n\n" );

/** Treść wpisów: bez poprawek, szkiców automatycznych i zgłoszeń. */
$skip_types = array( 'revision', 'zgloszenie', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_global_styles' );
$post_ids   = $wpdb->get_col(
	"SELECT ID FROM {$src}posts WHERE post_status <> 'auto-draft' AND post_type NOT IN ('" . implode( "','", $skip_types ) . "')"
);
$id_list    = $post_ids ? implode( ',', array_map( 'intval', $post_ids ) ) : '0';

$tables = array(
	'posts'              => "ID IN ($id_list)",
	'postmeta'           => "post_id IN ($id_list) AND meta_key NOT IN ('_edit_lock', '_edit_last', '_sdc_payload', '_sdc_email_idx')",
	'terms'              => '1=1',
	'term_taxonomy'      => '1=1',
	'term_relationships' => "object_id IN ($id_list)",
	'termmeta'           => '1=1',
	'comments'           => '1=0',     // komentarze są w serwisie wyłączone
	'commentmeta'        => '1=0',
	'links'              => '1=1',
);

$stats = array();
foreach ( $tables as $base => $where ) {
	$source_table = $src . $base;
	$target_table = $prefix . $base;
	$create       = $wpdb->get_row( "SHOW CREATE TABLE `$source_table`", ARRAY_N );   // phpcs:ignore
	if ( ! $create ) {
		continue;
	}
	$ddl = preg_replace( '/^CREATE TABLE `[^`]+`/', 'CREATE TABLE `' . $target_table . '`', $create[1] );
	$ddl = preg_replace( '/ AUTO_INCREMENT=\d+/', '', $ddl );
	fwrite( $fh, "DROP TABLE IF EXISTS `$target_table`;\n$ddl;\n" );

	$rows = $wpdb->get_results( "SELECT * FROM `$source_table` WHERE $where", ARRAY_A );   // phpcs:ignore
	$stats[ $base ] = count( $rows );
	foreach ( array_chunk( $rows, 50 ) as $chunk ) {
		$cols   = '`' . implode( '`,`', array_keys( $chunk[0] ) ) . '`';
		$values = array();
		foreach ( $chunk as $row ) {
			if ( 'posts' === $base ) {
				// Komentarze i pingbacki zamknięte także w samych wpisach.
				$row['comment_status'] = 'closed';
				$row['ping_status']    = 'closed';
			}
			$values[] = '(' . implode( ',', array_map( function ( $v ) use ( $from, $to ) {
				return sdmig_q( sdmig_replace( $v, $from, $to ) );
			}, $row ) ) . ')';
		}
		fwrite( $fh, "INSERT INTO `$target_table` ($cols) VALUES\n" . implode( ",\n", $values ) . ";\n" );
	}
	fwrite( $fh, "\n" );
}

/** Rejestr czynności: pusta tabela — produkcja zaczyna z czystym rejestrem. */
$audit = $wpdb->get_row( "SHOW CREATE TABLE `{$src}sdc_audit`", ARRAY_N );   // phpcs:ignore
if ( $audit ) {
	$ddl = preg_replace( '/^CREATE TABLE `[^`]+`/', 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'sdc_audit`', $audit[1] );
	$ddl = preg_replace( '/ AUTO_INCREMENT=\d+/', '', $ddl );
	fwrite( $fh, "$ddl;\n\n" );
}

/*
 * Ustawienia: dopisujemy albo nadpisujemy wybrane opcje, NIE zastępujemy całej
 * tabeli. Dzięki temu zostają wartości z instalatora na hostingu: adres witryny,
 * e-mail administratora, widoczność dla wyszukiwarek, wersja bazy.
 */
$skip_exact = array(
	'siteurl', 'home', 'admin_email', 'new_admin_email', 'admin_email_lifespan', 'blog_public',
	'db_version', 'initial_db_version', 'fresh_site', 'rewrite_rules', 'recently_edited',
	'recently_activated', 'uninstall_plugins', 'user_count', 'sdc_suggested_key',
	'theme_mods_twentytwentyfive', 'auto_plugin_theme_update_emails', 'wp_force_deactivated_plugins',
	'active_plugins',   // zostaje lista z hostingu; wtyczka projektu jest włączona (sprawdza bezpiecznik)
);
$skip_regex = '/^(_site_transient_|_transient_|ai1wm_|dupli_opt_|duplicator|e2wpms_|rmmigrate_|wpvivid_|prime_mover|primemover|migrate_?guru|mg_|bv|jetpack|jpsq_|mm_oauth)/i';

$force = array(
	'users_can_register'        => '0',       // konta zakłada wyłącznie administrator
	'default_comment_status'    => 'closed',
	'default_ping_status'       => 'closed',
	// Jak w polskim instalatorze WordPressa — Local ma angielskie formaty i strefę UTC.
	'timezone_string'           => 'Europe/Warsaw',
	'gmt_offset'                => '0',
	'date_format'               => 'j F Y',
	'time_format'               => 'H:i',
	'links_updated_date_format' => 'j F Y H:i',
	'start_of_week'             => '1',
);

$options = $wpdb->get_results( "SELECT option_name, option_value, autoload FROM {$src}options", ARRAY_A );   // phpcs:ignore
$kept    = 0;
$lines   = array();
foreach ( $options as $o ) {
	$name = $o['option_name'];
	if ( in_array( $name, $skip_exact, true ) || preg_match( $skip_regex, $name ) ) {
		continue;
	}
	$value = $o['option_value'];

	if ( isset( $force[ $name ] ) ) {
		$value = $force[ $name ];
	} elseif ( 'cron' === $name ) {
		// Zostawiamy wyłącznie zadania rdzenia i platformy.
		$cron = maybe_unserialize( $value );
		if ( is_array( $cron ) ) {
			foreach ( $cron as $ts => $hooks ) {
				if ( ! is_array( $hooks ) ) {
					continue;
				}
				foreach ( array_keys( $hooks ) as $hook ) {
					if ( ! preg_match( '/^(wp_|sdc_|recovery_mode_|delete_expired_transients|do_pings|importer_scheduled_cleanup|upgrader_scheduled_cleanup)/', $hook ) ) {
						unset( $cron[ $ts ][ $hook ] );
					}
				}
				if ( empty( $cron[ $ts ] ) ) {
					unset( $cron[ $ts ] );
				}
			}
			$value = serialize( $cron ); // phpcs:ignore
		}
	} else {
		$value = sdmig_replace( $value, $from, $to );
	}

	if ( $src . 'user_roles' === $name ) {
		$name = $prefix . 'user_roles';   // nazwa opcji ról zależy od prefiksu tabel
	}

	$lines[] = '(' . sdmig_q( $name ) . ',' . sdmig_q( $value ) . ',' . sdmig_q( $o['autoload'] ) . ')';
	$kept++;
}
foreach ( $force as $name => $value ) {
	if ( ! in_array( $name, array_column( $options, 'option_name' ), true ) ) {
		$lines[] = '(' . sdmig_q( $name ) . ',' . sdmig_q( $value ) . ",'on')";
		$kept++;
	}
}
foreach ( array_chunk( $lines, 40 ) as $chunk ) {
	fwrite( $fh, "INSERT INTO `{$prefix}options` (`option_name`,`option_value`,`autoload`) VALUES\n" . implode( ",\n", $chunk )
		. "\nON DUPLICATE KEY UPDATE `option_value` = VALUES(`option_value`), `autoload` = VALUES(`autoload`);\n" );
}
// Po imporcie WordPress sam zbuduje reguły adresów i dane tymczasowe od nowa.
fwrite( $fh, "\nDELETE FROM `{$prefix}options` WHERE `option_name` = 'rewrite_rules' OR `option_name` LIKE '\\_transient\\_%' OR `option_name` LIKE '\\_site\\_transient\\_%';\n" );
fwrite( $fh, "\nSET FOREIGN_KEY_CHECKS = 1;\n" );
fclose( $fh );

/* --------------------------------------------------------------------------
 * Obrazy: tylko biblioteka mediów (katalogi lat), bez archiwów wtyczek migracyjnych
 * ----------------------------------------------------------------------- */

$uploads = wp_get_upload_dir()['basedir'];
$zip     = new ZipArchive();
$zip_path = $outdir . '/uploads.zip';
@unlink( $zip_path );
$zip->open( $zip_path, ZipArchive::CREATE );
$files = 0;
foreach ( glob( $uploads . '/[12][0-9][0-9][0-9]', GLOB_ONLYDIR ) as $year ) {
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $year, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		$rel = 'uploads/' . str_replace( '\\', '/', substr( $file->getPathname(), strlen( $uploads ) + 1 ) );
		$zip->addFile( $file->getPathname(), $rel );
		$files++;
	}
}
$zip->close();

printf( "Źródło:  %s\nCel:     %s (prefiks tabel %s)\n\n", $source_url, $target, $prefix );
printf( "baza.sql     %7.1f KB  — wiersze: %s; ustawień: %d\n", filesize( $sql_path ) / 1024,
	implode( ', ', array_map( function ( $k, $v ) { return "$k $v"; }, array_keys( $stats ), $stats ) ), $kept );
printf( "uploads.zip  %7.1f KB  — plików: %d\n", filesize( $zip_path ) / 1024, $files );
printf( "\nPozostałe odwołania do %s w baza.sql: %d\n", $source_host, substr_count( (string) file_get_contents( $sql_path ), $source_host ) );
printf( "Bezpiecznik: import wykona się tylko tam, gdzie pod prefiksem %s działają motyw i wtyczka STOP DYSKRYMINACJI.\n", $prefix );
