# STOP DYSKRYMINACJI — platforma internetowa

Motyw blokowy i wtyczka WordPressa dla platformy promującej równe traktowanie
w zatrudnieniu.

Projekt nr FEWP.06.10-IP.01-0136/25, dofinansowany przez Unię Europejską
ze środków Europejskiego Funduszu Społecznego Plus w ramach programu
Fundusze Europejskie dla Wielkopolski 2021–2027.

---

## To repozytorium nie hostuje serwisu

GitHub przechowuje kod i sprawdza go przy każdym wysłaniu. **Nie uruchamia
WordPressa** — nie ma tu PHP ani bazy danych. GitHub Pages serwuje wyłącznie
pliki statyczne, więc nie postawi na sobie tego serwisu.

Serwis działa na hostingu nazwa.pl (PHP i MariaDB). Kod trafia tam z panelu
WordPressa albo przez SFTP — opis niżej i w
[`DOKUMENTACJA-WDROZENIOWA.md`](DOKUMENTACJA-WDROZENIOWA.md).

```
GitHub (kod + kontrola)  ──ZIP w panelu albo SFTP──>  nazwa.pl  ──>  serwis działa
```

---

## Zawartość

| Katalog | Co to jest |
|---|---|
| `stop-dyskryminacji/app/public/wp-content/themes/stop-dyskryminacji/` | motyw blokowy (FSE) — wygląd |
| `stop-dyskryminacji/app/public/wp-content/plugins/stop-dyskryminacji-core/` | wtyczka — funkcje, moduł zgłoszeniowy, oznakowanie |
| `identyfikacja_wizualna/` | oryginalne pliki znaków z paczek programu |
| `DOKUMENTACJA-WDROZENIOWA.md` | instalacja, konfiguracja, bezpieczeństwo, odbiór |
| `INSTRUKCJA-REDAKTORA.md` | obsługa serwisu na co dzień |
| `tests/wcag/` | testy dostępności (axe-core + klawiatura, 320 px, 200 %, odstępy tekstu, stany interaktywne) |
| `tests/zalacznik10/` | lista kontrolna oznakowania z Załącznika nr 10, mierzona na działającym serwisie |
| `tests/links/` | audyt odnośników wewnętrznych (PL, UA, EN) |
| `tests/launch/` | gotowość serwisu: wszystkie adresy z mapy witryny, znaki FE na górze stron, pobieranie P1–P4, formularz pomocy, podpis autorów |
| `tools/` | paczki ZIP (`build_zips.py`), zrzuty ekranu do odbioru (`screenshots.py`), eksport treści z Local na hosting (`export_migration.php`), sygnet i ikony (`build_brand_icons.py`), obrazy podglądu (`build_og_images.py`), okładki i obrazy artykułów zastępcze (`build_covers.py`, `build_article_images.py`), skład plików publikacji DOCX/PDF w identyfikacji (`brand_docs.py`, `docx_to_pdf.ps1`), PDF-y artykułów (`build_article_pdfs.py`), materiały promocyjne do pobrania (`build_promo_materials.py`, `build_materials_page.py`), treści redakcyjne (`editorial_content.py`, `editorial_markdown.mjs`) |

Rdzeń WordPressa, `wp-config.php` i biblioteka mediów **nie są wersjonowane** —
to nie jest kod projektu, a `wp-config.php` zawiera klucz szyfrowania zgłoszeń.

---

## Szybki start lokalnie

```bash
docker compose up -d
```

Serwis: http://localhost:8080. Motyw i wtyczkę włącza się w panelu.

---

## Wgrywanie na serwer

Przy każdym wysłaniu do `main` GitHub Actions sprawdza składnię PHP, poprawność
`theme.json`, brak zapisanego klucza szyfrowania w kodzie i komplet plików
oznakowania ([`.github/workflows/kontrola.yml`](.github/workflows/kontrola.yml)).
Nic nie trafia przy tym na serwer.

Wtyczkę wgrywa się jako ZIP z katalogiem `stop-dyskryminacji-core/`:
**Wtyczki → Dodaj nową → Wyślij wtyczkę na serwer → Zastąp bieżącą wersją
przesłaną**. Motyw analogicznie w **Wygląd → Motywy → Dodaj nowy motyw**, albo
oba katalogi przez SFTP (WinSCP) do `wp-content/plugins/` i `wp-content/themes/`.

Wtyczka idzie przed motywem — szablony motywu odwołują się do jej bloków,
w tym do bloku z obowiązkowym zestawem znaków FE / RP / UE / herb. Nigdy nie
wgrywa się całego `wp-content` z usuwaniem plików spoza paczki: skasowałoby to
z serwera bibliotekę mediów Zamawiającego.

---

## Po pierwszej instalacji

Moduł zgłoszeniowy **celowo nie przyjmuje zgłoszeń**, dopóki w `wp-config.php`
nie ma klucza szyfrowania. Gotową linię generuje panel:
**Ustawienia → STOP DYSKRYMINACJI**.

Opis sytuacji podany w formularzu może zawierać dane szczególnych kategorii
w rozumieniu art. 9 RODO. Utrata jednego zgłoszenia jest mniejszą szkodą niż
zapisanie takich danych otwartym tekstem.

Klucz nie trafia do repozytorium ani do bazy danych. Gdyby był w bazie, jeden
jej zrzut zawierałby i szyfrogram, i klucz do niego.

---

## Licencja

GPL-2.0-or-later — zgodnie z licencją WordPressa.

Pliki w `identyfikacja_wizualna/` pochodzą z paczek programu Fundusze Europejskie
dla Wielkopolski i podlegają zasadom z Księgi Tożsamości Wizualnej marki
Fundusze Europejskie 2021–2027. Nie wolno ich odtwarzać ani modyfikować.
