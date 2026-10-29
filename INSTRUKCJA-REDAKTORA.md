# Instrukcja redaktora — platforma „STOP DYSKRYMINACJI"

Ta instrukcja jest dla osób, które będą prowadzić serwis na co dzień.
Nie trzeba znać się na programowaniu — wszystko robi się w panelu WordPressa.

---

## 1. Logowanie

Adres panelu: **adres-serwisu/wp-admin**

Jeżeli masz rolę **Opiekun zgłoszeń**, widzisz wyłącznie zgłoszenia z formularza.
Jeżeli masz rolę **Redaktor**, widzisz treści, ale nie widzisz zgłoszeń.
Ten podział jest celowy: zgłoszenia zawierają dane wrażliwe i dostęp do nich
ma tylko tyle osób, ile trzeba.

---

## 2. Codzienna praca z treścią

### Gdzie co jest

| W menu po lewej | Co tam trzymamy |
|---|---|
| **Artykuły** | 12 artykułów bazy wiedzy |
| **Publikacje** | Kodeks, poradniki, kompendium — razem z plikami do pobrania |
| **Dobre praktyki** | baza praktyk firm, z filtrami |
| **Instytucje pomocowe** | wykaz instytucji do nawigatora |
| **Strony** | strony stałe: O projekcie, Potrzebuję pomocy, polityki |
| **Zgłoszenia** | sprawy z formularza (tylko dla uprawnionych) |

### Dodawanie artykułu

1. **Artykuły → Dodaj nowy artykuł**.
2. Wpisz tytuł. Ma mówić, o czym jest tekst — unikaj tytułów-zagadek.
3. Napisz treść, dzieląc ją **nagłówkami drugiego poziomu (H2)**.
4. Wypełnij **Zajawkę** w panelu bocznym (2–3 zdania). To ona pokazuje się
   na kartach i w podglądzie przy udostępnianiu.
5. Ustaw **Obraz wyróżniający** i koniecznie opisz go (patrz punkt 5).
6. Przypisz **Kategorię**, **Przesłankę** i **Grupę odbiorców**.
7. **Opublikuj**.

> Jeżeli artykuł ma **trzy lub więcej nagłówków H2**, serwis sam zbuduje nad nim
> spis treści „Na tej stronie". Nie trzeba go wpisywać ręcznie. Przy mniejszej
> liczbie sekcji spis się nie pojawia — bo tylko by przeszkadzał.

### Dodawanie publikacji

1. **Publikacje → Dodaj nową publikację**.
2. Tytuł, krótki opis w treści, zajawka.
3. **Obraz wyróżniający** = okładka publikacji.
4. Panel boczny → zakładka **Publikacja** → sekcja **Pliki do pobrania**. Przy formacie
   kliknij **Wybierz plik** → w oknie mediów **Wyślij pliki** (albo wskaż plik z biblioteki)
   → **Wybierz**. Każda publikacja ma mieć **PDF** i **EPUB**, Kodeks także **wersję
   edytowalną DOCX**. Na koniec **Zapisz**. **Zmień plik** podmienia plik, **Usuń plik**
   odpina go od publikacji (sam plik zostaje w Mediach).
5. Przypisz **Typ publikacji** (Kodeks / Poradnik / Kompendium). Od niego zależy kolor
   publikacji na stronie (Kodeks — morski, poradniki — ceglany, kompendium — zielony);
   inny kolor wybierzesz w panelu bocznym **Kolor serii** (np. ochra).

Przyciski pobierania pojawią się same, z formatem i rozmiarem pliku. Każde pobranie jest
liczone w statystykach — to jeden ze wskaźników rozliczanych w projekcie.

### Wiele plików naraz

1. Nazwij pliki tak, żeby zaczynały się od **kodu publikacji** (`P4_…`), jej **adresu**
   (`kompendium-dobrych-praktyk…`) albo **tytułu** (`Kompendium dobrych praktyk…`).
   Wielkość liter, polskie znaki, spacje i podkreślniki nie mają znaczenia; po kodzie albo
   nazwie może być dopisek, np. `P4_kompendium_2026.pdf`.
2. **Media → Dodaj nowy plik** → przeciągnij wszystkie pliki naraz.
3. **Publikacje → Pliki do pobrania** — zestawienie wszystkich publikacji: co jest,
   czego brakuje.

Format wynika z rozszerzenia (PDF, EPUB, DOCX). Nowszy plik zastępuje poprzedni —
poprzedni zostaje w Mediach. Kod wpisujesz raz, w publikacji: panel **Pliki do pobrania**
→ pole **Kod w nazwach plików**. Pliki wgrane, zanim nadano im właściwe nazwy albo
wpisano kod, przypisuje przycisk **Przypisz pliki z biblioteki mediów** na stronie
zestawienia. Plików wgranych w edytorze innej treści automat nie rusza.

Zanim wgrasz plik:

- **nazwa pliku** staje się częścią adresu do pobrania — bez oznaczeń roboczych
  (`v0-1`, `wersja robocza`); wielkie litery, polskie znaki i spacje wtyczka zamienia
  sama: `Kompendium dobrych praktyk.pdf` → `kompendium-dobrych-praktyk.pdf`;
- **dostępność**: w Wordzie **Recenzja → Sprawdź ułatwienia dostępu**; PDF zapisuj
  z Worda z opcją **Tagi struktury dokumentu dla ułatwień dostępu** (Plik → Zapisz jako
  → PDF → Opcje);
- **publikacje projektu (P1–P4) i artykuły** w DOCX i PDF składa skrypt opisany
  w dokumentacji wdrożeniowej (rozdz. 9). Pliki są już w kolorach serii, z okładką,
  zestawem znaków i tagami dostępności, więc nie poprawiaj ich ręcznie w Wordzie. Po
  zmianach w treści skład powtarza się jednym poleceniem.

### Wersje ukraińska (UA) i angielska (EN)

Język strony wynika z jej adresu: wszystko pod `/ua/` jest po ukraińsku, pod `/en/`
po angielsku, reszta po polsku. Osoba, która wybierze UA albo EN, **zostaje w swojej
sekcji**: menu, przycisk „Potrzebuję pomocy", stopka, ścieżka nawigacji, nazwa
serwisu w nagłówku i strona błędu 404 są w jej języku i prowadzą do stron w tym
języku.

| Co | Gdzie to zmienić |
|---|---|
| Menu UA / EN | **Wygląd → Edytor → Nawigacja → „Меню (UA)" / „Menu (EN)"** |
| Stopka UA / EN | **Wygląd → Edytor → Wzorce → Części szablonu → „Stopka (UA)" / „Stopka (EN)"** |
| Strona 404 UA / EN | **Wygląd → Edytor → Szablony → „Strona 404 (UA)" / „Strona 404 (EN)"** |

**Dodawanie strony po ukraińsku albo po angielsku**

1. **Strony → Dodaj stronę**. Tytuł i treść w danym języku.
2. W panelu bocznym ustaw **Stronę nadrzędną**: „Дискримінація на роботі в Польщі…"
   dla UA albo „Discrimination at work in Poland…" dla EN. To strona nadrzędna
   decyduje o języku — strona ukraińska bez niej dostanie polskie menu i stopkę.
3. Zapisz. Na dole panelu bocznego, w polu **Wersja językowa**, wskaż **polski
   odpowiednik**. Przełącznik PL / UA / EN będzie wtedy przenosił między tymi
   stronami, zamiast na stronę główną sekcji.
4. Dodaj stronę do menu i — jeśli trzeba — do stopki danego języka.

> **Nie tłumacz treści prawnych automatycznie.** Porady o prawie pracy, terminy
> odwołań, klauzula RODO i polityki muszą przetłumaczyć i sprawdzić ludzie —
> pomyłka w liczbie dni na odwołanie ma dla czytelnika realne skutki. Dokument
> dostępny tylko po polsku linkuj z dopiskiem „(польською)" albo „(in Polish)".

**„Prosty język" to nie jest wersja językowa.** Strona `/prosty-jezyk/` to
najważniejsze informacje napisane prostą polszczyzną — dla osób, którym trudno
czytać długie teksty, i dla słabiej znających polski. Dlatego odnośnik do niej
jest w stopce, obok deklaracji dostępności, a nie w przełączniku języków.

---

## 3. Gotowe klocki do wstawiania

W edytorze kliknij **+** i wpisz nazwę. Klocki projektu są w kategorii
**STOP DYSKRYMINACJI**.

| Klocek | Co robi | Gdzie go użyć |
|---|---|---|
| Formularz „Potrzebuję pomocy" | moduł zgłoszeniowy | strona „Potrzebuję pomocy" |
| Samoocena firmy | kwestionariusz dla pracodawcy z wynikiem | strona „Samoocena" |
| Nawigator instytucji pomocowych | wykaz instytucji z filtrem | strona „Gdzie szukać pomocy" |
| Pobrania publikacji | przyciski PDF / EPUB / DOCX | widok publikacji |
| Filtry publikacji | wybór rodzaju, przesłanki, odbiorcy | lista publikacji |
| Udostępnij | przyciski udostępniania | pod artykułem |
| Ścieżka nawigacji | pokazuje, gdzie jest użytkownik | góra podstrony |
| Czas czytania | „5 min czytania" | metryczka artykułu |
| Data aktualizacji | kiedy treść była sprawdzana | koniec strony |
| **Zestaw znaków FE / RP / UE / herb** | **obowiązkowe oznakowanie — nie ruszaj** | nagłówek i stopka |

### Gotowe sekcje

**+ → Wzorce → STOP DYSKRYMINACJI**: nagłówek strony głównej, wybór sytuacji,
ścieżki dla ról, kroki zgłoszenia, publikacje polecane, sekcja dla pracodawcy,
najczęstsze pytania, wezwanie „Potrzebuję pomocy", treść obowiązkowa „O projekcie".

---

## 4. Czego nie wolno ruszać

### Zestaw znaków FE / RP / UE / herb

To pasek z czterema znakami na samej górze każdej podstrony.

**Nie usuwaj go, nie przesuwaj, nie podmieniaj obrazka, nie zmieniaj opisu
alternatywnego.** Blok jest zablokowany, ale gdyby udało się go usunąć, projekt
traci **1 956,47 zł** dofinansowania. Jeżeli pasek zniknie — zgłoś to od razu.

### Treść strony „O projekcie"

Opis projektu jest przepisany co do słowa z załącznika do umowy.
**Nie skracaj go i nie parafrazuj.** Brak tego opisu albo brak w nim informacji
o wsparciu z Unii Europejskiej to **3 912,94 zł** pomniejszenia.

**Jedyna sekcja, którą masz aktualizować**, to „Efekty i rezultaty" — pod koniec
projektu wpisz tam faktycznie osiągnięte wartości zamiast zakładanych.
Edytujesz ją jak każdy inny akapit.

### Klauzula RODO i polityki

Treści pochodzą od Zamawiającego i nie można ich zmieniać bez jego pisemnej zgody.

---

## 5. Dostępność — sześć nawyków

Serwis przechodzi niezależny audyt dostępności. Te nawyki decydują o jego wyniku
bardziej niż cokolwiek po stronie kodu.

**1. Opisuj obrazy.** Po wgraniu obrazu wypełnij **Tekst alternatywny** — napisz,
co obraz przedstawia. Przy braku opisu panel pokaże czerwone ostrzeżenie.
Jeżeli obraz jest czysto dekoracyjny, wpisz pojedynczą spację.

**2. Nie przeskakuj poziomów nagłówków.** Po H2 może być H3, ale nie od razu H4.
Tytuł strony to H1 — nie używaj H1 w treści.

**3. Pisz sensowne teksty odnośników.** „Pobierz poradnik dla świadków" zamiast
„kliknij tutaj". Osoba korzystająca z czytnika ekranu często przegląda samą listę
odnośników, w oderwaniu od reszty zdania.

**4. Nie przekazuj informacji samym kolorem.** „Wymagane pole" musi być napisane,
nie tylko zaznaczone na czerwono.

**5. Pisz prosto.** Krótkie zdania, jedna myśl na akapit. Rozwijaj skróty przy
pierwszym użyciu. Odbiorcami są między innymi osoby, dla których polski nie jest
pierwszym językiem.

**6. Nie wklejaj tekstu jako obrazka.** Skan dokumentu nie jest treścią —
nie da się go powiększyć ani odczytać czytnikiem ekranu.

### Trzy strony, o których warto wiedzieć

- **Mapa strony** (`/mapa-strony/`) buduje się sama z opublikowanych treści —
  nie trzeba jej aktualizować. Jest wymagana, bo do każdej strony musi prowadzić
  więcej niż jedna droga.
- **Deklaracja dostępności** — na stronie jest projekt treści z polami
  „do uzupełnienia". Po audycie zewnętrznym wpisuje się wynik, datę i osobę
  kontaktową. Tego dokumentu szukają w pierwszej kolejności osoby zgłaszające
  problemy z dostępnością.
- **Prosty język** (`/prosty-jezyk/`) — najważniejsze informacje napisane prostą
  polszczyzną. Jeżeli zmienisz zasady zgłaszania sprawy, zmień też tę stronę.

### Panel ułatwień dostępu

Przycisk z ikoną postaci przy prawej krawędzi ekranu otwiera **Ułatwienia dostępu**:
większy tekst, wysoki kontrast, wyróżnione odnośniki, czytelna czcionka, tryb
czytania, linijka i maska do czytania, duży kursor, czytanie na głos. Działa sam —
redaktor niczego nie ustawia. Ustawienia zapamiętuje przeglądarka osoby
odwiedzającej. Panel linkuje do **Deklaracji dostępności** i strony **Prosty język**,
więc obie warto utrzymywać aktualne; w deklaracji można wspomnieć o panelu
w części o dostępnych udogodnieniach. Panel jest dodatkiem: dobre nagłówki, teksty
alternatywne i jasny język (sześć nawyków powyżej) są ważniejsze.

---

## 6. Obsługa zgłoszeń

### Zasady

Zgłoszenia zawierają dane o sytuacji osobistej ludzi. Traktuj je odpowiednio:

- **Każde otwarcie zgłoszenia jest zapisywane w rejestrze** — widać, kto i kiedy
  je czytał. Tak ma być; to wymóg umowy.
- **Nie kopiuj treści poza panel.** Nie przeklejaj jej do arkuszy, komunikatorów
  ani do narzędzi sztucznej inteligencji — to jest wprost zakazane umową.
- **Nie odsyłaj treści zgłoszenia e-mailem.** Powiadomienia zawierają tylko
  sygnaturę i odnośnik do panelu, i tak ma zostać.

### Jak pracować ze zgłoszeniem

1. **Zgłoszenia** → wybierz sprawę po sygnaturze (np. `ZGL-20260917-RCJSTR`).
2. Treść jest odszyfrowywana dopiero przy otwarciu.
3. Ustaw **Status obsługi**: Nowe → W toku → Odpowiedziano → Zamknięte.
4. Odpowiadasz **ze swojej skrzynki**, na adres z zgłoszenia, powołując się
   na sygnaturę.
5. Kolumna **Uwagi** pokazuje „Do weryfikacji ręcznej", jeżeli nie udało się
   potwierdzić, że formularz wypełnił człowiek. Nie znaczy to, że zgłoszenie jest
   fałszywe — przeczytaj je normalnie.

### Gdy ktoś prosi o swoje dane albo o ich usunięcie

Na ekranie zgłoszenia, w panelu po prawej:

- **Eksportuj zgłoszenie (JSON)** — gdy ktoś prosi o kopię swoich danych;
- **Usuń trwale** — gdy ktoś prosi o usunięcie. Operacji nie da się cofnąć.

Obie czynności trafiają do rejestru.

### Automatyczne usuwanie

Zgłoszenia znikają same po okresie retencji ustawionym w ustawieniach
(domyślnie 180 dni). Kolumna **Usunięcie** pokazuje datę i ile dni zostało.
Jeżeli sprawa wymaga dłuższego przechowywania, przenieś ustalenia poza moduł
i zamknij zgłoszenie.

---

## 7. Najczęstsze sytuacje

**Formularz pokazuje „Formularz jest chwilowo niedostępny".**
Brakuje klucza szyfrowania. To zadanie administratora — zgłoś to natychmiast,
bo w tym czasie nikt nie może wysłać zgłoszenia.

**Nie widzę okna zgody na pliki cookie.**
Pojawia się tylko wtedy, gdy ustawiono identyfikator GA4. Bez niego serwis nie
zbiera statystyk, więc nie ma o co pytać.

**Chcę zmienić menu.**
**Wygląd → Edytor → Wzory → Nagłówek**, kliknij menu i edytuj pozycje.

**Chcę zmienić kolory albo wielkość tekstu w całym serwisie.**
**Wygląd → Edytor → Style**. Zmiana działa wszędzie naraz.
Uwaga: kolory zostały dobrane pod wymagany kontrast — po zmianie sprawdź czytelność.

**Publikacja nie pokazuje przycisków pobierania.**
Nie wskazano plików w panelu bocznym edytora publikacji.

**Spis treści „Na tej stronie" się nie pojawia.**
Artykuł ma mniej niż trzy nagłówki H2.

**Zmieniłem/am coś i wygląda źle.**
Każdy wpis i strona ma historię zmian — w panelu bocznym, sekcja **Wersje**.
Da się wrócić do poprzedniej wersji.

---

## 8. Przed publikacją — lista kontrolna

- [ ] Tytuł mówi, o czym jest tekst
- [ ] Zajawka wypełniona (2–3 zdania)
- [ ] Obraz wyróżniający ustawiony i **opisany**
- [ ] Nagłówki po kolei, bez przeskoków
- [ ] Odnośniki mają sensowne nazwy
- [ ] Kategoria, przesłanka i grupa odbiorców przypisane
- [ ] Publikacje: pliki PDF i EPUB wskazane
- [ ] Podgląd na telefonie wygląda poprawnie
- [ ] Pasek ze znakami FE / RP / UE / herb jest na górze strony
