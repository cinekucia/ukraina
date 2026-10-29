# -*- coding: utf-8 -*-
"""
Sygnet projektu „Dymek równości" — pliki SVG i PNG (favicon, ikona ekranu startowego).

Geometria zmierzona na projekcie „STOP DYSKRYMINACJI — logo i layout publikacji"
(kierunek A, rekomendowany): dymek 64 × 50, promień narożnika 0,2 wysokości;
dwie kreski tej samej długości (0,5 szerokości dymku), grubość 0,128 wysokości,
środki na 0,38 i 0,645 wysokości; ogonek przy lewej krawędzi (0,205 szerokości),
sięga 1,236 wysokości.

Kolory: morski #0B5563 (marka), morela #F59E6B (detal w znaku), ceglany #C2410C
(wersja negatywowa), atrament #14181F (wersja achromatyczna).

Użycie:  python tools/build_brand_icons.py
Wynik:   wtyczka/assets/brand/sygnet*.svg, favicon.svg, favicon-32.png, favicon-180.png
"""
import os
from PIL import Image, ImageDraw

REPO = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(REPO, 'stop-dyskryminacji', 'app', 'public', 'wp-content', 'plugins',
                   'stop-dyskryminacji-core', 'assets', 'brand')

W, H = 64.0, 50.0                        # dymek
R = 0.2 * H                              # promień narożnika
BAR_W, BAR_H = 32.0, 6.4                 # kreski
BAR_X = (W - BAR_W) / 2
BAR_Y = (0.38 * H - BAR_H / 2, 0.645 * H - BAR_H / 2)
TAIL = [(13.1, H - 6), (13.1, 61.8), (28.3, H - 1), (28.3, H - 6)]   # zachodzi na dymek — bez szczeliny
VIEW_H = 62.0

VARIANTS = {
    'sygnet':               ('#0B5563', '#FFFFFF', '#F59E6B'),   # pozytyw
    'sygnet-negatyw':       ('#FFFFFF', '#0B5563', '#C2410C'),   # na tle morskim
    'sygnet-achromatyczny': ('#14181F', '#FFFFFF', '#FFFFFF'),   # druk jednobarwny
}


def svg(bubble, top, bottom, title=None):
    pts = ' '.join('%g,%g' % p for p in TAIL)
    t = '<title>%s</title>' % title if title else ''
    return (
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %g %g" width="%g" height="%g">%s'
        '<rect width="%g" height="%g" rx="%g" fill="%s"/>'
        '<polygon points="%s" fill="%s"/>'
        '<rect x="%g" y="%g" width="%g" height="%g" rx="%g" fill="%s"/>'
        '<rect x="%g" y="%g" width="%g" height="%g" rx="%g" fill="%s"/>'
        '</svg>\n'
    ) % (W, VIEW_H, W, VIEW_H, t, W, H, R, bubble, pts, bubble,
         BAR_X, BAR_Y[0], BAR_W, BAR_H, BAR_H / 2, top,
         BAR_X, BAR_Y[1], BAR_W, BAR_H, BAR_H / 2, bottom)


def png(size, bubble, top, bottom, background=None, pad=0.0):
    """Rysunek z nadpróbkowaniem ×8 — gładkie krawędzie bez zewnętrznych bibliotek."""
    ss = 8
    S = size * ss
    img = Image.new('RGBA', (S, S), background or (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    inner = S * (1 - 2 * pad)
    k = inner / max(W, VIEW_H)            # skala: dłuższy bok znaku = pole bez marginesu
    ox = (S - W * k) / 2
    oy = (S - VIEW_H * k) / 2

    def p(x, y):
        return (ox + x * k, oy + y * k)

    d.rounded_rectangle([p(0, 0), p(W, H)], radius=R * k, fill=bubble)
    d.polygon([p(*q) for q in TAIL], fill=bubble)
    for y, c in zip(BAR_Y, (top, bottom)):
        d.rounded_rectangle([p(BAR_X, y), p(BAR_X + BAR_W, y + BAR_H)], radius=BAR_H / 2 * k, fill=c)
    lanczos = getattr(Image, 'Resampling', Image).LANCZOS
    return img.resize((size, size), lanczos)


def main():
    os.makedirs(OUT, exist_ok=True)
    for name, colors in VARIANTS.items():
        with open(os.path.join(OUT, name + '.svg'), 'w', encoding='utf-8', newline='\n') as f:
            f.write(svg(*colors, title='STOP DYSKRYMINACJI'))
    with open(os.path.join(OUT, 'favicon.svg'), 'w', encoding='utf-8', newline='\n') as f:
        f.write(svg(*VARIANTS['sygnet']))

    pos = VARIANTS['sygnet']
    png(32, *pos, pad=0.02).save(os.path.join(OUT, 'favicon-32.png'), optimize=True)
    # Ikona ekranu startowego (iOS dodaje własne zaokrąglenie i nie lubi przezroczystości).
    png(180, *pos, background=(255, 255, 255, 255), pad=0.16).convert('RGB').save(os.path.join(OUT, 'favicon-180.png'), optimize=True)

    for f in sorted(os.listdir(OUT)):
        if f.startswith(('sygnet', 'favicon')):
            print('  %-28s %6d B' % (f, os.path.getsize(os.path.join(OUT, f))))


if __name__ == '__main__':
    main()
