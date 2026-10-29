# Testy dostępności WCAG 2.1 AA

Dwa skrypty sprawdzające serwis w prawdziwej przeglądarce (Chromium przez Playwright).
Służą do kontroli przed audytem zewnętrznym i do comiesięcznego monitoringu
dostępności w okresie utrzymania. **Nie zastępują audytu** — testy automatyczne
nie sprawdzą np. sensowności tekstów alternatywnych ani pracy z czytnikiem ekranu.

| Skrypt | Co sprawdza |
|---|---|
| `wcag_test.py` | każda strona serwisu (PL/UA/EN): axe-core z regułami WCAG 2.1 A i AA na 1440 px i 375 px, przejście Tabem przez całą stronę (widoczny fokus, brak pułapek, element nie zasłonięty), odnośnik „Przejdź do treści", zawijanie przy 320 px, powiększenie 200 %, odstępy w tekście (1.4.12), jeden H1 i nagłówki bez przeskoków, obszary strony, unikalne tytuły, etykieta w nazwie dostępnej (2.5.3), zduplikowane `id` |
| `interactions.py` | stany interaktywne: menu na telefonie z klawiatury (A), formularz z błędami w PL/UA/EN (B), wynik samooceny (C), okno zgody na pliki cookie (D), tryb wysokiego kontrastu Windows (E) |

## Przygotowanie (jednorazowo)

```bash
pip install playwright
python -m playwright install chromium
```

Biblioteka axe-core (Deque Systems, licencja MPL-2.0) nie jest przechowywana
w repozytorium. Przed pierwszym testem pobierz ją do tego katalogu:

```bash
curl -o tests/wcag/axe.min.js https://cdnjs.cloudflare.com/ajax/libs/axe-core/4.11.1/axe.min.js
```

albo wskaż istniejący plik zmienną `SD_AXE_PATH`.

## Uruchomienie

```bash
python tests/wcag/wcag_test.py
```

```bash
python tests/wcag/interactions.py A,B,C,E
```

Adres serwisu podaje zmienna `SD_BASE_URL` (domyślnie `http://stop-dyskryminacji.local`).
Test okna zgody (D) wymaga identyfikatora GA4 w ustawieniach — na kopii testowej
wystarczy fikcyjny `G-TEST000000`; nie ustawiaj go na serwisie produkcyjnym.

Wyniki trafiają do `tests/wcag/results/` (`podsumowanie.txt`, `wyniki.json`).
Oczekiwany wynik: `reguły WCAG naruszone (axe): 0` oraz zero uwag w pozostałych
kategoriach. Test formularza (B) wysyła formularz z celowo błędnymi danymi —
nie powstaje żadne zgłoszenie; wpisane wartości są przez 15 minut przechowywane
tymczasowo, tak jak przy każdym błędzie w formularzu.
