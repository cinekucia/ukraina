# Dokumentacja wdrożeniowa — platforma „STOP DYSKRYMINACJI"

Projekt nr FEWP.06.10-IP.01-0136/25, dofinansowany przez Unię Europejską ze środków
Europejskiego Funduszu Społecznego Plus w ramach programu Fundusze Europejskie
dla Wielkopolski 2021–2027.

Dokument odpowiada etapowi 9 Załącznika nr 1 do umowy zlecenia nr 1/Z1/2026.

---

## 1. Co składa się na platformę

Rozwiązanie to **klasyczny WordPress** (nie headless) w architekturze motywu blokowego
z pełną edycją witryny. Składa się z dwóch elementów autorskich:

| Element | Katalog | Odpowiada za |
|---|---|---|
| Motyw `stop-dyskryminacji` | `wp-content/themes/stop-dyskryminacji` | wygląd: tokeny projektowe, arkusz stylów, szablony, wzorce bloków |
| Wtyczka `stop-dyskryminacji-core` | `wp-content/plugins/stop-dyskryminacji-core` | funkcje: typy treści, moduł zgłoszeniowy, oznakowanie FE/RP/UE/herb, zgoda na cookie, GA4, narzędzia |

### Dlaczego taki podział

Trzy rzeczy muszą przetrwać zmianę albo awarię motywu:

1. **oznakowanie projektu** — jego brak to pomniejszenie dofinansowania o 1 956,47 zł
   (Załącznik nr 10 rozdz. 10);
2. **moduł zgłoszeniowy** razem z kluczem szyfrowania i rejestrem dostępu;
3. **typy treści** z zaimportowanymi publikacjami i artykułami.

Dlatego wszystkie trzy mieszkają we wtyczce. Motyw da się wymienić bez utraty
zgodności z umową i bez utraty danych.

### Czego celowo nie ma

- **Zero webfontów.** Kolejka czcionek jest systemowa. To największa pojedyncza
  oszczędność dla wyniku Lighthouse na urządzeniu mobilnym i jednocześnie usuwa
  żądania do serwerów Google przed uzyskaniem zgody.
- **Zero zasobów z domen trzecich** w krytycznej ścieżce renderowania.
- **Zero etapu budowania.** Brak webpacka i `node_modules`. Repozytorium działa
  takie, jakie jest — bez odtwarzania środowiska narzędziowego.
- **Brak kreatorów stron** (Elementor i podobne). Wygląd opisują `theme.json`
  i jeden arkusz stylów.

---

## 2. Instalacja od zera

### 2.1 Wymagania

| Składnik | Wersja minimalna | Uwagi |
|---|---|---|
| WordPress | 6.6 | testowane na 7.1 |
| PHP | 7.4 | zalecane 8.2+ |
| Rozszerzenia PHP | `sodium` **albo** `openssl` z AES-256-GCM | bez jednego z nich moduł zgłoszeniowy się nie uruchomi |
| Baza | MySQL 5.7+ / MariaDB 10.4+ | |
| HTTPS | wymagane | szyfrowanie w transmisji — kryterium odbioru |

### 2.1a Baza danych i instalator WordPressa

Przed instalatorem przygotuj bazę: **osobną** (panel hostingu → „Bazy danych MySQL" →
nowa baza i osobny użytkownik z długim, losowym hasłem i uprawnieniami tylko do tej bazy)
albo **wspólną z inną stroną** (niżej). Hasło zapisz w menedżerze haseł Stowarzyszenia —
nie w e-mailu ani w komunikatorze. Instalator otwieraj od razu pod adresem **https://**,
żeby adres witryny był zapisany z HTTPS.

| Pole instalatora | Co wpisać |
|---|---|
| Nazwa bazy danych | nazwa bazy utworzonej w panelu hostingu (często z przedrostkiem konta, np. `konto_stopdysk`) |
| Nazwa użytkownika | użytkownik bazy utworzony w panelu — nie główne konto hostingu |
| Hasło | hasło tego użytkownika bazy |
| Adres serwera bazy danych | zwykle `localhost`; jeżeli panel hostingu podaje inny adres serwera MySQL, wpisz ten |
| Prefiks tabel | zamiast `wp_` własny, np. `sd4k_` (litery, cyfry, podkreślnik) — utrudnia zautomatyzowane ataki nastawione na domyślne nazwy; wtyczka projektu obsługuje dowolny prefiks |

**Baza wspólna z inną stroną.** Serwis może działać w bazie, w której są już tabele innej
witryny WordPress — każda strona ma własny prefiks tabel i nie dotyka tabel drugiej.
W instalatorze wpisuje się wtedy te same dane dostępu co w `wp-config.php` tamtej strony:
nazwę bazy, użytkownika, hasło i adres serwera (na nazwa.pl adres w rodzaju
`mariadbNNN.serverNNNNNN.nazwa.pl`, nie `localhost`) oraz **inny prefiks**, np. `sd_`.
Prefiks drugiej strony widać w jej `wp-config.php` (`$table_prefix`), a prefiks wykonanej
już instalacji — w **Narzędzia → Zdrowie witryny → Informacje → Baza danych → Prefiks
tabel**. Obie strony mają to samo hasło bazy: po jego zmianie w panelu hostingu trzeba je
poprawić w obu plikach `wp-config.php`.

Na drugim ekranie instalatora: **konto administratora imienne** (np. `jan.kowalski`,
nie `admin` — test „Konta imienne i uwierzytelnianie dwuskładnikowe" oznaczy wspólne
nazwy jako błąd), silne hasło, a do dnia uruchomienia zaznacz **„Proś wyszukiwarki
o nieindeksowanie witryny"** (odznaczyć 1.11.2026).

`wp-config.php` powstaje na serwerze z tymi danymi — **nigdy nie trafia do repozytorium**
(wyklucza go `.gitignore`). Klucz szyfrowania dopisuje się do niego w kroku 4 poniżej;
na produkcji zawsze **nowy** klucz, nigdy klucz ze środowiska deweloperskiego.

### 2.1b Przeniesienie serwisu z Local na hosting

Lokalna kopia działa jako sieć WordPress (multisite) z jedną stroną; na hostingu serwis
działa jako **zwykła, pojedyncza instalacja**. Darmowe wtyczki migracyjne nie przenoszą
multisite, a pliku `app/sql/local.sql` z Local **nie importuje się** — zawiera tabele sieci,
konto deweloperskie z hasłem, dane wtyczek migracyjnych i adresy `stop-dyskryminacji.local`.

Eksport przygotowuje skrypt `tools/export_migration.php`:

```bash
php -c tools/php-local.ini tools/export_migration.php --url=https://stopdyskryminacji.pl --prefix=sd_
```

`--prefix` jest wymagany i musi być taki sam jak prefiks instalacji na hostingu.

Wynik w `dist/migracja/`: `baza.sql` (treść i ustawienia; zamiana adresów bezpieczna dla
danych zserializowanych) i `uploads.zip` (biblioteka mediów). Eksport **nie zawiera** kont
użytkowników, zgłoszeń, rejestru czynności, klucza szyfrowania ani danych wtyczek
migracyjnych; wymusza wyłączoną rejestrację kont, zamknięte komentarze i polskie ustawienia
regionalne (strefa Europe/Warsaw, data „30 września 2026", zegar 24-godzinny). Ustawienia
dopisuje, zamiast je zastępować: zostają adres witryny, e-mail administratora, widoczność
dla wyszukiwarek i lista włączonych wtyczek z hostingu (np. Two Factor).

**Bezpiecznik dla bazy wspólnej.** `baza.sql` zmienia wyłącznie tabele z podanym prefiksem.
Zanim cokolwiek usunie, sprawdza, czy pod tym prefiksem działa WordPress z włączonym motywem
i wtyczką STOP DYSKRYMINACJI. Jeśli nie, import zatrzymuje się na pierwszym poleceniu,
a baza zostaje bez zmian — pomyłka w prefiksie nie usunie tabel innej strony:

| Błąd w phpMyAdmin | Znaczenie | Co zrobić |
|---|---|---|
| brak tabeli `sd_options` | instalacja na hostingu ma inny prefiks | eksport z właściwym `--prefix` |
| brak tabeli `PRZERWANO_wlacz_najpierw_motyw_i_wtyczke_STOP_DYSKRYMINACJI` | motyw albo wtyczka nie są włączone | włączyć oba (krok 4), zaimportować ponownie |

Import można powtórzyć — kolejny daje ten sam wynik.

**Serwis musi działać w katalogu głównym domeny albo subdomeny** (np. `stopdyskryminacji.pl`
albo `stop.domena.pl`), nie w podkatalogu (`domena.pl/stop/`) — motyw i treść mają ok. 100
odnośników liczonych od katalogu głównego (`/potrzebuje-pomocy/`, `/ua/`…).

Kolejność na hostingu:

1. **Kopia zapasowa całej bazy** (phpMyAdmin → baza → Eksport), zwłaszcza gdy działa w niej
   inna strona.
2. Domena albo subdomena wskazująca na **nowy, pusty katalog**; certyfikat SSL.
3. WordPress w tym katalogu, instalator (rozdz. 2.1a) z **tym samym prefiksem**, który
   podano w eksporcie (np. `sd_`), i imiennym kontem administratora.
4. Wtyczka i motyw — **przed** importem bazy; **włącz oba** (bez tego bezpiecznik zatrzyma
   import). Z panelu: Wtyczki / Motywy → Dodaj → Wyślij plik ZIP z `dist/`. Przez FTP/SFTP
   (np. WinSCP): folder `dist/wgraj-przez-winscp/wp-content` przeciągnięty do katalogu
   WordPressa na serwerze — zawiera wtyczkę, motyw i bibliotekę mediów w układzie jak na
   serwerze (to rozpakowane paczki ZIP i `uploads.zip`), więc krok 5 odpada.
5. `uploads.zip` rozpakowany do `wp-content/` (menedżer plików hostingu albo FTP →
   `wp-content/uploads/2026/…`).
6. phpMyAdmin → baza → **Import** → `baza.sql`. Tabele innej strony pozostają nietknięte.
7. `wp-config.php`: nowy `SDC_ENCRYPTION_KEY` (linia z Ustawienia → STOP DYSKRYMINACJI),
   `DISALLOW_FILE_EDIT` itd. (rozdz. 11c). Przed edycją pobierz kopię pliku.
8. Ustawienia → Bezpośrednie odnośniki → **Zapisz**; Ustawienia → Ogólne — e-mail
   administratora; Ustawienia → STOP DYSKRYMINACJI — adresy powiadomień i kontaktu muszą
   być działającymi skrzynkami, telefon zapasowy (pole jest puste); Deklaracja dostępności —
   pola „[do uzupełnienia przez Zamawiającego]".
9. Kontrola: `SD_BASE_URL=https://stopdyskryminacji.pl python tests/links/link_audit.py`
   oraz `tests/zalacznik10/check.py`.

Procedura została przećwiczona na czystej instalacji WordPressa z prefiksem `sd4k_`:
wszystkie strony (PL, UA, EN), menu językowe, obrazy, panel i moduł zgłoszeniowy działały
po imporcie; audyt odnośników 53/53, lista kontrolna Załącznika nr 10 16/16.

Wariant z bazą wspólną przećwiczono 30.09.2026 na MariaDB 10.11 (nazwa.pl: MariaDB 10.6):
w jednej bazie druga strona z prefiksem `wp_` i serwis z prefiksem `sd_`. Bezpiecznik
zatrzymał import we wszystkich czterech błędnych sytuacjach (motyw i wtyczka niewłączone,
sam motyw, eksport z prefiksem drugiej strony, brak tabel `sd_`); sumy kontrolne tabel
`wp_*` nie zmieniły się ani razu. Po właściwym imporcie i po jego powtórzeniu: audyt
odnośników 53/53, Załącznik nr 10 16/16, szyfrowanie działa, lista wtyczek z hostingu
zachowana, strefa Europe/Warsaw.

### 2.2 Kroki

1. Skopiuj katalog motywu do `wp-content/themes/` i katalog wtyczki do `wp-content/plugins/`.
2. Włącz motyw: **Wygląd → Motywy → STOP DYSKRYMINACJI**.
3. Włącz wtyczkę: **Wtyczki → STOP DYSKRYMINACJI — moduł podstawowy**.
4. **Ustaw klucz szyfrowania** — patrz punkt 3 poniżej. Bez tego kroku formularz
   zgłoszeniowy nie przyjmuje zgłoszeń.
5. Ustaw bezpośrednie odnośniki: **Ustawienia → Bezpośrednie odnośniki → Nazwa wpisu**.
5a. Ustaw język witryny na **polski**: **Ustawienia → Ogólne → Język witryny**.
    WordPress pobierze wtedy paczkę tłumaczeń do `wp-content/languages`.
5b. Doinstaluj paczkę **ukraińską**: w tym samym polu wybierz **Українська**,
    zapisz (WordPress pobierze paczkę), a potem wróć do **Polski** i zapisz ponownie.
    Paczka zostaje zainstalowana. Z WP-CLI: `wp language core install uk`.
    Bez paczek wtyczka podstawia własny, minimalny zestaw tłumaczeń rdzenia
    (nazwy miesięcy, przyciski menu, tytuł strony 404) — patrz punkt 5.1 —
    ale pełna paczka jest lepsza.
6. Uzupełnij **Ustawienia → STOP DYSKRYMINACJI** (punkt 4).
7. Utwórz strony wymienione w punkcie 5 i ustaw stronę główną.
8. Sprawdź **Narzędzia → Stan witryny** — pięć testów projektu musi być zielonych.

---

## 3. Klucz szyfrowania — krok obowiązkowy

Moduł zgłoszeniowy przechowuje opis sytuacji, który może zawierać dane szczególnych
kategorii w rozumieniu art. 9 RODO. Umowa powierzenia (§ 3 ust. 1) wymaga szyfrowania
tych treści w spoczynku.

**Klucz mieszka w `wp-config.php`, nigdy w bazie danych.** Gdyby był w tabeli opcji,
jeden zrzut bazy zawierałby i szyfrogram, i klucz do niego — czyli szyfrowanie nie
chroniłoby przed niczym realnym.

Wygeneruj klucz i wklej gotową linię z ekranu **Ustawienia → STOP DYSKRYMINACJI**
do `wp-config.php`, powyżej wiersza `/* That's all, stop editing! */`:

```php
define( 'SDC_ENCRYPTION_KEY', 'tu-wklej-wygenerowany-klucz-base64' );
```

> **Utrata klucza oznacza bezpowrotną utratę dostępu do wszystkich zaszyfrowanych
> zgłoszeń.** Zapisz go w menedżerze haseł Zamawiającego, zanim serwis ruszy
> produkcyjnie. Odzyskanie danych bez klucza nie jest możliwe — to właściwość
> szyfrowania, nie usterka.

Jeżeli klucza nie ma, moduł **celowo odmawia przyjmowania zgłoszeń** i pokazuje
użytkownikowi alternatywne drogi kontaktu. Utrata jednego zgłoszenia jest mniejszą
szkodą niż zapisanie danych z art. 9 RODO otwartym tekstem.

### Algorytm

| Warunek | Algorytm | Prefiks szyfrogramu |
|---|---|---|
| dostępne `sodium` | XSalsa20-Poly1305 | `v1.` |
| tylko OpenSSL | AES-256-GCM | `v2.` |

Oba są szyfrowaniem **uwierzytelnionym** — modyfikacja szyfrogramu w bazie jest
wykrywana przy odczycie i zgłaszana jako błąd integralności, a nie odczytywana
jako uszkodzona treść. Prefiks wersji pozwala w przyszłości zmienić algorytm bez
utraty dostępu do starszych zgłoszeń.

---

## 4. Ustawienia platformy

**Ustawienia → STOP DYSKRYMINACJI**

### Moduł zgłoszeniowy

| Pole | Znaczenie |
|---|---|
| Deklarowany czas odpowiedzi | pokazywany użytkownikowi na stronie i w potwierdzeniu e-mail |
| Okres retencji zgłoszeń | po tylu dniach zgłoszenia są usuwane automatycznie; **musi odpowiadać deklaracji w klauzuli informacyjnej** |
| Adres powiadomień | dokąd idzie informacja o nowym zgłoszeniu (sama sygnatura, nigdy treść) |
| Zapasowy e-mail i telefon | pokazywane, gdy formularz jest niedostępny |
| Limit zgłoszeń na godzinę | ochrona przed nadużyciem, domyślnie 5 |
| Strona podziękowania | domyślnie `/dziekujemy/` |

### Dokumenty RODO

Treści pochodzą z **Załącznika nr 11** i zgodnie z § 7 ust. 7 umowy **nie mogą być
modyfikowane bez pisemnej zgody Zamawiającego**. Wklej pełne brzmienie klauzuli
w pole „Klauzula pod modułem zgłoszeniowym".

### Analityka i zabezpieczenia

| Pole | Uwagi |
|---|---|
| Identyfikator GA4 | postać `G-XXXXXXXXXX`. **Puste pole wyłącza analitykę i ukrywa okno zgody** — tak też serwis jest przekazywany |
| reCAPTCHA v3 — klucz witryny i tajny | z konsoli Google reCAPTCHA |
| Próg akceptacji reCAPTCHA | 0.0–1.0, domyślnie 0.5; wyższa wartość odrzuca więcej zgłoszeń, także prawdziwych |

---

## 5. Struktura stron do utworzenia

| Adres | Tytuł | Szablon | Zawiera |
|---|---|---|---|
| `/` | STOP DYSKRYMINACJI | *strona główna* | ustaw jako stronę główną w **Ustawienia → Czytanie** |
| `/o-projekcie/` | O projekcie | „O projekcie (obowiązkowe elementy)" | wzorzec „O projekcie — treść obowiązkowa" |
| `/potrzebuje-pomocy/` | Potrzebuję pomocy | domyślny | blok „Formularz «Potrzebuję pomocy»" |
| `/dziekujemy/` | Dziękujemy za zgłoszenie | domyślny | potwierdzenie |
| `/samoocena/` | Samoocena firmy | „Strona szeroka" | blok „Samoocena firmy" |
| `/gdzie-szukac-pomocy/` | Gdzie szukać pomocy | „Strona szeroka" | blok „Nawigator instytucji pomocowych" |
| `/polityka-prywatnosci/` | Polityka prywatności | domyślny | treść z Załącznika nr 11 |
| `/polityka-cookies/` | Polityka plików cookie | domyślny | treść z Załącznika nr 11 |
| `/deklaracja-dostepnosci/` | Deklaracja dostępności | domyślny | uzupełnić po audycie |
| `/aktualnosci/` | Baza wiedzy | *pusta* | **ustaw jako „Stronę wpisów"** w **Ustawienia → Czytanie** |

Publikacje, dobre praktyki i instytucje to osobne typy treści z własnymi archiwami —
nie zakłada się dla nich stron ręcznie.

> Artykuły korzystają z wbudowanego typu „wpis", a ten **nie ma własnego archiwum**,
> dopóki nie wskaże się strony wpisów. Bez tego kroku odnośniki do `/aktualnosci/`
> w stopce i we wzorcach prowadzą do strony 404.

### 5.1 Sekcje językowe (UA, EN)

Bez wtyczki tłumaczącej. Język żądania wynika z **przedrostka adresu**, ustawionego
w **Ustawienia → STOP DYSKRYMINACJI → Wersje językowe** (`/ua/`, `/en/`). Wszystko poniżej
działa w `includes/languages.php` wtyczki i przy braku przedrostka nic nie zmienia.

| Element | Jak dostosowuje się do sekcji |
|---|---|
| Napisy interfejsu (formularz, okno zgody, okruszki, przycisk w nagłówku) | filtr `locale` → `uk` / `en_US`; pliki `.mo` motywu i wtyczki |
| Menu w nagłówku | wpis `wp_navigation` z meta `_sdc_nav_lang` podstawiany w bloku nawigacji |
| Stopka | część szablonu `footer-uk` / `footer-en` podstawiana za `footer` |
| Strona 404 | szablon `404-uk` / `404-en` dokładany na początek hierarchii szablonów |
| Nazwa serwisu i „Strona główna" w okruszkach | prowadzą do `/ua/` / `/en/`, nie do polskiej strony głównej |
| Przełącznik PL / UA / EN | prowadzi do odpowiednika strony (meta `_sdc_translation_of`, pole „Wersja językowa" przy edycji strony), a gdy go nie ma — do strony głównej sekcji |
| Znaczniki `hreflang` | generowane z tych samych powiązań |
| Zestaw znaków | EN w sekcji EN; w UA polski, bo program nie udostępnia wersji ukraińskiej, a znaków nie wolno odtwarzać |

**Strony UA/EN** muszą mieć stronę nadrzędną `ua` albo `en` — to ona wyznacza
język strony w panelu „Wersja językowa". Strona ukraińska umieszczona poza tą
gałęzią dostanie polski interfejs.

**Tekst alternatywny zestawu znaków zostaje polski w każdej sekcji** — jego treść
jest narzucona słowo w słowo (Załącznik nr 10, punkt 7 listy kontrolnej). Obraz ma
atrybut `lang="pl"`, żeby czytnik ekranu na stronie ukraińskiej przeczytał go
polskim głosem (WCAG 3.1.2). Z tego samego powodu polskie nazwy własne w stopkach
UA/EN są w znaczniku `<bdo lang="pl">` — to ten sam znacznik, który wstawia opcja
„Język" w edytorze bloków.

**Tłumaczenia rdzenia WordPressa.** Nazwy miesięcy, etykiety przycisków menu na
telefonie i tytuł karty na stronie 404 pochodzą z paczki językowej WordPressa, nie
z motywu. Gdy paczki `pl_PL` albo `uk` nie ma, wtyczka ładuje własny, minimalny
zestaw (`languages/wordpress-pl_PL.mo`, `languages/wordpress-uk.mo`, kilkadziesiąt
napisów ustalonych pomiarem). Zainstalowana paczka ma zawsze pierwszeństwo.

**Co celowo zostaje po polsku:** klauzula RODO i polityki (Załącznik nr 11, zmiana
tylko za pisemną zgodą Zamawiającego), deklaracja dostępności, opis „O projekcie"
(tekst narzucony co do słowa) oraz merytoryczne treści o prawie pracy. Stopki UA/EN
linkują do nich z dopiskiem „(польською)" / „(in Polish)". Tłumaczenie tych treści
wymaga tłumacza i weryfikacji prawnej, a nie tłumaczenia maszynowego.

### 5.2 Uwagi do szablonów

Szablony `.html` nie zawierają komentarzy HTML. Edytor bloków traktuje komentarz
wewnątrz bloku jako niezgodność zapisu („blok zawiera nieoczekiwaną lub
nieprawidłową zawartość"), a komentarz między blokami jako pusty blok „Klasyczny".
Uzasadnienia, które wcześniej były w komentarzach:

| Szablon | Uwaga |
|---|---|
| `front-page.html` | kolejność sekcji: jedno zdanie i jedno wezwanie → wejście przez sytuację użytkownika, nie przez przepisy → podział na role → co się dzieje po zgłoszeniu (zdejmuje lęk przed kontaktem) → publikacje (wskaźnik: 1 000 pobrań) → ścieżka dla pracodawcy → najnowsze artykuły → najczęstsze pytania |
| `home.html` | H1 „Baza wiedzy" jest wpisany wprost — wbudowany typ „wpis" nie jest archiwum dla bloku „Tytuł zapytania", a bez H1 strona ma uwagę w audycie (WCAG 1.3.1, 2.4.6) |
| `page-o-projekcie.html` | cała treść to zwykłe bloki wpisu, bo sekcja efektów ma być edytowalna bez programisty (punkt 10 listy kontrolnej) |
| `page-szeroka.html` | dla stron narzędziowych (samoocena, nawigator, dobre praktyki) — siatki i filtry potrzebują pełnej szerokości |
| `archive-publikacja.html` | nagłówek, filtry i siatka w jednej kolumnie o układzie przepływowym — inaczej tytuł byłby wcięty względem kart; filtry to zwykły formularz GET, działa bez JavaScriptu |
| `single-publikacja.html` | lewa kolumna: okładka, pobrania, udostępnianie; prawa: opis; układ przepływowy, bo kolumna jest już węższa od kontenera |
| `single.html` | spis treści z nagłówków H2 znika, gdy sekcji jest mniej niż trzy |
| `parts/header.html` | przycisk w nagłówku to blok `stopdysk/help-cta` — napis i adres zależą od języka sekcji |

---

## 6. Model treści

### Typy wpisów

| Typ | Adres | Zastosowanie |
|---|---|---|
| `publikacja` | `/publikacje/` | Kodeks, poradniki, kompendium — z plikami PDF/EPUB/DOCX |
| `post` (etykieta „Artykuły") | `/aktualnosci/` | 12 artykułów bazy wiedzy |
| `praktyka` | `/dobre-praktyki/` | baza dobrych praktyk z filtrami |
| `instytucja` | — | nawigator instytucji pomocowych |
| `zgloszenie` | **niepubliczny** | zgłoszenia z modułu; brak w REST API, brak w eksporcie WXR |

### Taksonomie

`przeslanka`, `grupa_odbiorcow`, `typ_publikacji` (publikacje), `branza`,
`wielkosc_firmy` (praktyki), `typ_instytucji`.

### Pola publikacji

Panel **Pliki do pobrania** w edytorze publikacji (`assets/js/publication-files.js`, od
wersji 1.0.2) zapisuje identyfikatory plików z biblioteki mediów w `_sdc_pdf_id`,
`_sdc_epub_id` i `_sdc_docx_id`; plik można wgrać z panelu albo wskazać z biblioteki.
WordPress domyślnie odrzuca pliki EPUB — wtyczka dodaje typ `application/epub+zip`
do dozwolonych. Pola `_sdc_pages`, `_sdc_isbn` i `_sdc_lang` są zarejestrowane, ale
serwis ich jeszcze nie pokazuje i panel ich nie edytuje.

Przypisanie po nazwie pliku (`includes/publication-files.php`, od wersji 1.0.3): plik
wgrany w bibliotece mediów bez przypisania do treści trafia do publikacji, jeżeli jego
nazwa zaczyna się od kodu z pola `_sdc_file_code` (np. `P4`), adresu albo tytułu
publikacji; format według rozszerzenia. Nowszy plik zastępuje starszy, nigdy odwrotnie;
plik trafia tylko do publikacji, którą wgrywający może edytować. Nazwy wgrywanych
dokumentów (PDF, EPUB, DOCX) są zapisywane małymi literami, bez polskich znaków i spacji;
nazw obrazów to nie zmienia. **Publikacje → Pliki do pobrania** pokazuje zestawienie
i przypisuje pliki wgrane wcześniej.

---

## 7. Uprawnienia

| Rola | Widzi zgłoszenia | Uwagi |
|---|---|---|
| Administrator | tak | pełny dostęp |
| **Opiekun zgłoszeń** (`sdc_caseworker`) | tak | rola dodana przez wtyczkę; **nie ma dostępu do treści redakcyjnych** |
| Redaktor / Autor | **nie** | zgłoszenia są poza zasięgiem |

Zgodnie z § 3 ust. 1 umowy powierzenia dostęp administracyjny wymaga kont imiennych
i uwierzytelniania dwuskładnikowego — konfiguruje się je po stronie hostingu
i wtyczki 2FA wybranej przez Zamawiającego.

---

## 8. Rejestr czynności i retencja

### Rejestr

Tabela `{prefix}sdc_audit`. Zapisuje **kto, kiedy i co zrobił** — nigdy treści
zgłoszenia ani danych osobowych.

Odnotowywane czynności: utworzenie zgłoszenia, **każdy odczyt odszyfrowanej treści**,
zmiana statusu, eksport, usunięcie, usunięcie z retencji, logowanie do panelu,
odrzucenie zgłoszenia (spam, reCAPTCHA, limit).

Rejestr widać na ekranie każdego zgłoszenia oraz w jego eksporcie JSON.
Retencja rejestru: **minimum 365 dni**, nie da się jej obniżyć z panelu
(§ 3 ust. 1 wymaga nie mniej niż 12 miesięcy).

### Retencja zgłoszeń

Zadanie cykliczne `sdc_daily_maintenance` uruchamia się codziennie o 3:15 i trwale
usuwa zgłoszenia starsze niż zadeklarowany okres — razem z koszem, bo zgłoszenie
w koszu nadal jest przetwarzaniem danych.

> Na hostingu warto wyłączyć `WP_CRON` i wywoływać `wp-cron.php` systemowym
> harmonogramem. WP-Cron działa tylko przy ruchu na stronie, a retencja musi
> działać także w dni bez odwiedzin.

### Obsługa żądań z RODO

Na ekranie zgłoszenia: **Eksportuj zgłoszenie (JSON)** — art. 15 i 20,
**Usuń trwale** — art. 17. Obie czynności trafiają do rejestru.

**Znalezienie zgłoszenia, gdy osoba podaje tylko adres e-mail:** wpisz adres w polu
wyszukiwania na liście zgłoszeń. Adres jest w bazie wyłącznie zaszyfrowany, dlatego
obok zapisywany jest jego skrót HMAC-SHA256 (klucz wyprowadzony z klucza szyfrowania) —
pozwala znaleźć zgłoszenie po dokładnym adresie, ale nie pozwala odtworzyć adresu.
Wyszukanie trafia do rejestru, bez zapisu samego adresu. Działa dla zgłoszeń przyjętych
od 25.09.2026 (wcześniejsze znajdziesz po sygnaturze z e-maila z potwierdzeniem).

**Cały rejestr:** **Zgłoszenia → Rejestr czynności** (tylko administrator) — filtr po
czynności i numerze zgłoszenia, eksport CSV na żądanie Administratora, przy kontroli
(umowa powierzenia § 6) albo po naruszeniu (§ 5).

---

## 9. Oznakowanie projektu

### Pliki

`wp-content/plugins/stop-dyskryminacji-core/assets/brand/`

| Plik | Rola |
|---|---|
| `zestawienie-znakow-pl-original.png` | **oryginał z paczki KOLOR_POZIOM.zip, bajtowo nieprzerobiony** |
| `zestawienie-znakow-pl-{640,900,1200,1800}.png` | przeskalowania do `srcset` (bez zmiany barw i kadru) |
| `og-image-pl.png` / `og-image-en.png` | obraz podglądu 1200 × 630 px z zestawem znaków (`tools/build_og_images.py`) |
| `PROWENIENCJA.json` | ścieżki źródłowe i sumy kontrolne SHA-256 |
| `sygnet.svg`, `sygnet-negatyw.svg`, `sygnet-achromatyczny.svg` | sygnet logo projektu „Dymek równości" (`tools/build_brand_icons.py`) |
| `favicon.svg`, `favicon-32.png`, `favicon-180.png` | ikona karty przeglądarki i ekranu startowego |

Wersja polska: `Zestawienie FE+RP+UE+HERB.png`, SHA-256 `a8ad061d30f742ca…`.

### Zasady zrealizowane w kodzie

- zestaw znaków **na górze każdej podstrony**, nad treścią główną — samo umieszczenie
  w stopce nie spełnia wymagania (Załącznik nr 10 rozdz. 5);
- wyłącznie **wersja pełnokolorowa**; achromatyczna na stronie internetowej jest zakazana;
- osadzenie jako `<img>` w treści, **nigdy jako tło CSS**;
- tekst alternatywny dokładnie w brzmieniu z rozdz. 9 — stała `SDC_LOCKUP_ALT`;
- serwowanie **z serwera projektu**, nie z adresu zewnętrznego;
- Open Graph i Twitter Card z obrazem 1200 × 630 px zawierającym zestaw znaków;
- **wymiary z pomiaru pliku**: flaga UE to 66,2 % wysokości pliku, plik ma własny
  margines 16,6 %. Pasek u góry ma zestaw 72 px wysokości na komputerze (napisy
  ok. 10 px — czytelne) i wolne pole = połowa wysokości pliku z każdej strony, co
  razem z marginesem pliku daje co najmniej wysokość flagi (rozdz. 4). Na wąskim
  ekranie zestaw zmniejsza się proporcjonalnie do szerokości paska — nigdy nie jest
  przycinany ani przewijany (rozdz. 5).

Sprawdzenie całej listy kontrolnej na działającym serwisie:

```bash
python tests/zalacznik10/check.py
```

Podgląd i dowody do zrzutów ekranu: **Ustawienia → STOP DYSKRYMINACJI**, sekcja
„Oznakowanie projektu".

> Blok zestawu znaków jest zablokowany przed usunięciem i przeniesieniem
> (`lock: move/remove`). Redaktor nie usunie go przypadkiem z szablonu.

### Logo projektu i wygląd (projekt „STOP DYSKRYMINACJI — logo i layout publikacji")

- **Logo** — kierunek A „Dymek równości": znak równości w dymku rozmowy, dwie kreski
  tej samej długości (biała i morelowa). Blok **„Logo serwisu"** (`stopdysk/brand`)
  w nagłówku: sygnet SVG + nazwa z ustawień witryny, pierwsze słowo w kolorze marki,
  odnośnik do strony głównej bieżącej wersji językowej.
- **Wysokość sygnetu** = 0,6 wysokości zestawu znaków w pasku (zmienna `--sd-sign-h`,
  ta sama reguła w motywie i we wtyczce). Flaga UE to 0,662 wysokości zestawu, więc
  logo nigdy nie jest od niej wyższe — wymóg projektu znaku („sygnet nie wyższy niż
  znak UE w zestawieniu") i rozporządzenia 2021/1060 zał. IX. Punkt **6a** listy
  kontrolnej mierzy to przy 1440, 375, 320 px i na telefonie w poziomie.
- **Ikona witryny** (favicon SVG + PNG, ikona ekranu startowego) — wysyłana, dopóki
  w Wygląd → Dostosuj nie ustawiono własnej ikony.
- **Kolory** (`theme.json`): morski `#0B5563` — marka, odnośniki, nadtytuły; ceglany
  `#C2410C` — działanie (przyciski, „Potrzebuję pomocy"); ceglany ciemny `#9A3412` —
  najechanie, błędy; morela `#F59E6B` — **tylko grafika** (2,1 : 1, nie do tekstu);
  morski jasny `#E6F0F1` i morela jasna `#FCEBDF` — tła ramek; papier `#FAF7F2` — tło
  sekcji; atrament `#14181F` — tekst. Najsłabsza para tekst/tło: biały na ceglanym
  5,2 : 1 (AA). Fokus: obwódka morska z białą poświatą — widoczna na jasnym i ciemnym.
- **Kolory serii publikacji**: morski (01 Kodeks), ceglany (02 i 03 — poradniki),
  zielony `#2F6B3A` (04 Kompendium); fiolet z projektu odrzucony — do wyboru ochra
  `#8A5A00`. Domyślnie według typu publikacji, zmiana w edytorze: panel **„Kolor
  serii"**. Wtyczka dodaje klasę `sd-seria-<kolor>` do strony publikacji i jej kart.
- **Grafiki zastępcze** — okładki w układzie z projektu (`tools/build_covers.py`)
  i obrazy artykułów (`tools/build_article_images.py`) w nowej palecie. Zastępują je
  okładki od grafika; skrypty zapisują pliki pod tymi samymi nazwami i w wymiarach
  miniatur WordPressa, więc podmiana nie wymaga zmian w treści.

### Pliki publikacji i artykułów (DOCX, PDF) w identyfikacji

`tools/brand_docs.py` nadaje plikom DOCX od autorów (eksport z pandoca: style Title,
Subtitle, Heading 1–3, Block Text, tabele) wygląd z projektu publikacji;
`tools/docx_to_pdf.ps1` zapisuje je przez Worda jako PDF z tagami. **Oryginały zostają
nietknięte** — wynik trafia do osobnego katalogu.

```
python tools/brand_docs.py --all <katalog z plikami P*_*.docx i A*_*.docx> --out dist/publikacje/docx
powershell -ExecutionPolicy Bypass -File tools\docx_to_pdf.ps1 -InDir dist\publikacje\docx -OutDir dist\publikacje\pdf
```

- **P1–P4** — okładka w kolorze serii: 01 morski, 02 i 03 ceglany (`--ochra` — P3
  w ochrze), 04 zielony. Na okładce sygnet, numer, nadtytuł, tytuł i podtytuł, dwie
  kreski, odbiorcy, oryginalny zestaw znaków z tekstem alternatywnym z rozdz. 9
  Załącznika nr 10 i zdanie o dofinansowaniu. Dalej spis treści, nagłówek strony
  („KODEKS 01 · STOP DYSKRYMINACJI") i numer strony. P0 i P5 — „DOKUMENT ROBOCZY",
  morski.
- **A01–A12** — bez okładki: zestaw znaków i pas tytułowy (nadtytuł „BAZA WIEDZY ·
  ARTYKUŁ A01", tytuł) na pierwszej stronie.
- **Fonty** — tekst Literata, nagłówki Onest, jeśli są zainstalowane w systemie;
  inaczej Georgia i Segoe UI (skrypt wypisuje, których użył). Po instalacji fontów
  wystarczy uruchomić go ponownie.
- **Treść się nie zmienia.** Po zapisie skrypt porównuje tekst z oryginałem akapit po
  akapicie; tytuł i podtytuł przechodzą na okładkę. Jedyna zmiana tekstu: etykieta
  pandoca „Table of Contents" → „Spis treści".
- **Dostępność PDF** (sprawdzona na wszystkich 18 plikach):
  - tagi struktury, język pl-PL, tytuł dokumentu w pasku okna;
  - zakładki z nagłówków i nagłówki H1–H3;
  - osadzone fonty;
  - zestaw znaków jako obraz z tekstem alternatywnym; ozdobniki (sygnet, kreski,
    tło okładki) jako artefakty;
  - okładka i pas tytułowy złożone bez tabel, więc czytnik ekranu nie ogłasza ich jako
    tabeli;
  - tabele treści z wierszem nagłówka, powtarzanym na kolejnych stronach.
- **Uwagi do treści** — skrypt ich nie poprawia, tylko je zgłasza:
  - znaczniki redakcyjne ([DO UZUPEŁNIENIA], [KONTROLA EKSPERTA], [DATA AUTORYZACJI]…);
  - liczbę miejsc [DOSTOSUJ] (w Kodeksie są częścią wzoru);
  - numer wersji w podtytule albo metryczce niezgodny z nazwą pliku.
- **Do publikacji** — opcja `--publikacja`:
  - zapisuje pliki pod nazwą bez oznaczenia wersji, np.
    `P1_Kodeks_Rownosci_i_Inkluzywnosci.docx` (PDF dostaje tę samą nazwę);
  - takie pliki można od razu wgrać w Media; wtyczka przypisze je do publikacji po kodzie
    (rozdz. 6, „Pola publikacji");
  - dokument, w którym zostały znaczniki redakcyjne albo niezgodny numer wersji, jest
    wstrzymywany (kod wyjścia 1).
- **Eksport PDF** — każdy plik w osobnym procesie Worda, z limitem czasu. Skrypt zamyka
  wyłącznie instancje Worda uruchomione przez siebie (automatyzacja), więc Word otwarty
  w tym czasie przez użytkownika jest bezpieczny.

---

## 10. Zgoda na pliki cookie i GA4

Okno zgody pojawia się **tylko wtedy, gdy ustawiono identyfikator GA4**. Bez niego
serwis nie ustawia żadnych plików analitycznych, więc pytanie o zgodę byłoby
pytaniem bez przedmiotu.

**Przed decyzją użytkownika do dokumentu nie trafia żaden skrypt Google** — nie ma
znacznika `<script src>`, nie ma `preconnect`, nie ma żądania sieciowego i nie
powstaje żaden plik cookie. Sprawdzenie: zakładka **Sieć** w narzędziach
deweloperskich, filtr `google` — lista musi być pusta.

Oba przyciski mają **identyczną klasę wizualną**: ten sam kontur, to samo tło, tę samą
wagę i wielkość tekstu, tę samą wysokość. Wymaga tego kryterium odbioru
„okno zgody z równorzędnymi przyciskami".

Zmiana decyzji: odnośnik z atrybutem `data-sd-consent-reset` — jest już wstawiony
na stronie polityki plików cookie.

### Trzy zdarzenia wymagane umową

| Zdarzenie | Kiedy | Wskaźnik projektu |
|---|---|---|
| `uzytkownik_unikalny` | pierwsza wizyta w danej przeglądarce | min. 2 000 unikalnych użytkowników |
| `pobranie_publikacji` | kliknięcie pliku PDF/EPUB/DOCX | min. 1 000 pobrań |
| `wyslanie_zgloszenia` | wejście na stronę podziękowania z `?zgloszenie=ok` | liczba zgłoszeń |

Zdarzenie wysłania zgłoszenia liczy się **po stronie serwera** — dopiero po
faktycznym zapisaniu zgłoszenia następuje przekierowanie z tym parametrem.
Liczenie na zdarzeniu `submit` formularza zawyżałoby wynik o próby nieudane.

---

## 11. Bezpieczeństwo

| Warstwa | Rozwiązanie |
|---|---|
| Szyfrowanie w transmisji | HTTPS po stronie hostingu |
| Szyfrowanie w spoczynku | AEAD, klucz poza bazą danych |
| Ochrona formularza | nonce, pułapka na roboty, pomiar czasu wypełniania, limit z jednego źródła, reCAPTCHA v3 weryfikowana **po stronie serwera** |
| Adres IP | **nie jest zapisywany**; do limitowania służy skrót HMAC z solą |
| Nagłówki HTTP | `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options`, `Permissions-Policy` |
| XML-RPC | wyłączony |
| Wersja WordPressa | usunięta z `<head>` |
| Zgłoszenia w REST API | niedostępne (`show_in_rest => false`) |

### 11a. Podmioty podprzetwarzające

Umowa powierzenia (§ 4 ust. 2) wymaga prowadzenia aktualnej listy i przekazywania jej
Administratorowi; o każdej zmianie informuje się z wyprzedzeniem **co najmniej 14 dni**
(§ 4 ust. 3).

| Podmiot | Usługa | Jakie dane | Lokalizacja / podstawa transferu | Kiedy |
|---|---|---|---|---|
| dostawca hostingu — *do uzupełnienia po wyborze przez Zamawiającego* | serwer, baza, kopie zapasowe, WAF | wszystkie dane serwisu, w tym zaszyfrowane zgłoszenia | musi być w EOG (§ 3 ust. 2) | zawsze |
| Google Ireland Ltd (Google Analytics 4) | statystyki odwiedzin | identyfikator przeglądarki, dane o odwiedzinach | EOG; transfer do Google LLC — EU-US Data Privacy Framework | **wyłącznie po zgodzie** w oknie cookie |
| Google Ireland Ltd (reCAPTCHA v3) | ochrona formularza przed robotami | dane urządzenia i przeglądarki, adres IP | jw. | tylko na stronie z formularzem |
| dostawca poczty wychodzącej — *do uzupełnienia* | potwierdzenia i powiadomienia e-mail | adres e-mail, imię, sygnatura (bez treści zgłoszenia) | EOG | przy każdym zgłoszeniu |

Serwis nie ładuje czcionek ani skryptów z innych domen; wszystkie pliki, w tym
zestaw znaków, są serwowane z serwera projektu.

### 11b. Naruszenie ochrony danych — procedura

Umowa powierzenia § 5: zgłoszenie Administratorowi **bez zbędnej zwłoki, najpóźniej
w 24 godziny** od stwierdzenia — telefonicznie i pisemnie na adres z Załącznika nr 7.

1. Ogranicz skutki: odetnij dostęp (zmiana haseł, zablokowanie konta, tryb serwisowy),
   zabezpiecz logi serwera i eksport rejestru czynności (**Zgłoszenia → Rejestr czynności → Pobierz CSV**).
2. Ustal: co się stało, których zgłoszeń i ilu osób dotyczy, od kiedy.
3. Zgłoś Administratorowi (§ 5 ust. 2): opis naruszenia, kategorie i przybliżona liczba
   osób i wpisów, możliwe konsekwencje, podjęte i proponowane środki, osoba kontaktowa.
4. Pomóż Administratorowi w zgłoszeniu do Prezesa UODO — on ma na to **72 godziny**.
5. Po zdarzeniu: wpis do dokumentacji, zmiana klucza szyfrowania tylko razem
   z odszyfrowaniem i ponownym zaszyfrowaniem danych (inaczej utracisz dostęp do zgłoszeń).

Zaszyfrowanie treści zgłoszeń ogranicza skutki wycieku bazy danych: bez klucza
z `wp-config.php` treść jest nieczytelna. Wyciek bazy **razem** z `wp-config.php`
jest pełnym naruszeniem.

### 11c. Konfiguracja produkcji — lista kontrolna

Etap 6 umowy obejmuje konfigurację zapory aplikacyjnej. Te ustawienia wykonuje się
w panelu hostingu i w `wp-config.php` — nie ma ich w kodzie motywu ani wtyczki.

| Obszar | Ustawienie |
|---|---|
| HTTPS | certyfikat, przekierowanie całego ruchu na HTTPS, nagłówek `Strict-Transport-Security: max-age=31536000` (ustawia serwer albo WAF — aplikacja go nie wysyła) |
| WAF | reguły OWASP włączone; ograniczenie prób logowania do `wp-login.php`; blokada `xmlrpc.php`; blokada wykonywania PHP w `wp-content/uploads` |
| Logowanie do panelu | wtyczka **Two Factor** (autor: WordPress.org Contributors) — drugi składnik dla każdego administratora i opiekuna zgłoszeń; konta wyłącznie imienne. Test „Konta imienne i uwierzytelnianie dwuskładnikowe" w **Narzędzia → Stan witryny** pokazuje, kto jeszcze nie ma 2FA |
| Hosting i baza danych | 2FA do panelu hostingu i dostępu do bazy (u dostawcy) |
| `wp-config.php` | `SDC_ENCRYPTION_KEY`; `DISALLOW_FILE_EDIT` = `true`; `WP_DEBUG` = `false`; `DISABLE_WP_CRON` = `true` **dopiero po** ustawieniu crona systemowego w panelu hostingu (co 5–15 min wywołanie `https://…/wp-cron.php`) — bez niego nie działa automatyczne usuwanie zgłoszeń po okresie retencji |
| Kopie zapasowe | codziennie, poza serwerem, retencja zgodna z polityką Zamawiającego; w kopii `wp-config.php` (rozdz. 13) |
| Monitoring | dostępność serwisu (np. co 5 minut) i powiadomienia — dane do raportu miesięcznego |
| Aktualizacje | automatyczne aktualizacje bezpieczeństwa WordPressa; wtyczki — przegląd co tydzień |

### reCAPTCHA a dostępność

Wybrano **v3 (niewidoczną)**, nie v2. Zagadka obrazkowa jest barierą dla osób
niewidomych i osób z trudnościami poznawczymi, a serwis ma spełniać WCAG 2.1 AA.

Gdy JavaScript jest wyłączony albo usługa Google nie odpowiada, token nie powstaje.
Zgłoszenie przechodzi wtedy przez pułapkę i pomiar czasu, a w panelu dostaje
znacznik **„Do weryfikacji ręcznej"** zamiast być odrzucone. Osoba szukająca pomocy
nie może zostać odcięta przez awarię cudzego serwisu.

---

## 12. Wydajność

Zrealizowane założenia dla Lighthouse Performance ≥ 90 na urządzeniu mobilnym:

- brak webfontów i brak zasobów z domen trzecich w krytycznej ścieżce;
- jeden arkusz stylów motywu; style bloków rdzenia ładowane **tylko dla bloków
  faktycznie użytych** na danej stronie (`should_load_separate_core_block_assets`);
- wszystkie skrypty z atrybutem `defer`, żaden nie blokuje pierwszego renderowania;
- zestaw znaków z `srcset` i `fetchpriority="high"` — jest w pierwszym ekranie,
  więc nie może ładować się leniwie;
- pierwszy obraz w treści wyłączony z leniwego ładowania (`wp_omit_loading_attr_threshold`);
- wyłączony skrypt emoji i nieużywane znaczniki `<head>`.

Pomiar na środowisku deweloperskim: **8–9 żądań, ok. 190–210 kB na podstronę**
(bez kompresji). Na produkcji z gzip/brotli i OPcache wartości będą wyraźnie niższe.

### Do zrobienia na hostingu produkcyjnym

1. Włącz kompresję brotli albo gzip.
2. Włącz OPcache.
3. Ustaw nagłówki `Cache-Control` dla `/wp-content/` (rok, z wersjonowaniem w adresie).
4. Włącz cache stron (na poziomie serwera albo wtyczki hostingu) **z wykluczeniem**
   `/potrzebuje-pomocy/` i `/dziekujemy/`.
5. Wyłącz `WP_CRON` i wywołuj `wp-cron.php` z harmonogramu systemowego.

---

## 13. Kopia zapasowa i test odtworzenia

Umowa wymaga przeprowadzenia **testowego odtworzenia kopii** i protokołu.

Kopia musi obejmować:

- bazę danych (w tym tabelę `{prefix}sdc_audit`),
- `wp-content/uploads` (pliki publikacji),
- katalogi motywu i wtyczki,
- **`wp-config.php` z kluczem szyfrowania** — bez niego odtworzona baza zawiera
  zgłoszenia, których nie da się odczytać.

Procedura testu: odtwórz kopię na środowisku zapasowym, zaloguj się, otwórz jedno
zgłoszenie i potwierdź, że treść się odszyfrowuje. Zrzut ekranu z odszyfrowaną
treścią jest dowodem do protokołu.

> Danych produkcyjnych nie wolno przenosić na środowisko testowe bez wcześniejszej
> anonimizacji (§ 3 ust. 1 umowy powierzenia).

---

## 13a. Dostępność — jak sprawdzać

Umowa (Załącznik nr 2) wymaga zgodności z **WCAG 2.1 AA dla wszystkich szablonów**,
a dowodem odbiorowym jest raport niezależnego audytora bez uwag krytycznych.
Przed audytem serwis został sprawdzony samodzielnie — wynik i metoda:
[RAPORT-WCAG-2.1-AA.md](RAPORT-WCAG-2.1-AA.md).

Zestaw testów jest w repozytorium i uruchamia się jedną komendą:

```bash
pip install playwright && python -m playwright install chromium
curl -o tests/wcag/axe.min.js https://cdnjs.cloudflare.com/ajax/libs/axe-core/4.11.1/axe.min.js
SD_BASE_URL=https://adres-serwisu python tests/wcag/wcag_test.py
```

Sprawdza każdą stronę serwisu (PL, UA, EN) regułami axe-core dla WCAG 2.1 A i AA
na dwóch szerokościach, przechodzi całą stronę klawiaturą, kontroluje zawijanie
przy 320 px, powiększenie 200 %, odstępy w tekście, strukturę nagłówków i obszarów.
Drugi skrypt (`interactions.py`) obsługuje stany: menu na telefonie, formularz
z błędami, wynik samooceny, okno zgody, tryb wysokiego kontrastu.

To jest także narzędzie do **comiesięcznego monitoringu dostępności** wymaganego
w okresie utrzymania: wynik `reguły WCAG naruszone (axe): 0` można wkleić do
miesięcznego raportu. Testy automatyczne nie zastępują audytu — nie ocenią
sensowności tekstów alternatywnych ani pracy z czytnikiem ekranu.

Po audycie zewnętrznym: uwagi usuwa się w terminie z § 5 ust. 4 umowy, a wynik
wpisuje do strony **Deklaracja dostępności** (projekt treści jest już na stronie,
pola do uzupełnienia oznaczono).

### Panel ułatwień dostępu

Pływający przycisk z ikoną postaci (prawa krawędź ekranu; w kolejności klawiatury
zaraz po „Przejdź do treści") otwiera okno z ustawieniami: wielkość tekstu (do 160 %),
kontrast czarno-biały i żółty na czarnym, wyróżnienie odnośników, czytelna czcionka
i odstępy (wartości z WCAG 1.4.12), tryb czytania, linijka i maska do czytania, duży
kursor, zatrzymanie animacji, czytanie na głos (strona albo zaznaczony fragment,
z wyróżnieniem czytanego akapitu), przywrócenie ustawień, odnośniki do deklaracji
dostępności i tekstu w prostym języku. Teksty panelu i język mowy — PL, UA, EN.

- **Własny kod** (`includes/a11y-toolbar.php`, `assets/js/a11y-toolbar.js`,
  `assets/css/a11y-toolbar.css`), nie nakładka firmy trzeciej: żaden skrypt z cudzego
  serwera na stronie z formularzem zgłoszeń, brak wpływu na Lighthouse. Czytanie na
  głos korzysta z syntezatora mowy przeglądarki — tekst nie opuszcza urządzenia.
- **Panel nie jest warunkiem zgodności** — serwis spełnia WCAG 2.1 AA bez niego; to
  udogodnienie dla osób słabowidzących, z dysleksją, z trudnościami w czytaniu.
- **Zapis ustawień**: `localStorage` „sd-a11y", tylko po wyborze opcji przez
  użytkownika, nic nie trafia na serwer — zapis niezbędny do usługi, o którą prosi
  użytkownik, więc bez zgody. Warto dopisać go do polityki plików cookie (treść
  należy do Zamawiającego).
- **Oznakowanie**: tryby podmieniają zmienne palety, a nie nakładają filtrów na całą
  stronę — pas znaków FE i ramki z zestawem znaków zostają białe i pełnokolorowe;
  nazwa w logo ma rozmiar w px, więc powiększenie tekstu nie sprawi, że logo
  przerośnie flagę UE (punkt 6a listy kontrolnej).
- Sprawdzone na 10 stronach przy 1440 i 375 px w każdym trybie: kontrast tekstu,
  brak przewijania w bok, pas znaków; okno — obsługa klawiaturą (Enter, Tab,
  Escape z powrotem fokusu), `role="dialog"`, `aria-pressed`, `aria-expanded`.
- Wyłączenie (dla programisty): `add_filter( 'sdc_a11y_toolbar_enabled', '__return_false' );`.

---

## 14. Kontrola przed odbiorem

**Narzędzia → Stan witryny** zawiera pięć testów projektu:

| Test | Sprawdza |
|---|---|
| Szyfrowanie zgłoszeń | obecność klucza i praktyczną próbę zaszyfrowania i odszyfrowania danych kontrolnych |
| Oznakowanie projektu | komplet plików zestawu znaków i obrazu Open Graph |
| Retencja i rejestr | istnienie tabeli rejestru i zaplanowanie zadania cyklicznego |
| Dokumenty RODO | obecność klauzuli i opublikowanych polityk |
| Konta imienne i uwierzytelnianie dwuskładnikowe | brak kont o nazwach wspólnych (`admin`, `redakcja`…) i włączony drugi składnik logowania u administratorów i opiekunów zgłoszeń (umowa powierzenia § 3 ust. 1) |

Wszystkie pięć musi być zielonych przed podpisaniem protokołu.
