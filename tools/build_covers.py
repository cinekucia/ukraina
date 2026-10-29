# -*- coding: utf-8 -*-
"""
Okładki zastępcze publikacji w układzie z projektu „STOP DYSKRYMINACJI — logo
i layout publikacji" (strona 5): górne 69 % w kolorze serii — sygnet, nazwa,
numer, nadtytuł, tytuł, podtytuł i dwie kreski; dół biały — odbiorcy, wydanie
i zestaw znaków FE (oryginalny plik z paczki, tylko pomniejszony).

Okładki docelowe przygotowuje grafik — te stoją w serwisie do czasu ich
dostarczenia. Pliki mają te same nazwy i wymiary co miniatury WordPressa
(kadrowanie jak w WordPressie: miniatury twarde — od środka), więc wystarczy
nadpisać je na serwerze; metadane załączników zostają ważne.

Użycie:  python tools/build_covers.py [katalog_docelowy ...]
         bez argumentów: wp-content/uploads/2026/09 lokalnej kopii i folder dla WinSCP
"""
import os
import sys
from PIL import Image, ImageDraw, ImageFont

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import build_brand_icons as sign   # noqa: E402

REPO = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
WP = os.path.join(REPO, 'stop-dyskryminacji', 'app', 'public', 'wp-content')
LOCKUP = os.path.join(WP, 'plugins', 'stop-dyskryminacji-core', 'assets', 'brand', 'zestawienie-znakow-pl-original.png')
TARGETS = [os.path.join(WP, 'uploads', '2026', '09'),
           os.path.join(REPO, 'dist', 'wgraj-przez-winscp', 'wp-content', 'uploads', '2026', '09')]

FONT = 'C:/Windows/Fonts/segoeui.ttf'
FONT_BOLD = 'C:/Windows/Fonts/segoeuib.ttf'

INK, MUTED, PEACH, BAR_LIGHT, MORELA = '#14181F', '#4A5160', '#F8C9A4', '#F1EEE8', '#F59E6B'

# Kolory serii — jak w motywie (klasy sd-seria-*): Kodeks morski, poradniki ceglany, kompendium zielony.
COVERS = [
    ('okladka-kodeks', '01', '#0B5563', 'DOKUMENT WZORCOWY', 'Kodeks Równości i Inkluzywności',
     'Gotowy do wdrożenia dokument wewnętrzny z procedurą zgłoszeń i wzorami zapisów.',
     ('Dla pracodawców', 'i działów HR')),
    ('okladka-poradnik-osoby', '02', '#9A3412', 'PORADNIK', 'Poradnik dla osób doświadczających dyskryminacji',
     'Prawa, dowody i terminy — krok po kroku, prostym językiem.',
     ('Dla osób pracujących', 'i kandydatów')),
    ('okladka-poradnik-swiadkowie', '03', '#9A3412', 'PORADNIK', 'Poradnik dla świadków dyskryminacji',
     'Jak zareagować w chwili zdarzenia i jak wesprzeć osobę pokrzywdzoną.',
     ('Dla świadków', 'i współpracowników')),
    ('okladka-kompendium', '04', '#2F6B3A', 'KOMPENDIUM', 'Kompendium dobrych praktyk',
     'Praktyki z kosztem, czasem wdrożenia i miernikiem — dla małych i średnich firm.',
     ('Dla pracodawców', 'i kadry zarządzającej')),
]

W, H = 620, 877
SS = 3                     # nadpróbkowanie — gładkie litery i krawędzie
PAD = 45
COLOR_H = int(H * 0.69)


def font(path, size):
    return ImageFont.truetype(path, size * SS)


def wrap(draw, text, fnt, width):
    lines, line = [], ''
    for word in text.split():
        test = (line + ' ' + word).strip()
        if draw.textlength(test, font=fnt) <= width * SS:
            line = test
        else:
            lines.append(line)
            line = word
    return lines + ([line] if line else [])


def spaced(draw, xy, text, fnt, fill, tracking):
    """Tekst z rozstrzeleniem liter (nadtytuł, nazwa projektu)."""
    x, y = xy
    for ch in text:
        draw.text((x, y), ch, font=fnt, fill=fill)
        x += draw.textlength(ch, font=fnt) + tracking * SS
    return x


def cover(num, color, eyebrow, title, subtitle, audience):
    img = Image.new('RGB', (W * SS, H * SS), '#FFFFFF')
    d = ImageDraw.Draw(img)
    d.rectangle([0, 0, W * SS, COLOR_H * SS], fill=color)

    # Sygnet (biały dymek, kreski: kolor serii + morela) i nazwa projektu.
    mark = sign.png(34 * SS, '#FFFFFF', color, MORELA)
    img.paste(mark, (PAD * SS, 44 * SS), mark)
    spaced(d, ((PAD + 44) * SS, 52 * SS), 'STOP DYSKRYMINACJI', font(FONT_BOLD, 13), '#FFFFFF', 0.6)
    fn = font(FONT_BOLD, 14)
    d.text(((W - PAD) * SS - d.textlength(num, font=fn), 52 * SS), num, font=fn, fill='#FFFFFF')

    # Nadtytuł, tytuł, podtytuł.
    y = 170
    spaced(d, (PAD * SS, y * SS), eyebrow, font(FONT_BOLD, 14), PEACH, 1.8)
    y += 34
    ft = font(FONT_BOLD, 44)
    for line in wrap(d, title, ft, W - 2 * PAD):
        d.text((PAD * SS, y * SS), line, font=ft, fill='#FFFFFF')
        y += 52
    y += 12
    fs = font(FONT, 18)
    for line in wrap(d, subtitle, fs, W - 2 * PAD - 20):
        d.text((PAD * SS, y * SS), line, font=fs, fill='#FFFFFF')
        y += 26

    # Dwie kreski tej samej długości — motyw znaku.
    bar_w, bar_h = 368, 30
    for i, c in enumerate((BAR_LIGHT, MORELA)):
        top = (COLOR_H - 108 + i * 52)
        d.rounded_rectangle([PAD * SS, top * SS, (PAD + bar_w) * SS, (top + bar_h) * SS], radius=bar_h / 2 * SS, fill=c)

    # Dół: odbiorcy, wydanie, zestaw znaków FE.
    fsm = font(FONT, 14)
    y0 = COLOR_H + 34
    for i, line in enumerate(audience):
        d.text((PAD * SS, (y0 + i * 21) * SS), line, font=fsm, fill=INK)
    for i, line in enumerate(('Wydanie 1.0', 'Bezpłatnie · PDF i EPUB')):
        d.text(((W - PAD) * SS - d.textlength(line, font=fsm), (y0 + i * 21) * SS), line, font=fsm, fill=MUTED)

    lock = Image.open(LOCKUP).convert('RGBA')
    lw = (W - 2 * PAD) * SS
    lh = round(lock.height * lw / lock.width)
    lock = lock.resize((lw, lh), getattr(Image, 'Resampling', Image).LANCZOS)
    img.paste(lock, (PAD * SS, (H - 30) * SS - lh), lock)

    return img.resize((W, H), getattr(Image, 'Resampling', Image).LANCZOS)


def variants(img):
    """Te same rozmiary i kadry, które WordPress zapisał dla oryginałów 620 × 877."""
    lz = getattr(Image, 'Resampling', Image).LANCZOS

    def hard(w, h):
        # image_resize_dimensions(): kadr od środka, bez powiększania.
        nw, nh = min(w, W), min(h, H)
        ratio = max(nw / W, nh / H)
        cw, ch = round(nw / ratio), round(nh / ratio)
        sx, sy = (W - cw) // 2, (H - ch) // 2
        return img.crop((sx, sy, sx + cw, sy + ch)).resize((nw, nh), lz)

    return {
        '150x150': hard(150, 150),       # thumbnail
        '212x300': img.resize((212, 300), lz),   # medium (proporcjonalnie)
        '480x678': img.resize((480, 678), lz),   # sd-cover
        '620x360': hard(640, 360),       # sd-card
        '620x640': hard(1280, 640),      # sd-wide
    }


def main():
    targets = sys.argv[1:] or TARGETS
    for name, *spec in COVERS:
        img = cover(*spec)
        for t in targets:
            os.makedirs(t, exist_ok=True)
            img.save(os.path.join(t, name + '.png'), optimize=True)
            for size, v in variants(img).items():
                v.save(os.path.join(t, '%s-%s.png' % (name, size)), optimize=True)
        print('  %-30s 01–04: %s  kolor %s' % (name + '.png', spec[0], spec[1]))
    print('zapisano do: ' + ' | '.join(targets))


if __name__ == '__main__':
    main()
