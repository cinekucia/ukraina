# -*- coding: utf-8 -*-
"""
Audyt odnośników wewnętrznych: przechodzi po serwisie (PL, UA, EN) i sprawdza
kod odpowiedzi każdego adresu, do którego prowadzi jakikolwiek odnośnik.

Uruchomienie:  python tests/links/link_audit.py
Adres serwisu: zmienna SD_BASE_URL (domyślnie http://stop-dyskryminacji.local).
"""
import os
import re
import sys
import urllib.error
import urllib.request
from html.parser import HTMLParser

BASE = os.environ.get('SD_BASE_URL', 'http://stop-dyskryminacji.local').rstrip('/')
HOST = re.sub(r'^https?://', '', BASE).split('/')[0]
START = ['/', '/ua/', '/en/', '/mapa-strony/', '/nie-ma-takiej-strony/']
LIMIT = 300


class Links(HTMLParser):
    def __init__(self):
        super().__init__()
        self.hrefs = []

    def handle_starttag(self, tag, attrs):
        if tag == 'a':
            self.hrefs.append(dict(attrs).get('href') or '')


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


opener = urllib.request.build_opener(NoRedirect)


def get(path):
    try:
        r = opener.open(BASE + path, timeout=30)
        return r.status, r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace') if e.fp else ''
    except Exception:  # noqa: BLE001
        return 0, ''


def normalize(href):
    href = href.strip()
    if href.startswith(BASE):
        href = href[len(BASE):]
    if re.match(r'^(https?:)?//', href) or re.match(r'^(mailto|tel|javascript):', href, re.I):
        return None
    if not href.startswith('/'):
        return None
    href = href.split('#')[0].split('?')[0]
    if re.search(r'/wp-(admin|login|content|includes|json)|/feed/?$|\.(pdf|epub|docx|xml|png|jpe?g)$', href):
        return None
    return href.rstrip('/') + '/' if href else None


def main():
    queue = list(START)
    seen = {}            # adres -> kod
    sources = {}         # adres -> strony, które do niego linkują
    while queue and len(seen) < LIMIT:
        path = queue.pop(0)
        if path in seen:
            continue
        code, html = get(path)
        seen[path] = code
        if code != 200 or not html:
            continue
        parser = Links()
        parser.feed(html)
        for href in parser.hrefs:
            target = normalize(href)
            if not target:
                continue
            sources.setdefault(target, set()).add(path)
            if target not in seen and target not in queue:
                queue.append(target)

    linked = {p: c for p, c in seen.items() if p in sources}
    bad = {p: c for p, c in linked.items() if c != 200}
    print('Sprawdzono %d unikalnych adresów wewnętrznych (z odnośników)' % len(linked))
    print('  działa:     %d' % (len(linked) - len(bad)))
    print('  NIE DZIAŁA: %d' % len(bad))
    for p, c in sorted(bad.items()):
        print('   %-50s HTTP %s  (linkowany z: %s)' % (p, c, ', '.join(sorted(sources[p]))[:120]))
    return 1 if bad else 0


if __name__ == '__main__':
    sys.exit(main())
