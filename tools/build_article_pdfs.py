# -*- coding: utf-8 -*-
"""
PDF artykułu (A01–A12) w układzie PDF-ów autora — z pliku MD pakietu „treści na stronę v2-0”.

Układ odtworzony z PDF-ów autora (Chromium, font Onest): zestaw znaków, nadtytuł
„PROJEKT „STOP DYSKRYMINACJI” · ARTYKUŁ N Z 12”, tytuł, linia autora, lead, wersja pełna
(ramki „jak chronić…”, infografika), nota, stopka redakcyjna; wersja skrócona dla mediów
od nowej strony. Nagłówek „STOP DYSKRYMINACJI · Artykuł N z 12 · strona”, stopka z autorem,
stanem prawnym i licencją. PDF z tagami struktury, językiem i zakładkami — jak oryginały.

Treść pochodzi wyłącznie z pliku MD; skrypt jej nie zmienia. Sekcja „Metadane i dystrybucja”
nie trafia do PDF (tak jak w oryginałach) — z jej pól bierze się linia autora i stopka.

Użycie:
  python tools/build_article_pdfs.py PLIK.md [...] --infographics KATALOG --font Onest.ttf --out KATALOG
"""
import argparse
import html
import os
import re
import sys

from playwright.sync_api import sync_playwright

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
LOCKUP = os.path.join(ROOT, 'dist', 'wgraj-przez-winscp', 'wp-content', 'plugins', 'stop-dyskryminacji-core',
                      'assets', 'brand', 'zestawienie-znakow-pl-1800.png')
LOCKUP_ALT = ('Znaki: Fundusze Europejskie dla Wielkopolski, barwy Rzeczypospolitej Polskiej, '
              'Dofinansowane przez Unię Europejską, Herb Województwa Wielkopolskiego')
PROJECT = ('Publikacja powstała w ramach projektu „STOP DYSKRYMINACJI” (nr FEWP.06.10-IP.01-0136/25) realizowanego przez '
           'Stowarzyszenie Edukacyjne „Nieskończoność” (Partner wiodący) i Stowarzyszenie na rzecz Zrównoważonego Rozwoju '
           '„Pro Ukraina” (Partner), współfinansowanego ze środków Europejskiego Funduszu Społecznego Plus w ramach programu '
           'Fundusze Europejskie dla Wielkopolski 2021–2027.')
LICENCE = ('Utwór dostępny na licencji Creative Commons Uznanie autorstwa – Użycie niekomercyjne – Bez utworów zależnych 4.0 '
           'Międzynarodowe (CC BY-NC-ND 4.0). Treść licencji: creativecommons.org/licenses/by-nc-nd/4.0/deed.pl. Przy '
           'wykorzystaniu należy podać autora, tytuł, źródło (stopdyskryminacji.pl) i informację o współfinansowaniu.')
MONTHS = {'stycznia': 1, 'lutego': 2, 'marca': 3, 'kwietnia': 4, 'maja': 5, 'czerwca': 6, 'lipca': 7, 'sierpnia': 8,
          'września': 9, 'października': 10, 'listopada': 11, 'grudnia': 12}

CSS = r'''
@font-face { font-family: "Onest"; src: url("%(font)s") format("truetype"); font-weight: 100 900; font-style: normal; }
@page {
  size: A4; margin: 68.2pt 54pt 62pt 59.3pt;
  @top-left { content: "STOP DYSKRYMINACJI"; font-family: "Onest"; font-size: 7.5pt; font-weight: 700; letter-spacing: .12em; color: #C1121F; vertical-align: top; padding-top: 29.2pt; }
  @top-right { content: "Artykuł %(num)d z 12 · " counter(page); font-family: "Onest"; font-size: 7.5pt; font-weight: 500; color: #4A5160; vertical-align: top; padding-top: 29.2pt; }
  @bottom-left { content: "%(footer)s"; font-family: "Onest"; font-size: 7pt; color: #4A5160; vertical-align: bottom; padding-bottom: 27pt; }
}
* { box-sizing: border-box; }
html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
body { margin: 0; font-family: "Onest", "Segoe UI", Arial, sans-serif; font-size: 10pt; line-height: 15pt; color: #14181F; }
p { margin: 0 0 7.5pt; }
p, li { orphans: 3; widows: 3; }
strong { font-weight: 700; }
a { color: inherit; text-decoration: none; }
.znaki { display: block; width: 456.2pt; height: auto; margin: 1.5pt 0 22.5pt; }
.nadtytul { font-size: 8pt; line-height: 11pt; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: #C1121F; margin: 0 0 7pt; }
h1 { font-size: 20pt; line-height: 24pt; font-weight: 800; letter-spacing: -.01em; margin: 0 0 8pt; }
.autor { font-size: 8.5pt; line-height: 12.8pt; color: #4A5160; margin: 0; padding-bottom: 12pt; border-bottom: 1pt solid #C1121F; margin-bottom: 14.2pt; }
.lead { font-size: 11.5pt; line-height: 17.25pt; font-weight: 500; margin: 0 0 14.7pt; }
h2 { font-size: 15pt; line-height: 18pt; font-weight: 700; color: #8E0D17; margin: 17pt 0 9pt; padding-top: 14.8pt; border-top: 1pt solid #C1121F; break-after: avoid; }
/* Natywne numery list (::marker): w PDF z tagami zostają częścią listy (L/LI), jak u autora. */
ol, ul { margin: 0 0 7.5pt; padding: 0 0 0 18.4pt; }
li { margin: 0 0 3.7pt; padding-left: 0; }
ol > li::marker { font-weight: 700; color: #C1121F; }
ul > li::marker { color: #C1121F; }
figure { margin: 6pt 0 14pt; break-inside: avoid; text-align: center; }
figure img { width: 404pt; height: auto; }
.ramka { break-inside: avoid; margin: 0 0 14.3pt; padding: 14.5pt 18pt 8pt 20.9pt; border-left: 6pt solid #C1121F; background: #FDECEE; border-radius: 0 6pt 6pt 0; }
.ramka--pracodawca { border-left-color: #4A5160; background: #EEF0F3; }
.ramka__tytul { font-weight: 700; margin: 0 0 7.5pt; }
.ramka ol, .ramka ul { padding-left: 16.3pt; }
.nota { font-style: italic; margin-top: 10pt; }
.stopka { font-size: 7.5pt; line-height: 11pt; color: #4A5160; margin-top: 14pt; }
.media { break-before: page; }
.media .tytul { font-size: 15pt; line-height: 18pt; font-weight: 700; color: #8E0D17; margin: 0 0 10pt; padding: 0; border: 0; }
'''


# Polska zasada składu z PDF-ów autora: słowa jedno- i dwuliterowe („w”, „i”, „że”, „do”…)
# nie zostają na końcu wiersza — po nich stoi spacja nierozdzielająca. Reguła wybrana porównaniem
# przełamań z oryginałem (najwięcej zgodnych wierszy spośród sprawdzonych wariantów).
GLUE_SHORT = re.compile(r'(?<![\w\u00C0-\u024F])([a-z\u0105\u0107\u0119\u0142\u0144\u00F3\u015B\u017A\u017C]{1,2}) ')


MONTHS_RE = 'stycznia|lutego|marca|kwietnia|maja|czerwca|lipca|sierpnia|września|października|listopada|grudnia'
DATE_GLUE = [
    (re.compile(r'(\d{1,2}) (?=(?:%s)\b)' % MONTHS_RE), '\\1\u00a0'),   # 5 listopada
    (re.compile(r'\b(%s) (?=\d{4})' % MONTHS_RE), '\\1\u00a0'),         # listopada 2026
    (re.compile(r'(\d{4}) (?=r\.)'), '\\1\u00a0'),                      # 2026 r.
]


def inline(s):
    s = GLUE_SHORT.sub('\\1\u00a0', s)
    s = s.replace(' – ', '\u00a0– ')        # półpauza nie zaczyna wiersza (jak u autora)
    for pattern, repl in DATE_GLUE:          # daty nie rozdzielają się między wiersze
        s = pattern.sub(repl, s)
    s = html.escape(s, quote=False)
    s = re.sub(r'\*\*(.+?)\*\*', r'<strong>\1</strong>', s)
    s = re.sub(r'(?<![*\w])\*(?=\S)(.+?)(?<=\S)\*(?![*\w])', r'<em>\1</em>', s)
    s = re.sub(r'\[([^\]]+)\]\(([^)\s]+)\)', r'<a href="\2">\1</a>', s)
    s = re.sub(r'`([^`]+)`', r'<code>\1</code>', s)
    return s


# Listy numerowane jak w pandocu (fancy_lists): „1. ” albo „(1) ” na początku akapitu.
OL_ITEM = re.compile(r'(?:(\d+)\.|\((\d+)\))\s+')
BLOCK_START = re.compile(r'(### |!\[|>|\d+\.\s|\(\d+\)\s|- )')


def parse_blocks(lines):
    out, i = [], 0
    while i < len(lines):
        line = lines[i].rstrip()
        if not line.strip():
            i += 1
            continue
        if line.startswith('### '):
            out.append(('h2', line[4:].strip()))
            i += 1
        elif line.startswith('!['):
            m = re.match(r'!\[(.*)\]\(([^)]+)\)\s*$', line)
            out.append(('img', m.group(1), m.group(2)))
            i += 1
        elif line.startswith('>'):
            quoted = []
            while i < len(lines) and lines[i].startswith('>'):
                quoted.append(re.sub(r'^>\s?', '', lines[i].rstrip()))
                i += 1
            out.append(('quote', parse_blocks(quoted)))
        elif OL_ITEM.match(line):
            m = OL_ITEM.match(line)
            start = int(m.group(1) or m.group(2))
            items = []
            while i < len(lines) and OL_ITEM.match(lines[i]):
                items.append(OL_ITEM.sub('', lines[i].rstrip(), count=1))
                i += 1
            out.append(('ol', items, start))
        elif line.startswith('- '):
            items = []
            while i < len(lines) and lines[i].startswith('- '):
                items.append(lines[i][2:].rstrip())
                i += 1
            out.append(('ul', items))
        else:
            para = [line.strip()]
            i += 1
            while i < len(lines) and lines[i].strip() and not BLOCK_START.match(lines[i]):
                para.append(lines[i].strip())
                i += 1
            out.append(('p', ' '.join(para)))
    return out


def render_blocks(blocks, infographics):
    out = []
    for b in blocks:
        kind = b[0]
        if kind == 'h2':
            out.append('<h2>%s</h2>' % inline(b[1]))
        elif kind == 'img':
            src = os.path.join(infographics, os.path.basename(b[2]))
            out.append('<figure><img src="%s" alt="%s"></figure>' % (file_url(src), html.escape(b[1], quote=True)))
        elif kind == 'quote':
            inner = b[1]
            title = inner[0][1] if inner and inner[0][0] == 'p' else ''
            cls = 'ramka ramka--pracodawca' if 'pracodawc' in title else 'ramka'
            body = render_blocks(inner[1:], infographics) if title.startswith('**Ramka') else render_blocks(inner, infographics)
            head = '<p class="ramka__tytul">%s</p>' % inline(title.strip('*')) if title.startswith('**Ramka') else ''
            out.append('<div class="%s">%s%s</div>' % (cls, head, body))
        elif kind == 'ol':
            start = '' if b[2] == 1 else ' start="%d"' % b[2]
            out.append('<ol%s>%s</ol>' % (start, ''.join('<li>%s</li>' % inline(x) for x in b[1])))
        elif kind == 'ul':
            out.append('<ul>%s</ul>' % ''.join('<li>%s</li>' % inline(x) for x in b[1]))
        else:
            text = b[1]
            if re.fullmatch(r'\*[^*].*[^*]\*', text):
                out.append('<p class="nota">%s</p>' % inline(text[1:-1]))
            else:
                out.append('<p>%s</p>' % inline(text))
    return ''.join(out)


def file_url(path):
    return 'file:///' + os.path.abspath(path).replace('\\', '/')


def parse_md(text):
    text = text.replace('\r\n', '\n')
    if text.startswith('---\n'):
        text = text.split('\n---\n', 1)[1]
    lines = text.split('\n')
    title = next(l[2:].strip() for l in lines if l.startswith('# '))
    meta = {}
    for l in lines:
        m = re.match(r'\|\s*([^|]+?)\s*\|\s*(.*?)\s*\|\s*$', l)
        if m and m.group(1) not in ('Pole', '---') and m.group(1) not in meta:
            meta[m.group(1)] = m.group(2).strip('`')
    sections, current = {}, None
    for l in lines:
        if l.startswith('## '):
            current = l[3:].strip()
            sections[current] = []
        elif current:
            sections[current].append(l)
    return title, meta, sections


def short_date(legal):
    m = re.match(r'(\d+)\s+(\w+)\s+(\d{4})', legal)
    return '%d.%d.%s' % (int(m.group(1)), MONTHS[m.group(2)], m.group(3)) if m else legal


def build_html(md_path, infographics, font_url):
    title, meta, sections = parse_md(open(md_path, encoding='utf-8').read())
    code = re.match(r'(A\d\d)_', os.path.basename(md_path)).group(1)
    num = int(code[1:])
    author = meta.get('Autor', '')
    legal = next((v for k, v in meta.items() if k.startswith('Stan prawny')), '')
    lead_key = next(k for k in sections if k.startswith('Lead'))
    full_key = next(k for k in sections if k.startswith('Wersja pełna'))
    media_key = next((k for k in sections if k.startswith('Wersja skrócona')), None)
    lead = ' '.join(l.strip() for l in sections[lead_key] if l.strip())
    full = render_blocks(parse_blocks(sections[full_key]), infographics)
    colophon = PROJECT + ' ' + LICENCE + ' ' + meta.get('Sposób wytworzenia', '')
    parts = [
        '<img class="znaki" src="%s" alt="%s">' % (file_url(LOCKUP), html.escape(LOCKUP_ALT, quote=True)),
        '<p class="nadtytul">Projekt „STOP DYSKRYMINACJI” · Artykuł %d z 12</p>' % num,
        '<h1>%s</h1>' % inline(title),
        '<p class="autor">Autor: %s · Stan prawny: %s · Licencja CC BY-NC-ND 4.0 · stopdyskryminacji.pl</p>' % (inline(author), inline(legal)),
        '<p class="lead">%s</p>' % inline(lead),
        full,
        '<p class="stopka">%s</p>' % inline(colophon.strip()),
    ]
    if media_key:
        mb = parse_blocks(sections[media_key])
        media_title = mb[0][1].strip('*') if mb and mb[0][0] == 'p' and mb[0][1].startswith('**') else ''
        rest = mb[1:] if media_title else mb
        parts.append('<section class="media"><img class="znaki" src="%s" alt="%s">'
                     '<p class="nadtytul">Wersja skrócona dla mediów · do bezpłatnego przedruku</p>'
                     '<h2 class="tytul">%s</h2>%s<p class="stopka">%s</p></section>' % (
                         file_url(LOCKUP), html.escape(LOCKUP_ALT, quote=True), inline(media_title),
                         render_blocks(rest, infographics), inline('Autor: ' + author.split(' – ')[0] + '. ' + PROJECT)))
    footer = '%s · stan prawny: %s · CC BY-NC-ND 4.0' % (author.split(' – ')[0], short_date(legal))
    css = CSS % {'font': font_url, 'num': num, 'footer': footer.replace('\\', '\\\\').replace('"', '\\"')}
    doc = ('<!doctype html><html lang="pl"><head><meta charset="utf-8"><title>%s</title><style>%s</style></head>'
           '<body><main>%s</main></body></html>') % (html.escape(title), css, ''.join(parts))
    return code, num, title, doc


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('md', nargs='+')
    ap.add_argument('--infographics', required=True)
    ap.add_argument('--font', required=True)
    ap.add_argument('--out', required=True)
    a = ap.parse_args()
    os.makedirs(a.out, exist_ok=True)
    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page()
        for md in a.md:
            code, num, title, doc = build_html(md, a.infographics, file_url(a.font))
            tmp = os.path.join(a.out, code + '.html')
            with open(tmp, 'w', encoding='utf-8') as f:
                f.write(doc)
            page.goto(file_url(tmp))
            page.wait_for_load_state('networkidle')
            page.evaluate('document.fonts.ready')
            out = os.path.join(a.out, os.path.splitext(os.path.basename(md))[0] + '.pdf')
            # Marginesy, nagłówek i stopka z reguły @page (pola marginesu) — ten sam font co tekst.
            page.pdf(path=out, print_background=True, prefer_css_page_size=True, tagged=True, outline=True)
            print('  %s → %s' % (code, out))
        browser.close()


if __name__ == '__main__':
    sys.exit(main())
