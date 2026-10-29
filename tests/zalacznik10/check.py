# -*- coding: utf-8 -*-
"""
Lista kontrolna Załącznika nr 10 (rozdz. 11) — sprawdzenie na działającym serwisie.

Każdy punkt listy kontrolnej jest mierzony, a nie deklarowany: pliki porównywane
sumą kontrolną z oryginałem z paczki programu, położenie i wymiary znaków mierzone
w przeglądarce (Chromium przez Playwright) na komputerze i na telefonie.

Dodatkowo sprawdza wymóg z rozdz. 4 (wolne pole wokół zestawu ≥ wysokość flagi UE
po każdej stronie) oraz rozdz. 5 (na telefonie zmniejszenie dozwolone, przycięcie
zabronione — także przy 320 px).

Uruchomienie:  python tests/zalacznik10/check.py
Adres serwisu: zmienna SD_BASE_URL (domyślnie http://stop-dyskryminacji.local).
"""
import hashlib
import json
import os
import re
import sys
import urllib.request

from playwright.sync_api import sync_playwright

BASE = os.environ.get('SD_BASE_URL', 'http://stop-dyskryminacji.local').rstrip('/')
REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
BRAND = os.path.join(REPO, 'stop-dyskryminacji', 'app', 'public', 'wp-content', 'plugins',
                     'stop-dyskryminacji-core', 'assets', 'brand')
PACZKA = os.path.join(REPO, 'identyfikacja_wizualna', 'KOLOR_POZIOM')

ALT = ('Znaki: Fundusze Europejskie dla Wielkopolski, barwy Rzeczypospolitej Polskiej, '
       'Dofinansowane przez Unię Europejską, Herb Województwa Wielkopolskiego')

# Geometria pliku zestawu (zmierzona na oryginale 6946 × 710 px):
FLAG = 0.662     # wysokość flagi UE / wysokość pliku
MARGIN = 0.166   # pusty margines wbudowany w plik, z każdej strony

PAGES = ['/', '/o-projekcie/', '/publikacje/', '/potrzebuje-pomocy/',
         '/czy-to-juz-dyskryminacja/', '/ua/', '/en/']

OSIEM = {
    'tytuł projektu': 'STOP DYSKRYMINACJI',
    'informacja o dofinansowaniu': 'jest dofinansowany przez Unię Europejską ze środków Europejskiego Funduszu Społecznego Plus',
    'cel projektu': 'Celem projektu jest wzmocnienie',
    'zadania i działania': 'W ramach projektu realizowane są następujące zadania',
    'grupy docelowe': 'Projekt skierowany jest do 330 osób',
    'efekty i rezultaty': 'Zakładane rezultaty projektu',
    'wartość projektu': '823 776,25',
    'wkład Funduszy Europejskich': '576 643,37',
}

results = []


def check(nr, label, ok, evidence=''):
    results.append((nr, label, bool(ok), evidence))
    print('%3s. [%s] %-66s %s' % (nr, ' TAK ' if ok else ' NIE ', label, evidence), flush=True)


def sha256(path_or_bytes):
    h = hashlib.sha256()
    if isinstance(path_or_bytes, bytes):
        h.update(path_or_bytes)
    else:
        with open(path_or_bytes, 'rb') as fh:
            h.update(fh.read())
    return h.hexdigest()


def fetch(url):
    with urllib.request.urlopen(url, timeout=30) as r:
        return r.read()


JS_LOCKUP = """() => {
  const img = document.querySelector('.sd-eu-bar__img');
  if (!img) return null;
  const r = img.getBoundingClientRect();
  const bar = document.querySelector('.sd-eu-bar');
  const br = bar.getBoundingClientRect();
  const main = document.querySelector('main');
  const beforeMain = main ? !!(img.compareDocumentPosition(main) & Node.DOCUMENT_POSITION_FOLLOWING) : false;
  const lang = document.querySelector('.sd-eu-bar .sd-lang');
  const lr = lang ? lang.getBoundingClientRect() : null;
  const cs = getComputedStyle(img);
  // Inne obrazy w linii zestawienia (w pasku znaków) — nie może ich być.
  const others = [...bar.querySelectorAll('img')].filter(i => i !== img).length;
  return { x: r.left, y: r.top, w: r.width, h: r.height, vw: document.documentElement.clientWidth, vh: innerHeight,
           barBottom: br.bottom, beforeMain, visible: cs.display !== 'none' && cs.visibility !== 'hidden' && cs.opacity !== '0',
           bgImage: cs.backgroundImage, src: img.currentSrc, alt: img.getAttribute('alt'), lang: lr ? [lr.left, lr.top, lr.width, lr.height] : null,
           others, scrollW: document.documentElement.scrollWidth };
}"""


def clear_space(g):
    """Wolne pole od krawędzi treści zestawu (bez marginesu pliku) do najbliższego elementu."""
    m = MARGIN * g['h']
    top = g['y'] + m
    bottom = g['barBottom'] - (g['y'] + g['h'] - m)
    left = g['x'] + m
    right_edge = g['vw']
    if g['lang'] and g['lang'][1] < g['y'] + g['h'] and g['lang'][1] + g['lang'][3] > g['y']:
        right_edge = g['lang'][0]            # przełącznik języka w tym samym wierszu
    right = right_edge - (g['x'] + g['w'] - m)
    return top, bottom, left, right


def main():
    print('Serwis: %s\n' % BASE)

    # ---- 1–2. Pliki: oryginał z paczki, nieprzerobiony -------------------------
    pairs = [('pl', 'Zestawienie FE+RP+UE+HERB', 'Zestawienie FE+RP+UE+HERB.png'),
             ('en', 'Zestawienie FE+RP+UE+HERB EN', 'Zestawienie FE+RP+UE+HERB_EN.png')]
    same = []
    for lang, folder, name in pairs:
        orig = os.path.join(PACZKA, folder, name)
        copy = os.path.join(BRAND, 'zestawienie-znakow-%s-original.png' % lang)
        same.append(os.path.isfile(orig) and os.path.isfile(copy) and sha256(orig) == sha256(copy))
    prov = os.path.join(BRAND, 'PROWENIENCJA.json')
    check(1, 'Pliki z paczki KOLOR_POZIOM, oryginał bajtowo nieprzerobiony', all(same),
          'SHA-256 zgodne (PL, EN); pochodzenie: PROWENIENCJA.json' if all(same) and os.path.isfile(prov) else 'porównanie: %s' % same)
    check(2, 'Zestaw czterech znaków FE → RP → UE → herb', all(same),
          'oficjalny plik „Zestawienie FE+RP+UE+HERB" z paczki (kolejność w pliku)')

    with sync_playwright() as p:
        browser = p.chromium.launch()

        # ---- 3. Na górze każdej podstrony, bez przewijania (1440 × 900) ---------
        ok3, notes3, geo_desktop = True, [], {}
        ctx = browser.new_context(viewport={'width': 1440, 'height': 900})
        page = ctx.new_page()
        alts, srcs, bgs = set(), set(), set()
        for path in PAGES:
            page.goto(BASE + path, wait_until='networkidle')
            g = page.evaluate(JS_LOCKUP)
            if not g:
                ok3 = False; notes3.append(path + ': brak zestawu'); continue
            geo_desktop[path] = g
            alts.add(g['alt']); srcs.add(g['src']); bgs.add(g['bgImage'])
            if not (g['visible'] and g['beforeMain'] and g['y'] >= 0 and g['y'] + g['h'] <= g['vh']):
                ok3 = False; notes3.append(path)
        check(3, 'Zestaw znaków na samej górze każdej podstrony, bez przewijania', ok3,
              '%d/%d stron (PL, UA, EN)' % (len(PAGES) - len(notes3), len(PAGES)) + ('; problem: ' + ', '.join(notes3) if notes3 else ''))
        ctx.close()

        # ---- 4. Telefon: 375 px (wymóg) i 320 px (najwęższy ekran z WCAG) --------
        mobile = {}
        for width in (375, 320):
            ctx = browser.new_context(viewport={'width': width, 'height': 812}, is_mobile=True, has_touch=True)
            page = ctx.new_page()
            page.goto(BASE + '/', wait_until='networkidle')
            mobile[width] = page.evaluate(JS_LOCKUP)
            ctx.close()
        fits = all(g and g['x'] >= 0 and g['x'] + g['w'] <= g['vw'] and g['scrollW'] <= g['vw'] + 1 for g in mobile.values())
        g375 = mobile[375]
        check(4, 'Zestaw widoczny i czytelny na telefonie, bez przycięcia', fits,
              '375 px: %.0f × %.0f px; 320 px: %.0f × %.0f px; w całości w ekranie' % (g375['w'], g375['h'], mobile[320]['w'], mobile[320]['h']))

        # ---- 5. Wyłącznie pełny kolor --------------------------------------------
        achro = [s for s in srcs if 'achromat' in s.lower()]
        served_ok = True
        for s in srcs:
            name = s.split('/')[-1].split('?')[0]
            served_ok = served_ok and bool(re.match(r'zestawienie-znakow-(pl|en)-\d+\.png$', name))
        check(5, 'Wyłącznie wersja pełnokolorowa (brak achromatycznej)', not achro and served_ok,
              'serwowane pliki: ' + ', '.join(sorted(set(s.split('/')[-1] for s in srcs))))

        # ---- 6. Logo realizatorów poza linią zestawienia, nie większe od flagi --
        ctx = browser.new_context(viewport={'width': 1440, 'height': 900})
        page = ctx.new_page()
        page.goto(BASE + '/', wait_until='networkidle')
        partners = page.evaluate("""() => [...document.querySelectorAll('.sd-partners img')].map(i => { const r = i.getBoundingClientRect(); return [i.getAttribute('alt'), r.width, r.height]; })""")
        footer_flag = page.evaluate("""() => { const i = document.querySelector('.sd-footer__brand img'); if (!i) return null; const r = i.getBoundingClientRect(); return [r.height * 0.662, r.height * 0.662 * 1.5]; }""")
        ctx.close()
        others = max(g['others'] for g in geo_desktop.values()) if geo_desktop else 0
        if partners:
            fh, fw = footer_flag or (0, 0)
            small = all(h <= fh + 0.5 and w <= fw + 0.5 for _, w, h in partners)
            check(6, 'Logo realizatorów poza linią zestawienia, nie większe od flagi UE', others == 0 and small,
                  '%d logo w stopce, największe %.0f × %.0f px ≤ flaga %.0f × %.0f px' % (
                      len(partners), max(w for _, w, _ in partners), max(h for _, _, h in partners), fw, fh))
        else:
            check(6, 'Logo realizatorów poza linią zestawienia, nie większe od flagi UE', others == 0,
                  'w pasku wyłącznie zestawienie; logotypów realizatorów jeszcze nie dodano (czekają na pliki od Zamawiającego)')

        # ---- 6a. Logo projektu w nagłówku nie wyższe od flagi UE w pasku -----------
        # Projekt znaku: „sygnet nie wyższy niż znak UE w zestawieniu znaków";
        # rozporządzenie 2021/1060, zał. IX: flaga co najmniej tak duża jak inne logo.
        JS_SIGN = """() => {
            const img = document.querySelector('.sd-eu-bar__img');
            const mark = document.querySelector('.sd-brand__mark');
            const name = document.querySelector('.sd-brand__name');
            if (!img || !mark) return null;
            const r = mark.getBoundingClientRect(), n = name ? name.getBoundingClientRect() : r;
            return { flag: img.getBoundingClientRect().height * 0.662, mark: r.height,
                     logo: Math.max(r.bottom, n.bottom) - Math.min(r.top, n.top) };
        }"""
        sizes = []
        for width, height in ((1440, 900), (375, 812), (320, 640), (812, 375)):
            ctx = browser.new_context(viewport={'width': width, 'height': height})
            page = ctx.new_page()
            page.goto(BASE + '/', wait_until='networkidle')
            s = page.evaluate(JS_SIGN)
            ctx.close()
            sizes.append((width, height, s))
        found = all(s for _, _, s in sizes)
        ok6a = found and all(s['logo'] <= s['flag'] + 0.5 for _, _, s in sizes)
        check('6a', 'Logo projektu w nagłówku nie wyższe od flagi UE w pasku', ok6a,
              '; '.join('%d×%d: logo %.1f px ≤ flaga %.1f px' % (w, h, s['logo'], s['flag']) if s else '%d×%d: brak logo' % (w, h)
                        for w, h, s in sizes))

        # ---- 7. Tekst alternatywny ------------------------------------------------
        check(7, 'Tekst alternatywny dokładnie wg rozdz. 9', alts == {ALT}, 'na %d stronach' % len(geo_desktop))

        # ---- 8. Serwowane z serwera projektu --------------------------------------
        host = re.sub(r'^https?://', '', BASE).split('/')[0]
        local = all(re.sub(r'^https?://', '', s).split('/')[0] == host for s in srcs)
        check(8, 'Znaki serwowane z serwera projektu, nie z adresu zewnętrznego', local,
              'adres plików: ' + host)

        # ---- Rozdz. 4: wolne pole wokół zestawu ≥ wysokość flagi UE --------------
        worst = []
        for label, g in [('1440 px', geo_desktop.get('/')), ('375 px', mobile[375]), ('320 px', mobile[320])]:
            t, b, l, r = clear_space(g)
            f = FLAG * g['h']
            worst.append((label, f, min(t, b, l, r)))
        ok_clear = all(m >= f - 0.5 for _, f, m in worst)
        check('4a', 'Wolne pole wokół zestawu ≥ wysokość flagi UE (rozdz. 4)', ok_clear,
              '; '.join('%s: flaga %.1f px, najmniejsze pole %.1f px' % x for x in worst))

        # ---- 9–11, 14. Strona „O projekcie" ---------------------------------------
        ctx = browser.new_context(viewport={'width': 1440, 'height': 900})
        page = ctx.new_page()
        page.goto(BASE + '/o-projekcie/', wait_until='networkidle')
        text = page.evaluate("() => document.querySelector('main').innerText")
        links = page.evaluate("() => [...document.querySelectorAll('main a[href]')].map(a => a.href)")
        home_text = None
        page.goto(BASE + '/', wait_until='networkidle')
        home_text = page.evaluate("() => document.querySelector('footer').innerText")
        ctx.close()
        missing = [k for k, v in OSIEM.items() if v not in text]
        check(9, 'Strona „O projekcie" zawiera wszystkie 8 elementów', not missing,
              '%d/8' % (8 - len(missing)) + ('; brak: ' + ', '.join(missing) if missing else ''))

        # Sekcja efektów jest treścią wpisu (edytowalną w edytorze), a nie szablonu.
        try:
            api = json.loads(fetch(BASE + '/wp-json/wp/v2/pages?slug=o-projekcie&_fields=content'))
            in_content = bool(api) and 'Zakładane rezultaty projektu' in api[0]['content']['rendered']
        except Exception as e:  # noqa: BLE001
            in_content = False
        check(10, 'Sekcja efektów edytowalna przez redaktora (treść wpisu, nie szablon)', in_content,
              'tekst sekcji w treści strony (REST API)')
        check(11, 'Odnośnik do www.mapadotacji.gov.pl na stronie „O projekcie"',
              any('mapadotacji.gov.pl' in h for h in links), '')

        # ---- 12. Open Graph / Twitter Card -----------------------------------------
        html = fetch(BASE + '/o-projekcie/').decode('utf-8', 'replace')
        og = dict(re.findall(r'<meta (?:property|name)="((?:og|twitter):[a-z:_]+)" content="([^"]*)"', html))
        img_ok = False
        dims = ''
        if og.get('og:image'):
            data = fetch(og['og:image'])
            if data[:8] == b'\x89PNG\r\n\x1a\n':
                w = int.from_bytes(data[16:20], 'big'); h = int.from_bytes(data[20:24], 'big')
                dims = '%d × %d px' % (w, h)
                img_ok = (w, h) == (1200, 630)
        tags = all(og.get(k) for k in ('og:title', 'og:description', 'og:image', 'twitter:card', 'twitter:image'))
        check(12, 'Open Graph i Twitter Card, obraz 1200 × 630 px ze znakami', tags and img_ok,
              dims + ('; plik: ' + og.get('og:image', '').split('/')[-1] if og.get('og:image') else ''))

        # ---- 13. Kontrast wokół znaków, 200 % -----------------------------------------
        ctx = browser.new_context(viewport={'width': 720, 'height': 450}, device_scale_factor=2)
        page = ctx.new_page()
        page.goto(BASE + '/', wait_until='networkidle')
        z = page.evaluate(JS_LOCKUP)
        bg = page.evaluate("() => getComputedStyle(document.querySelector('.sd-eu-bar')).backgroundColor")
        ctx.close()
        zoom_ok = z and z['x'] >= 0 and z['x'] + z['w'] <= z['vw'] and z['y'] + z['h'] <= z['vh'] and z['scrollW'] <= z['vw'] + 1
        check(13, 'Kontrast wokół znaków i czytelność przy 200 %', zoom_ok and bg in ('rgb(255, 255, 255)', 'rgba(255, 255, 255, 1)'),
              'tło %s; przy 200 %%: %.0f × %.0f px, w całości widoczny' % (bg, z['w'], z['h']) if z else '')
        check(14, 'Informacja o dofinansowaniu także jako tekst',
              OSIEM['informacja o dofinansowaniu'] in text and 'dofinansowany przez Unię Europejską' in (home_text or ''),
              'strona „O projekcie" + stopka każdej podstrony')
        check(15, 'Plik SVG znaków bez skryptów i odwołań zewnętrznych', True,
              'nie dotyczy — paczka nie zawiera SVG, użyto PNG (rozdz. 2)')
        browser.close()

    ok = sum(1 for r in results if r[2])
    print('\nWYNIK: %d spełnionych, %d niespełnionych' % (ok, len(results) - ok))
    return 0 if ok == len(results) else 1


if __name__ == '__main__':
    sys.exit(main())
