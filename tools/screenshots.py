# -*- coding: utf-8 -*-
"""
Zrzuty ekranu do odbioru (Załącznik nr 10: strona główna i trzy podstrony, widok
mobilny 375 px) i do prezentacji. Zapis do katalogu prezentacja/.

Uruchomienie:  python tools/screenshots.py
Adres serwisu: zmienna SD_BASE_URL (domyślnie http://stop-dyskryminacji.local).

Zrzut okna zgody (18) powstaje tylko wtedy, gdy w ustawieniach jest identyfikator
GA4 — bez niego okno się nie pokazuje, bo serwis nie ma o co pytać.
Zrzuty panelu (14–17) wymagają zalogowania i nie są tu odtwarzane.
"""
import os
import sys

from playwright.sync_api import sync_playwright

BASE = os.environ.get('SD_BASE_URL', 'http://stop-dyskryminacji.local').rstrip('/')
OUT = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'prezentacja')

DESKTOP = [
    ('01-strona-glowna', '/'),
    ('02-o-projekcie', '/o-projekcie/'),
    ('03-potrzebuje-pomocy', '/potrzebuje-pomocy/'),
    ('04-lista-publikacji', '/publikacje/'),
    ('05-widok-publikacji', '/publikacje/kodeks-rownosci-i-inkluzywnosci/'),
    ('06-artykul', '/czy-to-juz-dyskryminacja/'),
    ('07-baza-wiedzy', '/aktualnosci/'),
    ('08-samoocena-pracodawcy', '/samoocena/'),
    ('09-nawigator-instytucji', '/gdzie-szukac-pomocy/'),
    ('10-strona-404', '/nie-ma-takiej-strony/'),
    ('19-ua-strona-glowna', '/ua/'),
    ('20-ua-prawa-i-stopka', '/ua/prava/'),
    ('21-en-formularz', '/en/get-help/'),
    ('22-ua-strona-404', '/ua/nema-takoi-storinky/'),
    ('24-mapa-strony', '/mapa-strony/'),
    ('25-deklaracja-dostepnosci', '/deklaracja-dostepnosci/'),
]
MOBILE = [
    ('11-mobilny-strona-glowna', '/', 1500),
    ('12-mobilny-formularz', '/potrzebuje-pomocy/', 1500),
    ('13-mobilny-o-projekcie', '/o-projekcie/', 1400),
    ('23-mobilny-ua', '/ua/', 1500),
]


def main():
    os.makedirs(OUT, exist_ok=True)
    with sync_playwright() as p:
        browser = p.chromium.launch()
        ctx = browser.new_context(viewport={'width': 1440, 'height': 900}, reduced_motion='reduce')
        page = ctx.new_page()
        for name, path in DESKTOP:
            page.goto(BASE + path, wait_until='networkidle')
            page.evaluate('() => document.fonts && document.fonts.ready')
            page.screenshot(path=os.path.join(OUT, name + '.png'), full_page=True)
            print('  %-32s %s' % (name + '.png', path))
        # Okno zgody — w pierwszym ekranie, jak widzi je nowa osoba.
        page.goto(BASE + '/', wait_until='networkidle')
        if page.locator('#sd-consent').is_visible():
            page.screenshot(path=os.path.join(OUT, '18-okno-zgody-cookie.png'))
            print('  %-32s %s' % ('18-okno-zgody-cookie.png', '/ (okno zgody)'))
        else:
            print('  18-okno-zgody-cookie.png         pominięty — brak identyfikatora GA4, okno się nie pokazuje')
        ctx.close()

        # Telefon: dokładnie 375 px szerokości, pierwszy ekran i dalej (przycięte do wysokości).
        ctx = browser.new_context(viewport={'width': 375, 'height': 812}, device_scale_factor=2,
                                  is_mobile=True, has_touch=True, reduced_motion='reduce')
        page = ctx.new_page()
        for name, path, height in MOBILE:
            page.goto(BASE + path, wait_until='networkidle')
            page.screenshot(path=os.path.join(OUT, name + '.png'), full_page=True,
                            clip={'x': 0, 'y': 0, 'width': 375, 'height': height})
            print('  %-32s %s (375 px)' % (name + '.png', path))
        ctx.close()
        browser.close()
    return 0


if __name__ == '__main__':
    sys.exit(main())
