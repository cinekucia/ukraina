# -*- coding: utf-8 -*-
"""
Gotowość serwisu do uruchomienia — kontrola publiczna (bez logowania), mierzona, nie deklarowana.

1. Wszystkie adresy z mapy witryny WordPressa (wp-sitemap.xml) odpowiadają 200.
2. Na każdej z nich na górze strony stoi pełnokolorowy zestaw znaków FE (plik zestawienia,
   nie achromatyczny), z tekstem alternatywnym, przed nagłówkiem serwisu.
3. Na przykładowych szablonach (komputer 1440 × 900 i telefon 375 × 812) zestaw jest w całości
   widoczny bez przewijania.
4. P1–P4: przyciski pobierania i pliki PDF (200, application/pdf, nagłówek %PDF).
5. Formularz „Potrzebuję pomocy” (PL/UA/EN): pola, zgoda, brak komunikatu o wyłączeniu modułu.
6. Podpis autorów na artykułach i publikacjach oraz w ich PDF-ach.

Uruchomienie:  python tests/launch/check_launch.py   (adres: SD_BASE_URL, domyślnie produkcja)
"""
import html
import io
import json
import os
import re
import sys
import urllib.error
import urllib.request

from playwright.sync_api import sync_playwright
from pypdf import PdfReader

BASE = os.environ.get('SD_BASE_URL', 'https://stopdyskryminacji.pl').rstrip('/')
BYLINE = 'Tetiana Wróblewska, Krzysztof Martyniak – ekspert ds. równego traktowania'
ALT_PL = ('Znaki: Fundusze Europejskie dla Wielkopolski, barwy Rzeczypospolitej Polskiej, '
          'Dofinansowane przez Unię Europejską, Herb Województwa Wielkopolskiego')
UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) sd-launch-check'
report = {'pages': [], 'downloads': [], 'forms': [], 'byline': [], 'fold': []}


def get(url, binary=False):
    req = urllib.request.Request(url, headers={'User-Agent': UA})
    try:
        with urllib.request.urlopen(req, timeout=60) as r:
            data = r.read()
            return r.status, r.headers.get('Content-Type', ''), data if binary else data.decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, e.headers.get('Content-Type', ''), b'' if binary else ''


def sitemap_urls():
    urls, todo = [], [BASE + '/wp-sitemap.xml']
    while todo:
        status, _, xml = get(todo.pop())
        for loc in re.findall(r'<loc>([^<]+)</loc>', xml):
            loc = html.unescape(loc)
            (todo if loc.endswith('.xml') else urls).append(loc)
    return sorted(set(urls))


def eu_bar(page_html):
    """Pierwszy obraz strony i jego położenie względem nagłówka serwisu."""
    body = page_html.split('<body', 1)[-1]
    imgs = re.findall(r'<img\b[^>]*>', body)
    first = imgs[0] if imgs else ''
    src = re.search(r'src="([^"]+)"', first)
    alt = re.search(r'alt="([^"]*)"', first)
    nav = body.find('<nav')
    pos = body.find(first) if first else -1
    return (src.group(1) if src else ''), html.unescape(alt.group(1)) if alt else '', 0 <= pos < (nav if nav >= 0 else 10 ** 9)


def main():
    urls = sitemap_urls()
    print('Mapa witryny: %d adresów' % len(urls))
    bad_pages = 0
    for url in urls:
        status, ctype, body = get(url)
        src, alt, before_nav = eu_bar(body) if status == 200 else ('', '', False)
        full_colour = bool(re.search(r'zestawienie-znakow-(pl|en)', src)) and 'achromat' not in src.lower()
        ok = status == 200 and full_colour and before_nav and alt.startswith('Znaki:')
        bad_pages += not ok
        report['pages'].append(dict(url=url, status=status, lockup=src.rsplit('/', 1)[-1], alt_ok=alt.startswith('Znaki:'), first=before_nav, ok=ok))
        if not ok:
            print('  ✗ %s → %s, znaki: %s, alt: %s, przed nawigacją: %s' % (url, status, src.rsplit('/', 1)[-1] or '—', alt[:30] or '—', before_nav))
    print('Strony: %d/%d z odpowiedzią 200 i pełnokolorowym zestawem znaków na górze' % (len(urls) - bad_pages, len(urls)))

    # Widoczność bez przewijania — po jednej stronie z każdego szablonu
    samples = [u for u in urls if u.rstrip('/') == BASE] + \
        [next((u for u in urls if pat in u), None) for pat in ('/o-projekcie/', '/publikacje/', '/artykuly/', '/dobre-praktyki/', '/instytucje/', '/ua/', '/en/', '/potrzebuje-pomocy/')]
    samples = [u for u in dict.fromkeys(samples) if u]
    with sync_playwright() as p:
        browser = p.chromium.launch()
        for vw, vh in ((1440, 900), (375, 812)):
            ctx = browser.new_context(viewport={'width': vw, 'height': vh}, user_agent=UA)
            page = ctx.new_page()
            for url in samples:
                page.goto(url, wait_until='load')
                box = page.evaluate('''() => { const i = [...document.images].find(x => /zestawienie-znakow/.test(x.currentSrc || x.src));
                    if (!i) return null; const r = i.getBoundingClientRect();
                    return {top: r.top, bottom: r.bottom, left: r.left, right: r.right, w: r.width, nw: i.naturalWidth, nh: i.naturalHeight, h: r.height}; }''')
                ok = bool(box) and box['top'] >= 0 and box['bottom'] <= vh and box['left'] >= 0 and box['right'] <= vw + 0.5 and \
                    abs(box['w'] / box['h'] - box['nw'] / box['nh']) < 0.05
                report['fold'].append(dict(url=url, viewport='%dx%d' % (vw, vh), box=box, ok=ok))
                if not ok:
                    print('  ✗ bez przewijania %s @%dx%d: %s' % (url, vw, vh, box))
            ctx.close()
        browser.close()
    print('Zestaw znaków w całości widoczny bez przewijania: %d/%d (szablony × 2 szerokości)' % (sum(f['ok'] for f in report['fold']), len(report['fold'])))

    # P1–P4
    pubs = [u for u in urls if '/publikacje/' in u and u.rstrip('/') != BASE + '/publikacje']
    for url in pubs:
        _, _, body = get(url)
        links = [html.unescape(h) for h in re.findall(r'<a class="sd-download" href="([^"]+)"', body)]
        if not links:
            report['downloads'].append(dict(url=url, file=None, ok=False))
            print('  ✗ brak przycisku pobierania: %s' % url)
        for link in links:
            status, ctype, data = get(link, binary=True)
            ok = status == 200 and 'pdf' in ctype and data[:4] == b'%PDF'
            pages = len(PdfReader(io.BytesIO(data)).pages) if ok else 0
            report['downloads'].append(dict(url=url, file=link, status=status, bytes=len(data), pages=pages, ok=ok))
            print('  %s %s → %s, %d KB, %d str.' % ('✓' if ok else '✗', url.replace(BASE, ''), status, len(data) // 1024, pages))

    # Formularz
    for path in ('/potrzebuje-pomocy/', '/ua/', '/en/'):
        _, _, body = get(BASE + path)
        if path != '/potrzebuje-pomocy/':
            m = re.search(r'href="(' + re.escape(BASE) + path + r'[^"]*(?:pomoc|help|dopomog)[^"]*)"', body)
            if not m:
                continue
            path = m.group(1).replace(BASE, '')
            _, _, body = get(BASE + path)
        has_form = '<form' in body and re.search(r'type="email"', body) and re.search(r'<textarea', body) and re.search(r'type="checkbox"', body)
        disabled = re.search(r'moduł zgłoszeniowy jest wyłączony|formularz nie przyjmuje', body, re.I)
        ok = bool(has_form) and not disabled
        report['forms'].append(dict(path=path, ok=ok))
        print('  %s formularz %s%s' % ('✓' if ok else '✗', path, ' — wyłączony' if disabled else ''))

    # Podpis autorów
    for url in [u for u in urls if '/artykuly/' in u] + pubs:
        _, _, body = get(url)
        text = html.unescape(re.sub(r'<[^>]+>', ' ', body))
        on_page = BYLINE in re.sub(r'\s+', ' ', text)
        old = re.search(r'Autor: Krzysztof Martyniak', text)
        pdf_link = re.search(r'href="([^"]*sdc_editorial_asset[^"]*)"', body)
        pdf_ok = None
        if pdf_link:
            _, _, data = get(html.unescape(pdf_link.group(1)), binary=True)
            ptext = re.sub(r'\s+', ' ', ' '.join((pg.extract_text() or '') for pg in PdfReader(io.BytesIO(data)).pages[:2]))
            pdf_ok = BYLINE.replace(' ', '') in ptext.replace(' ', '')
        report['byline'].append(dict(url=url, page=on_page, old_on_page=bool(old), pdf=pdf_ok))
        print('  %s podpis %s | strona: %s%s | PDF (pierwsze strony): %s' % ('✓' if on_page and not old else '✗', url.replace(BASE, ''), on_page, ' (jest też „Autor: K. Martyniak”)' if old else '', pdf_ok))
    out = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'wynik.json')
    json.dump(report, open(out, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
    print('Wynik: %s' % out)


if __name__ == '__main__':
    sys.exit(main())
