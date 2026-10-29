# -*- coding: utf-8 -*-
"""
Obrazy podglądu odnośników (Open Graph / Twitter Card), 1200 × 630 px, w układzie
grafiki społecznościowej z projektu „logo i layout publikacji" (strona 4):
pole morskie z sygnetem w wersji negatywowej, nazwą i hasłem, a pod nim — na
białym tle (Załącznik nr 10 rozdz. 4) — oryginalny zestaw znaków FE.

Sygnet jest niższy od flagi UE w zestawie (rozporządzenie 2021/1060, zał. IX:
flaga co najmniej tak duża jak każde inne logo) — skrypt to sprawdza.

Użycie:  python tools/build_og_images.py
Wynik:   wtyczka/assets/brand/og-image-pl.png, og-image-en.png
"""
import os
import sys
from PIL import Image, ImageDraw, ImageFont

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import build_brand_icons as sign   # noqa: E402

REPO = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
BRAND = os.path.join(REPO, 'stop-dyskryminacji', 'app', 'public', 'wp-content', 'plugins',
                     'stop-dyskryminacji-core', 'assets', 'brand')
FB, FR = 'C:/Windows/Fonts/segoeuib.ttf', 'C:/Windows/Fonts/segoeui.ttf'
TEAL, LIGHT, PEACH = '#0B5563', '#E6F0F1', '#F8C9A4'
W, H, FIELD = 1200, 630, 450
FLAG = 0.662                               # wysokość flagi UE / wysokość pliku zestawu

TEXTS = {
    'pl': ('RÓWNE TRAKTOWANIE W ZATRUDNIENIU', ('Wiedza, narzędzia i wsparcie', 'dla osób pracujących i pracodawców w Wielkopolsce.')),
    'en': ('EQUAL TREATMENT AT WORK', ('Knowledge, tools and support', 'for workers and employers in Wielkopolska.')),
}


def spaced(d, xy, text, fnt, fill, tracking):
    x, y = xy
    for ch in text:
        d.text((x, y), ch, font=fnt, fill=fill)
        x += d.textlength(ch, font=fnt) + tracking


def build(lang):
    ss = 2
    img = Image.new('RGB', (W * ss, H * ss), '#FFFFFF')
    d = ImageDraw.Draw(img)
    d.rectangle([0, 0, W * ss, FIELD * ss], fill=TEAL)

    lock = Image.open(os.path.join(BRAND, 'zestawienie-znakow-%s-original.png' % lang)).convert('RGBA')
    lw = 1040 * ss
    lh = round(lock.height * lw / lock.width)
    flag_h = FLAG * lh / ss

    mark_h = 56
    assert mark_h < flag_h, 'sygnet (%d px) nie może być wyższy od flagi UE (%.1f px)' % (mark_h, flag_h)
    mark = sign.png(round(mark_h * 64 / 62) * ss, *sign.VARIANTS['sygnet-negatyw'])
    img.paste(mark, (80 * ss, 64 * ss), mark)

    eyebrow, lines = TEXTS[lang]
    spaced(d, (156 * ss, 82 * ss), eyebrow, ImageFont.truetype(FB, 22 * ss), PEACH, 3 * ss)
    d.text((80 * ss, 150 * ss), 'STOP DYSKRYMINACJI', font=ImageFont.truetype(FB, 84 * ss), fill='#FFFFFF')
    f = ImageFont.truetype(FR, 34 * ss)
    for i, line in enumerate(lines):
        d.text((80 * ss, (290 + i * 48) * ss), line, font=f, fill=LIGHT)

    lock = lock.resize((lw, lh), getattr(Image, 'Resampling', Image).LANCZOS)
    img.paste(lock, ((W * ss - lw) // 2, FIELD * ss + ((H - FIELD) * ss - lh) // 2), lock)

    out = img.resize((W, H), getattr(Image, 'Resampling', Image).LANCZOS)
    path = os.path.join(BRAND, 'og-image-%s.png' % lang)
    out.save(path, optimize=True)
    print('  og-image-%s.png  %d × %d px, sygnet %d px < flaga UE %.1f px, %d KB' % (lang, W, H, mark_h, flag_h, os.path.getsize(path) // 1024))


if __name__ == '__main__':
    for lang in ('pl', 'en'):
        build(lang)
