# -*- coding: utf-8 -*-
"""
Treść strony „Materiały do pobrania" (bloki WordPressa) z plików wgranych w Media.

Adresy i identyfikatory plików pobiera z publicznego REST API serwisu
(/wp-json/wp/v2/media), więc strona zawsze wskazuje pliki, które naprawdę są
w bibliotece. Plakat pojawia się na stronie dopiero wtedy, gdy w Mediach jest
i jego PDF, i podgląd — brakujący materiał jest pomijany, a skrypt to wypisuje.

Odnośniki do plików mają parametr ?v=RRRRMMDD: po podmianie pliku pod tą samą
nazwą wystarczy zmienić datę, a przeglądarki nie podadzą starej wersji z pamięci.
Parametr ma też drugi skutek: plakat nie jest liczony w GA4 jako „pobranie
publikacji" (to zdarzenie łapie odnośniki kończące się na .pdf, .epub, .docx),
więc wskaźnik pobrań publikacji zostaje czysty.

Użycie:
  python tools/build_materials_page.py --site https://stopdyskryminacji.pl --out dist/materialy-do-pobrania/strona.html
"""
import argparse
import html
import json
import os
import sys
import urllib.parse
import urllib.request

VERSION = '20261019'
ICON = {
    'pdf': '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 15h1.5a1.5 1.5 0 0 0 0-3H9v6M14.5 18v-6h1a2 2 0 0 1 0 4h-1"/>',
    'download': '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/>',
}
CONTACT = 'info@proukraina.org, tel. 728 845 846, stopdyskryminacji.pl'
MARKS = ('Na dole znaki: Fundusze Europejskie dla Wielkopolski, barwy Rzeczypospolitej Polskiej, '
         'Dofinansowane przez Unię Europejską, Herb Województwa Wielkopolskiego.')
PARTNERS = 'Lider: Stowarzyszenie Edukacyjne „Nieskończoność” (SEN). Partner: PRO UKRAINA.'

POSTERS = [
    dict(base='plakat-pomoc-pl-a3', lang='pl', hreflang='',
         title='Czy spotkała Cię dyskryminacja w pracy?',
         meta='Plakat dla osób pracujących i kandydatów · po polsku · A3 pionowy',
         alt='Podgląd plakatu „Czy spotkała Cię dyskryminacja w pracy?” w języku polskim',
         link_name='„Czy spotkała Cię dyskryminacja w pracy?”',
         summary='Treść plakatu',
         text=[
             'STOP DYSKRYMINACJI · PL · UA · EN',
             'Bezpłatna pomoc · Wielkopolska',
             '<strong>Czy spotkała Cię dyskryminacja w pracy?</strong>',
             'Pomożemy Ci zrozumieć, co się stało, i pokażemy, co możesz zrobić dalej. Nie musisz znać przepisów ani nazywać tego „dyskryminacją”.',
             ['Bezpłatnie', 'Poufnie, bez nazwiska', 'Bez zobowiązań', 'Odpowiedź w 5 dni roboczych'],
             ['Potraktowano Cię gorzej niż innych — wynagrodzenie, awans, rekrutacja, zwolnienie',
              'Doświadczasz mobbingu lub molestowania — nękanie, poniżanie, zachowania seksualne',
              'Widzisz, jak ktoś jest dyskryminowany — jak zareagować i wesprzeć tę osobę'],
             'Opisz swoją sytuację — zeskanuj kod lub wejdź na stopdyskryminacji.pl. Nie pytamy o nazwisko ani o nazwę pracodawcy.',
             'Projekt „STOP DYSKRYMINACJI” nr FEWP.06.10-IP.01-0136/25 jest dofinansowany przez Unię Europejską ze środków Europejskiego Funduszu Społecznego Plus w ramach programu Fundusze Europejskie dla Wielkopolski 2021–2027. Realizatorzy: Stowarzyszenie Edukacyjne „Nieskończoność” (Partner wiodący) · Stowarzyszenie na rzecz Zrównoważonego Rozwoju „Pro Ukraina” (Partner).',
         ]),
    dict(base='plakat-pomoc-ua-a3', lang='uk', hreflang='uk',
         title='Вас дискримінують на роботі?',
         meta='Ten sam plakat po ukraińsku · A3 pionowy',
         alt='Podgląd plakatu w języku ukraińskim „Czy spotkała Cię dyskryminacja w pracy?”',
         link_name='po ukraińsku',
         summary='Treść plakatu (po ukraińsku)',
         text=[
             'STOP DYSKRYMINACJI · UA · PL · EN',
             'Безкоштовна допомога · Великопольща',
             '<strong>Вас дискримінують на роботі?</strong>',
             'Ми допоможемо зрозуміти, що сталося, і підкажемо, що робити далі. Не потрібно знати закони чи називати це «дискримінацією».',
             ['Безкоштовно', 'Конфіденційно, без прізвища', 'Без зобов’язань', 'Відповідь за 5 робочих днів'],
             ['З вами повелися гірше, ніж з іншими — зарплата, підвищення, прийом на роботу, звільнення',
              'Ви зазнаєте мобінгу або домагань — цькування, приниження, сексуальні домагання',
              'Ви бачите, як когось дискримінують — як відреагувати й підтримати цю людину'],
             'Опишіть свою ситуацію — скануйте код або зайдіть на stopdyskryminacji.pl/ua. Ми не питаємо прізвища чи назви роботодавця.',
             'Проєкт «STOP DYSKRYMINACJI» № FEWP.06.10-IP.01-0136/25 співфінансується Європейським Союзом з коштів Європейського соціального фонду Плюс у межах програми «Європейські фонди для Великопольщі 2021–2027». Виконавці: Освітнє товариство «Nieskończoność» (провідний партнер) · Товариство сталого розвитку «Pro Ukraina» (партнер).',
         ]),
    dict(base='plakat-pomoc-en-a3', lang='en', hreflang='en',
         title='Facing discrimination at work?',
         meta='Ten sam plakat po angielsku · A3 pionowy',
         alt='Podgląd plakatu w języku angielskim „Czy spotkała Cię dyskryminacja w pracy?”',
         link_name='po angielsku',
         summary='Treść plakatu (po angielsku)',
         text=[
             'STOP DYSKRYMINACJI · EN · PL · UA',
             'Free support · Wielkopolska',
             '<strong>Facing discrimination at work?</strong>',
             'We’ll help you understand what happened and show you what you can do next. You don’t need to know the law or call it “discrimination”.',
             ['Free of charge', 'Confidential, no surname needed', 'No obligation', 'Reply within 5 working days'],
             ['You were treated worse than others — pay, promotion, recruitment, dismissal',
              'You face bullying or harassment — persistent harassment, humiliation, sexual harassment',
              'You see someone being discriminated against — how to react and support them'],
             'Describe your situation — scan the code or visit stopdyskryminacji.pl/en. We don’t ask for your surname or your employer’s name.',
             'The project “STOP DYSKRYMINACJI” No. FEWP.06.10-IP.01-0136/25 is co-funded by the European Union from the European Social Fund Plus under the European Funds for Wielkopolska 2021–2027 programme. Implemented by: Stowarzyszenie Edukacyjne „Nieskończoność” (lead partner) · Stowarzyszenie na rzecz Zrównoważonego Rozwoju „Pro Ukraina” (partner).',
         ]),
    dict(base='plakat-dla-pracodawcow-a3', lang='pl', hreflang='',
         title='Jak inkluzywna jest Twoja firma?',
         meta='Plakat dla pracodawców i działów HR · po polsku · A3 pionowy',
         alt='Podgląd plakatu dla pracodawców „Jak inkluzywna jest Twoja firma?”',
         link_name='„Jak inkluzywna jest Twoja firma?”',
         summary='Treść plakatu',
         text=[
             'STOP DYSKRYMINACJI · Dla pracodawców i HR',
             'Samoocena firmy · około 6 minut',
             '<strong>Jak inkluzywna jest Twoja firma?</strong>',
             'Piętnaście pytań o rekrutację, wynagrodzenia, awanse i reagowanie na zgłoszenia. Na końcu: wynik punktowy, trzy rzeczy warte poprawy i raport PDF dla zarządu. Bez rejestracji. Nie zbieramy danych osobowych.',
             ['Kodeks Równości i Inkluzywności — gotowy dokument wewnętrzny: PDF, EPUB i edytowalny DOCX',
              'Wzory dokumentów — polityka antymobbingowa, procedura zgłoszeń',
              'Baza dobrych praktyk — filtr po branży, wielkości firmy i przesłance',
              'Bezpłatne warsztaty — zarządzanie różnorodnością, nabór otwarty'],
             'Zrób samoocenę — zeskanuj kod lub wejdź na stopdyskryminacji.pl/samoocena. Pierwszeństwo w warsztatach mają mikro i małe firmy z Wielkopolski.',
             'Projekt „STOP DYSKRYMINACJI” nr FEWP.06.10-IP.01-0136/25 jest dofinansowany przez Unię Europejską ze środków Europejskiego Funduszu Społecznego Plus w ramach programu Fundusze Europejskie dla Wielkopolski 2021–2027. Realizatorzy: Stowarzyszenie Edukacyjne „Nieskończoność” (Partner wiodący) · Stowarzyszenie na rzecz Zrównoważonego Rozwoju „Pro Ukraina” (Partner).',
         ]),
    dict(base='plakat-informacyjny-projektu-a3', lang='pl', hreflang='',
         title='Plakat informacyjny projektu',
         meta='Informacja o dofinansowaniu z Funduszy Europejskich · A3 poziomy',
         alt='Podgląd plakatu informacyjnego projektu ze znakami Funduszy Europejskich i Unii Europejskiej',
         link_name='informacyjny projektu',
         summary='Treść plakatu',
         text=[
             'Znaki: Fundusze Europejskie, Dofinansowane przez Unię Europejską, Herb Województwa Wielkopolskiego.',
             'Stowarzyszenie Edukacyjne „Nieskończoność” realizuje projekt STOP DYSKRYMINACJI.',
             'Dofinansowanie projektu z UE: 576 643,37 zł.',
             'www.mapadotacji.gov.pl',
         ]),
]

GRAPHICS = [
    dict(base='grafika-rowne-szanse', title='Równe szanse zaczynają się w pracy',
         alt='Podgląd grafiki „Równe szanse zaczynają się w pracy”',
         social=('Grafika projektu STOP DYSKRYMINACJI: „Równe szanse zaczynają się w pracy”. Wiedza, warsztaty '
                 'i wsparcie. Projekt dla osób i pracodawców z Wielkopolski. Poznaj projekt: ' + CONTACT + '. ')),
    dict(base='grafika-rekrutacja-od-lutego-2027', title='Rekrutacja od lutego 2027',
         alt='Podgląd grafiki „Twoje prawa. Twoja praca. Twoja przyszłość. Rekrutacja od lutego 2027”',
         social=('Grafika projektu STOP DYSKRYMINACJI: „Twoje prawa. Twoja praca. Twoja przyszłość.” Rekrutacja od '
                 'lutego 2027. Dla osób doświadczających dyskryminacji oraz kadr zarządzających i HR przedsiębiorstw. '
                 'Udział zgodnie z kryteriami projektu. Poznaj projekt: ' + CONTACT + '. ')),
    dict(base='grafika-wklad-ue', title='Europa wspiera równe szanse',
         alt='Podgląd grafiki „Europa wspiera równe szanse” z kwotą wkładu Unii Europejskiej',
         social=('Grafika projektu STOP DYSKRYMINACJI: „Europa wspiera równe szanse”. Wkład Unii Europejskiej: '
                 '576 643,37 zł. Wartość projektu: 823 776,25 zł. Dofinansowanie ogółem: 782 587,43 zł. '
                 'Poznaj projekt: ' + CONTACT + '. ')),
]


def esc(s):
    return html.escape(s, quote=True)


def media_index(site, names):
    """Plik w Mediach po nazwie: {nazwa: {id, url, w, h, kb}}. Szuka po nazwie bez rozszerzenia."""
    found = {}
    for name in names:
        stem = os.path.splitext(name)[0]
        q = urllib.parse.urlencode({'search': stem, 'per_page': 20, '_fields': 'id,source_url,media_details,mime_type'})
        with urllib.request.urlopen('%s/wp-json/wp/v2/media?%s' % (site, q), timeout=30) as r:
            items = json.load(r)
        for it in items:
            if os.path.basename(urllib.parse.urlparse(it['source_url']).path) == name:
                md = it.get('media_details') or {}
                size = md.get('filesize')
                if not size:       # PDF-y nie zawsze mają rozmiar w metadanych — pytamy serwer
                    req = urllib.request.Request(it['source_url'], method='HEAD')
                    with urllib.request.urlopen(req, timeout=30) as r:
                        size = int(r.headers.get('Content-Length') or 0)
                found[name] = dict(id=it['id'], url=it['source_url'], w=md.get('width'), h=md.get('height'),
                                   kb=round(size / 1024))
                break
    return found


def svg(name, size=20):
    return ('<svg width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" '
            'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>') % (size, size, ICON[name])


def image_block(m, alt):
    # Bez szerokości i wysokości w atrybutach bloku: WordPress sam dopisuje width/height,
    # srcset i loading="lazy" obrazom z klasą wp-image-ID. Ramka — z obsługi obramowań bloku.
    return ('<!-- wp:image {"id":%(id)d,"sizeSlug":"full","linkDestination":"none",'
            '"style":{"border":{"width":"1px","color":"#dcd6cb","radius":"8px"}}} -->\n'
            '<figure class="wp-block-image size-full has-custom-border"><img src="%(url)s" alt="%(alt)s" '
            'class="has-border-color wp-image-%(id)d" style="border-color:#dcd6cb;border-width:1px;border-radius:8px"/></figure>\n'
            '<!-- /wp:image -->') % dict(m, alt=esc(alt))


def download_block(m, label, fmt, name, icon, hreflang=''):
    href = '%s?v=%s' % (m['url'], VERSION)
    hl = ' hreflang="%s"' % hreflang if hreflang else ''
    return ('<!-- wp:html -->\n<div class="sd-downloads"><a class="sd-download" href="%s" download="%s"%s>'
            '<span class="sd-download__icon">%s</span><span><span class="sd-download__label">%s'
            '<span class="sd-sr-only"> %s</span></span><span class="sd-download__size">%s</span></span></a></div>\n'
            '<!-- /wp:html -->') % (esc(href), esc(os.path.basename(m['url'])), hl, svg(icon), esc(label), esc(name), esc(fmt))


def poster_text(p):
    parts = []
    for item in p['text']:
        if isinstance(item, list):
            parts.append('<ul>%s</ul>' % ''.join('<li>%s</li>' % esc(x) for x in item))
        else:
            parts.append('<p>%s</p>' % (item if item.startswith('<strong>') else esc(item)))
    lang = ' lang="%s"' % p['lang'] if p['lang'] != 'pl' else ''
    return ('<!-- wp:html -->\n<div class="sd-faq"><details><summary>%s</summary><div%s>%s</div></details></div>\n'
            '<!-- /wp:html -->') % (esc(p['summary']), lang, ''.join(parts))


def heading(text, level, lang=''):
    inner = '<span lang="%s">%s</span>' % (lang, esc(text)) if lang and lang != 'pl' else esc(text)
    return '<!-- wp:heading {"level":%d} -->\n<h%d class="wp-block-heading">%s</h%d>\n<!-- /wp:heading -->' % (level, level, inner, level)


def para(text, cls=''):
    a = ' {"className":"%s"}' % cls if cls else ''
    c = ' class="%s"' % cls if cls else ''
    return '<!-- wp:paragraph%s -->\n<p%s>%s</p>\n<!-- /wp:paragraph -->' % (a, c, text)


def card(blocks):
    return ('<!-- wp:group {"className":"sd-card","layout":{"type":"default"}} -->\n<div class="wp-block-group sd-card">\n%s\n</div>\n'
            '<!-- /wp:group -->') % '\n'.join(blocks)


def grid(cls, cards):
    return ('<!-- wp:group {"align":"wide","className":"sd-grid %s","layout":{"type":"default"}} -->\n'
            '<div class="wp-block-group alignwide sd-grid %s">\n%s\n</div>\n<!-- /wp:group -->') % (cls, cls, '\n'.join(cards))


def build(media):
    out, missing = [], []
    out.append(para('Plakaty i grafiki projektu „STOP DYSKRYMINACJI” — do wydrukowania, powieszenia w miejscu pracy '
                    'i udostępnienia w mediach społecznościowych. Materiały są bezpłatne.', 'sd-lead'))

    cards = []
    for p in POSTERS:
        pdf, prv = media.get(p['base'] + '.pdf'), media.get(p['base'] + '-podglad.webp')
        if not (pdf and prv):
            missing.append(p['base'])
            continue
        cards.append(card([
            image_block(prv, p['alt']),
            heading(p['title'], 3, p['lang']),
            para(esc(p['meta'])),
            download_block(pdf, 'Pobierz plakat', 'PDF · A3 · %d KB' % pdf['kb'], p['link_name'], 'pdf', p['hreflang']),
            poster_text(p),
        ]))
    if cards:
        out.append(heading('Plakaty A3', 2))
        out.append(para('Pliki PDF w formacie A3, przygotowane do druku na zwykłej drukarce i ksero. Pod podglądem każdego '
                        'plakatu jest jego pełna treść.'))
        out.append(grid('sd-grid--2', cards))

    cards = []
    for g in GRAPHICS:
        png, prv = media.get(g['base'] + '-1080.png'), media.get(g['base'] + '-podglad.webp')
        if not (png and prv):
            missing.append(g['base'])
            continue
        cards.append(card([
            image_block(prv, g['alt']),
            heading(g['title'], 3),
            download_block(png, 'Pobierz grafikę', 'PNG · 1080 × 1080 px · %d KB' % png['kb'], '„%s”' % g['title'], 'download'),
            para('<strong>Tekst alternatywny do wklejenia:</strong> ' + esc(g['social'] + PARTNERS + ' ' + MARKS)),
        ]))
    if cards:
        out.append(heading('Grafiki do mediów społecznościowych', 2))
        out.append(para('Kwadratowe grafiki 1080 × 1080 px. Publikuj je w całości — pas znaków Funduszy Europejskich '
                        'na dole grafiki musi być widoczny, więc nie przycinaj go. Do posta dodaj tekst alternatywny: '
                        'propozycja jest pod każdą grafiką.'))
        out.append(grid('sd-grid--3', cards))

    out.append(heading('Zasady korzystania', 2))
    out.append('<!-- wp:list -->\n<ul class="wp-block-list">%s</ul>\n<!-- /wp:list -->' % ''.join(
        '<!-- wp:list-item --><li>%s</li><!-- /wp:list-item -->' % x for x in (
            'Zestaw znaków Funduszy Europejskich publikuj wyłącznie w pełnym kolorze, bez zmiany kolejności i proporcji.',
            'Nie przycinaj grafik ani plakatów tak, by wypadł pas znaków.',
            'Każdą grafikę opublikowaną w internecie opatrz tekstem alternatywnym.',
        )))
    out.append(para('Pytania o materiały: <a href="/kontakt/">kontakt z zespołem projektu</a>.'))
    return '\n\n'.join(out) + '\n', missing


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--site', required=True)
    ap.add_argument('--out', required=True)
    a = ap.parse_args()
    names = []
    for p in POSTERS:
        names += [p['base'] + '.pdf', p['base'] + '-podglad.webp']
    for g in GRAPHICS:
        names += [g['base'] + '-1080.png', g['base'] + '-podglad.webp']
    media = media_index(a.site.rstrip('/'), names)
    content, missing = build(media)
    os.makedirs(os.path.dirname(os.path.abspath(a.out)), exist_ok=True)
    with open(a.out, 'w', encoding='utf-8', newline='\n') as f:
        f.write(content)
    print('W Mediach: %d z %d plików. Na stronie: %d plakatów, %d grafik.' % (
        len(media), len(names), sum(1 for p in POSTERS if p['base'] not in missing),
        sum(1 for g in GRAPHICS if g['base'] not in missing)))
    if missing:
        print('Pominięte (brak pliku albo podglądu w Mediach): ' + ', '.join(missing))
    sys.exit(0)


if __name__ == '__main__':
    main()
