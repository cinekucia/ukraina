# -*- coding: utf-8 -*-
"""
Materiały promocyjne do wgrania w Media (strona „Materiały do pobrania").

Z paczki od koordynatora robi pliki o czytelnych nazwach (staną się częścią adresów):
  - plakaty A3 (PDF): kopie z tytułem dokumentu, językiem i wyświetlaniem tytułu
    w oknie przeglądarki PDF — wygląd i treść plakatu się nie zmieniają;
  - podglądy plakatów i grafik w WebP (600 px szerokości) — lekkie obrazy na stronę;
  - grafiki 1080 × 1080 (PNG) — kopie bez zmian, do pobrania.

Oryginały w paczce zostają nietknięte.

Użycie:
  python tools/build_promo_materials.py KATALOG_PACZKI --out KATALOG_WYJŚCIOWY
"""
import argparse
import hashlib
import json
import os
import shutil

from PIL import Image
from pypdf import PdfReader, PdfWriter
from pypdf.generic import BooleanObject, DictionaryObject, NameObject, TextStringObject

# (plik w paczce, nowa nazwa bez rozszerzenia, tytuł dokumentu, język)
POSTERS = [
    ('01_Plakat_A3_PL_pomoc', 'plakat-pomoc-pl-a3',
     'Czy spotkała Cię dyskryminacja w pracy? — plakat A3, STOP DYSKRYMINACJI', 'pl-PL'),
    ('02_Plakat_A3_UA_pomoc', 'plakat-pomoc-ua-a3',
     'Вас дискримінують на роботі? — плакат A3, STOP DYSKRYMINACJI', 'uk-UA'),
    ('03_Plakat_A3_EN_pomoc', 'plakat-pomoc-en-a3',
     'Facing discrimination at work? — A3 poster, STOP DYSKRYMINACJI', 'en-GB'),
    ('04_Plakat_A3_PL_pracodawcy', 'plakat-dla-pracodawcow-a3',
     'Jak inkluzywna jest Twoja firma? — plakat A3 dla pracodawców, STOP DYSKRYMINACJI', 'pl-PL'),
    ('00_Plakat_OBOWIAZKOWY_A3_poziomy', 'plakat-informacyjny-projektu-a3',
     'Plakat informacyjny projektu STOP DYSKRYMINACJI — A3', 'pl-PL'),
]
GRAPHICS = [
    ('01_Projekt', 'grafika-rowne-szanse'),
    ('02_Rekrutacja', 'grafika-rekrutacja-od-lutego-2027'),
    ('03_Finansowanie', 'grafika-wklad-ue'),
]
PREVIEW_W = 600


def sha(path):
    with open(path, 'rb') as f:
        return hashlib.sha256(f.read()).hexdigest()


def poster_pdf(src, dst, title, lang):
    reader = PdfReader(src)
    writer = PdfWriter(clone_from=reader)
    writer.add_metadata({'/Title': title, '/Author': 'Projekt STOP DYSKRYMINACJI', '/Subject': 'FEWP.06.10-IP.01-0136/25'})
    root = writer._root_object
    root[NameObject('/Lang')] = TextStringObject(lang)
    vp = root.get('/ViewerPreferences')
    vp = vp.get_object() if vp is not None else DictionaryObject()
    vp[NameObject('/DisplayDocTitle')] = BooleanObject(True)
    root[NameObject('/ViewerPreferences')] = vp
    with open(dst, 'wb') as f:
        writer.write(f)
    # Kontrola: ta sama liczba stron, ten sam format i ten sam tekst co w oryginale.
    a, b = PdfReader(src), PdfReader(dst)
    same = len(a.pages) == len(b.pages) and all(
        (pa.mediabox == pb.mediabox and (pa.extract_text() or '') == (pb.extract_text() or ''))
        for pa, pb in zip(a.pages, b.pages))
    if not same:
        raise SystemExit('Kopia %s różni się od oryginału' % dst)
    p = b.pages[0]
    return {'w_mm': round(float(p.mediabox.width) / 72 * 25.4), 'h_mm': round(float(p.mediabox.height) / 72 * 25.4)}


def preview(src, dst):
    im = Image.open(src).convert('RGB')
    h = round(im.height * PREVIEW_W / im.width)
    im.resize((PREVIEW_W, h), Image.Resampling.LANCZOS).save(dst, 'WEBP', quality=84, method=6)
    return {'w': PREVIEW_W, 'h': h}


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('package')
    ap.add_argument('--out', required=True)
    a = ap.parse_args()
    os.makedirs(a.out, exist_ok=True)
    manifest = {'plakaty': [], 'grafiki': []}

    for stem, name, title, lang in POSTERS:
        src_pdf = next(os.path.join(a.package, '02_Plakaty_A3_PDF', f) for f in os.listdir(os.path.join(a.package, '02_Plakaty_A3_PDF')) if f.startswith(stem))
        src_png = os.path.join(a.package, '03_Plakaty_A3_podglad_PNG', stem + '.png')
        pdf = os.path.join(a.out, name + '.pdf')
        fmt = poster_pdf(src_pdf, pdf, title, lang)
        prv = preview(src_png, os.path.join(a.out, name + '-podglad.webp'))
        manifest['plakaty'].append(dict(plik=name + '.pdf', podglad=name + '-podglad.webp', zrodlo=os.path.basename(src_pdf),
                                        kb=round(os.path.getsize(pdf) / 1024), tytul=title, jezyk=lang, **fmt,
                                        podglad_w=prv['w'], podglad_h=prv['h']))

    for stem, name in GRAPHICS:
        src = os.path.join(a.package, '01_Grafiki_do_strony', stem + '.png')
        png = os.path.join(a.out, name + '-1080.png')
        shutil.copyfile(src, png)
        if sha(src) != sha(png):
            raise SystemExit('Kopia %s różni się od oryginału' % png)
        prv = preview(src, os.path.join(a.out, name + '-podglad.webp'))
        manifest['grafiki'].append(dict(plik=name + '-1080.png', podglad=name + '-podglad.webp', zrodlo=os.path.basename(src),
                                        kb=round(os.path.getsize(png) / 1024), podglad_w=prv['w'], podglad_h=prv['h']))

    # Manifest obok, nie w katalogu do wgrania — Media nie przyjmują plików JSON.
    with open(os.path.join(os.path.dirname(os.path.abspath(a.out)), 'manifest.json'), 'w', encoding='utf-8') as f:
        json.dump(manifest, f, ensure_ascii=False, indent=2)
    for kind in ('plakaty', 'grafiki'):
        for m in manifest[kind]:
            print('  %-44s %4d KB   %s' % (m['plik'], m['kb'], m['podglad']))


if __name__ == '__main__':
    main()
