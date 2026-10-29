=== STOP DYSKRYMINACJI — moduł podstawowy ===
Contributors: Filip Kucia
Tags: dostępność, wcag, rodo, formularz, oznakowanie
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Funkcje platformy „STOP DYSKRYMINACJI": typy treści, moduł zgłoszeniowy
z szyfrowaniem, obowiązkowe oznakowanie FE/RP/UE/herb, zgoda na pliki cookie,
GA4 z trzema zdarzeniami i narzędzia dla pracodawców.

== Description ==

Wtyczka projektu nr FEWP.06.10-IP.01-0136/25, dofinansowanego przez Unię
Europejską ze środków Europejskiego Funduszu Społecznego Plus w ramach programu
Fundusze Europejskie dla Wielkopolski 2021–2027.

Wszystkie funkcje objęte wymaganiami umowy są w tej wtyczce, a nie w motywie —
dzięki temu przetrwają zmianę albo awarię motywu:

* obowiązkowe oznakowanie FE / RP / UE / herb Województwa Wielkopolskiego,
* moduł zgłoszeniowy „Potrzebuję pomocy" z szyfrowaniem treści w spoczynku,
* rejestr czynności przetwarzania i automatyczna retencja zgłoszeń,
* okno zgody na pliki cookie z równorzędnymi przyciskami,
* Google Analytics 4 uruchamiane dopiero po uzyskaniu zgody,
* typy treści: publikacje, dobre praktyki, instytucje pomocowe, zgłoszenia,
* narzędzie samooceny dla pracodawcy i nawigator instytucji.

= Bloki =

Zestaw znaków FE/RP/UE/herb, formularz „Potrzebuję pomocy", samoocena firmy,
pobrania publikacji, formaty publikacji, filtry publikacji, nawigator instytucji,
udostępnianie, ścieżka nawigacji, czas czytania, data aktualizacji.

== Installation ==

1. Wgraj wtyczkę przez **Wtyczki → Dodaj nową → Wyślij wtyczkę na serwer**.
2. Włącz ją.
3. **Dodaj klucz szyfrowania do wp-config.php** — gotową linię znajdziesz
   w **Ustawienia → STOP DYSKRYMINACJI**. Bez tego kroku moduł zgłoszeniowy
   celowo nie przyjmuje zgłoszeń.
4. Uzupełnij pozostałe ustawienia: czas odpowiedzi, retencja, klauzula RODO,
   identyfikator GA4, klucze reCAPTCHA.
5. Sprawdź **Narzędzia → Stan witryny** — cztery testy projektu muszą być zielone.

Pełny opis: DOKUMENTACJA-WDROZENIOWA.md w repozytorium projektu.

== Frequently Asked Questions ==

= Dlaczego formularz nie przyjmuje zgłoszeń? =

Brakuje stałej SDC_ENCRYPTION_KEY w wp-config.php. Opis sytuacji może zawierać
dane szczególnych kategorii w rozumieniu art. 9 RODO, więc moduł nie zapisuje
niczego, dopóki nie może tego zaszyfrować. Użytkownik widzi w tym czasie
alternatywne drogi kontaktu.

= Gdzie trzymany jest klucz szyfrowania? =

W wp-config.php, nigdy w bazie danych. Gdyby był w tabeli opcji, jeden zrzut
bazy zawierałby i szyfrogram, i klucz do niego.

= Co się stanie, jeśli zgubię klucz? =

Zaszyfrowanych zgłoszeń nie da się odzyskać. To właściwość szyfrowania, nie
usterka. Zapisz klucz w menedżerze haseł przed uruchomieniem produkcyjnym.

= Dlaczego nie widzę okna zgody na pliki cookie? =

Pojawia się tylko wtedy, gdy ustawiono identyfikator GA4. Bez niego serwis nie
ustawia żadnych plików analitycznych, więc nie ma o co pytać.

= Czy wtyczka wysyła dane na zewnątrz? =

Tylko w dwóch przypadkach i tylko wtedy, gdy sam je włączysz: weryfikacja tokenu
reCAPTCHA u Google oraz Google Analytics 4 po uzyskaniu zgody użytkownika.
Adres IP nie jest nigdzie zapisywany.

== Changelog ==

= 1.0.5 =
* Panel ułatwień dostępu: pływający przycisk z wielkością tekstu, kontrastem czarno-białym i żółtym na czarnym, wyróżnieniem odnośników, czytelną czcionką i odstępami, trybem czytania, linijką i maską do czytania, dużym kursorem, zatrzymaniem animacji i czytaniem na głos (syntezator mowy przeglądarki). Bez skryptów firm trzecich; ustawienia tylko w przeglądarce użytkownika.

= 1.0.4 =
* Logo projektu „Dymek równości" (blok „Logo serwisu"): sygnet SVG i nazwa w nagłówku; sygnet nigdy nie jest wyższy od flagi UE w pasku znaków.
* Ikony witryny (favicon SVG i PNG, ikona ekranu startowego) — dopóki nie ustawiono własnej ikony w WordPressie.
* Kolor serii publikacji: pole w edytorze, domyślnie według typu (Kodeks — morski, poradniki — ceglany, kompendium — zielony), klasa sd-seria-<kolor> na stronie i kartach publikacji.

= 1.0.3 =
* Pliki publikacji przypisywane same po nazwie: wgrane w Media → Dodaj nowy plik trafiają do publikacji według kodu (np. P4_…), adresu albo tytułu; format według rozszerzenia.
* Publikacje → Pliki do pobrania: zestawienie plików wszystkich publikacji i przypisanie plików wgranych wcześniej.
* Nazwy wgrywanych dokumentów (PDF, EPUB, DOCX) zapisywane małymi literami, bez polskich znaków i spacji.

= 1.0.2 =
* Edytor publikacji: panel „Pliki do pobrania" — wybór albo wgranie plików PDF, EPUB i DOCX, które blok pobierania pokazuje jako przyciski.
* Biblioteka mediów przyjmuje pliki EPUB.

= 1.0.1 =
* Ustawienia: pola kluczy reCAPTCHA bez autouzupełniania przeglądarki; wartość, która nie jest kluczem (np. adres e-mail albo hasło), nie zostaje zapisana.

= 1.0.0 =
* Pierwsze wydanie.
