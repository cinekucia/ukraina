# -*- coding: utf-8 -*-
"""
Obrazy zastępcze artykułów (1280 × 720) w palecie z projektu „STOP DYSKRYMINACJI
— logo i layout publikacji": tło papierowe, rysunek morski i ceglany, morelowy
pasek u dołu. Kompozycje geometryczne — bez zdjęć ludzi (ryzyko stereotypu,
wizerunku i praw autorskich). Tytuł jest w nagłówku wpisu, więc obraz nie jest
jedynym nośnikiem informacji (WCAG 1.4.5).

Pliki mają te same nazwy co obecne w bibliotece mediów, a miniatury są
odtwarzane w wymiarach i kadrach, które WordPress zapisał (pliki *-WxH.png
w katalogu docelowym): proporcjonalne — zmniejszone, inne — kadr od środka.

Użycie:  python tools/build_article_images.py [katalog ...]
         bez argumentów: wp-content/uploads/2026/09 lokalnej kopii i folder dla WinSCP
"""
import os
import re
import sys
from PIL import Image, ImageDraw, ImageFont

REPO = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
TARGETS = [os.path.join(REPO, 'stop-dyskryminacji', 'app', 'public', 'wp-content', 'uploads', '2026', '09'),
           os.path.join(REPO, 'dist', 'wgraj-przez-winscp', 'wp-content', 'uploads', '2026', '09')]
FB = 'C:/Windows/Fonts/segoeuib.ttf'

INK = (20, 24, 31)
TEAL = (11, 85, 99)            # morski — marka
BRICK = (194, 65, 12)          # ceglany — działanie
MORELA = (245, 158, 107)       # detal (tylko grafika)
SOFT = (230, 240, 241)         # morski jasny
PAPER = (250, 247, 242)        # papier
BORDER = (220, 214, 203)
WHITE = (255, 255, 255)
W, H = 1280, 720


def a_question(d):
    d.ellipse([W // 2 - 150, H // 2 - 150, W // 2 + 150, H // 2 + 150], outline=TEAL, width=14)
    d.text((W // 2, H // 2 - 14), '?', font=ImageFont.truetype(FB, 190), fill=TEAL, anchor='mm')
    for x in (W // 2 - 420, W // 2 + 420):
        d.ellipse([x - 66, H // 2 - 66, x + 66, H // 2 + 66], outline=BORDER, width=10)


def a_documents(d):
    for i, off in enumerate((-190, 0, 190)):
        x, y = W // 2 + off - 105, H // 2 - 140 + (i % 2) * 22
        d.rectangle([x, y, x + 210, y + 280], fill=WHITE, outline=BORDER, width=4)
        d.rectangle([x, y, x + 210, y + 10], fill=BRICK if i == 1 else TEAL)
        for ln in range(5):
            w = 150 if ln % 2 == 0 else 108
            d.line([(x + 30, y + 62 + ln * 38), (x + 30 + w, y + 62 + ln * 38)], fill=BORDER, width=8)


def a_gap(d):
    base = H - 170
    for i, (v, c) in enumerate(zip((150, 260, 205, 320), (BORDER, TEAL, BORDER, BRICK))):
        x = W // 2 - 300 + i * 165
        d.rectangle([x, base - v, x + 110, base], fill=c)
    d.line([(W // 2 - 340, base), (W // 2 + 340, base)], fill=INK, width=6)


def a_split(d):
    d.rectangle([0, 0, W // 2 - 4, H], fill=SOFT)
    cy = H // 2
    for x, col in ((W // 4, TEAL), (W - W // 4, BRICK)):
        d.ellipse([x - 62, cy - 150, x + 62, cy - 26], outline=col, width=12)
        d.arc([x - 104, cy - 20, x + 104, cy + 190], 180, 360, fill=col, width=12)
    d.line([(W // 2, 80), (W // 2, H - 80)], fill=INK, width=5)


def a_funnel(d):
    cx, cy = W // 2, H // 2
    for i, w in enumerate((330, 250, 170, 90)):
        y = cy - 170 + i * 92
        d.rectangle([cx - w, y, cx + w, y + 62], outline=TEAL if i < 3 else BRICK,
                    width=10, fill=WHITE if i < 3 else None)


def a_access(d):
    cx, cy = W // 2, H // 2
    d.ellipse([cx - 180, cy - 180, cx + 180, cy + 180], outline=TEAL, width=14)
    d.ellipse([cx - 34, cy - 138, cx + 34, cy - 70], fill=TEAL)
    d.line([(cx - 120, cy - 30), (cx + 120, cy - 30)], fill=TEAL, width=14)
    d.line([(cx - 62, cy - 30), (cx - 62, cy + 128)], fill=TEAL, width=14)
    d.line([(cx + 62, cy - 30), (cx + 62, cy + 128)], fill=TEAL, width=14)


ARTICLES = [
    ('art-czy-to-dyskryminacja', a_question),
    ('art-dokumentowanie', a_documents),
    ('art-luka-placowa', a_gap),
    ('art-mobbing-a-dyskryminacja', a_split),
    ('art-rekrutacja', a_funnel),
    ('art-usprawnienia', a_access),
]


def render(painter):
    ss = 2
    img = Image.new('RGB', (W, H), PAPER)
    painter(ImageDraw.Draw(img))
    d = ImageDraw.Draw(img)
    d.rectangle([0, H - 12, W, H], fill=MORELA)        # morelowy pasek — detal z sygnetu
    big = img.resize((W * ss, H * ss), getattr(Image, 'Resampling', Image).NEAREST)
    return big.resize((W, H), getattr(Image, 'Resampling', Image).LANCZOS)


def derive(img, w, h):
    """Miniatura jak w WordPressie: proporcjonalna albo kadr od środka."""
    lz = getattr(Image, 'Resampling', Image).LANCZOS
    if abs(w / h - W / H) < 0.01:
        return img.resize((w, h), lz)
    ratio = max(w / W, h / H)
    cw, ch = round(w / ratio), round(h / ratio)
    sx, sy = (W - cw) // 2, (H - ch) // 2
    return img.crop((sx, sy, sx + cw, sy + ch)).resize((w, h), lz)


def main():
    targets = sys.argv[1:] or TARGETS
    for name, painter in ARTICLES:
        img = render(painter)
        for t in targets:
            os.makedirs(t, exist_ok=True)
            img.save(os.path.join(t, name + '.png'), optimize=True)
            done = 0
            for f in os.listdir(t):
                m = re.match(re.escape(name) + r'-(\d+)x(\d+)\.png$', f)
                if m:
                    derive(img, int(m.group(1)), int(m.group(2))).save(os.path.join(t, f), optimize=True)
                    done += 1
        print('  %-34s + %d miniatur' % (name + '.png', done))


if __name__ == '__main__':
    main()
