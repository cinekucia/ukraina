# -*- coding: utf-8 -*-
"""
Test WCAG 2.1 AA platformy STOP DYSKRYMINACJI.

Co sprawdza (dla każdej strony serwisu, PL / UA / EN):
  1. axe-core 4.11.1, reguły z tagami wcag2a, wcag2aa, wcag21a, wcag21aa
     (osobno: best-practice — raportowane, ale nie są kryteriami WCAG),
     na szerokości 1440 px i 375 px;
  2. klawiatura: przejście Tabem przez całą stronę — czy każdy element z fokusem
     ma widoczny wskaźnik, nie jest zasłonięty, czy nie ma pułapki (2.1.1, 2.1.2,
     2.4.3, 2.4.7), czy pierwszy Tab trafia w odnośnik „przejdź do treści" (2.4.1);
  3. zawijanie przy 320 px — brak przewijania w poziomie (1.4.10);
  4. powiększenie 200 % (widok 720 px) — brak przewijania w poziomie (1.4.4);
  5. odstępy w tekście wg 1.4.12 — czy tekst nie zostaje ucięty;
  6. struktura nagłówków: jeden H1, bez przeskoków poziomów (1.3.1, 2.4.6);
  7. punkty orientacyjne: jeden main, nawigacje z unikalnymi nazwami (1.3.1);
  8. unikalne tytuły stron (2.4.2), atrybut lang (3.1.1);
  9. etykieta w nazwie dostępnej (2.5.3), zduplikowane id (4.1.1).

Uruchomienie:  python wcag_test.py [--only-axe] [--pages /,/ua/]
Wyniki:        a11y/results/*.json oraz podsumowanie na ekranie.
"""
import json
import os
import re
import sys
import time
import urllib.request
from html.parser import HTMLParser

from playwright.sync_api import sync_playwright

# Adres testowanego serwisu: zmienna SD_BASE_URL, domyślnie lokalna kopia z Local.
BASE = os.environ.get('SD_BASE_URL', 'http://stop-dyskryminacji.local').rstrip('/')
HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, 'results')
# axe-core nie jest w repozytorium (patrz README.md) — ścieżkę można wskazać zmienną SD_AXE_PATH.
AXE = open(os.environ.get('SD_AXE_PATH', os.path.join(HERE, 'axe.min.js')), encoding='utf-8').read()
os.makedirs(OUT, exist_ok=True)

WCAG_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']

START = [
    '/', '/o-projekcie/', '/potrzebuje-pomocy/', '/publikacje/', '/aktualnosci/',
    '/samoocena/', '/gdzie-szukac-pomocy/', '/czy-to-juz-dyskryminacja/', '/kontakt/',
    '/ua/', '/en/', '/?s=praca', '/?s=zzqqxx', '/nie-ma-takiej-strony/',
    '/ua/nema-takoi-storinky/', '/en/no-such-page/',
]


# --------------------------------------------------------------------------
# Zbieranie adresów: strony startowe + wszystkie wewnętrzne odnośniki z nich
# --------------------------------------------------------------------------
class Links(HTMLParser):
    def __init__(self):
        super().__init__()
        self.hrefs = []

    def handle_starttag(self, tag, attrs):
        if tag == 'a':
            href = dict(attrs).get('href') or ''
            self.hrefs.append(href)


def fetch(url):
    try:
        with urllib.request.urlopen(url, timeout=30) as r:
            return r.read().decode('utf-8', 'replace')
    except Exception:
        return ''


def collect_pages():
    seen = []
    for path in START:
        if path not in seen:
            seen.append(path)
        p = Links()
        p.feed(fetch(BASE + path))
        for href in p.hrefs:
            if href.startswith(BASE):
                href = href[len(BASE):]
            if not href.startswith('/') or href.startswith('//'):
                continue
            href = href.split('#')[0]
            if re.search(r'/wp-(admin|content|includes|json|login)|/feed/|\.(pdf|epub|docx|xml)$', href):
                continue
            if href and href not in seen:
                seen.append(href)
    return seen


# --------------------------------------------------------------------------
# Skrypty wykonywane w przeglądarce
# --------------------------------------------------------------------------
JS_AXE = """async (tags) => {
  const slim = (arr) => arr.map(v => ({
    id: v.id, impact: v.impact, help: v.help, helpUrl: v.helpUrl, tags: v.tags,
    nodes: v.nodes.slice(0, 12).map(n => ({
      target: n.target.join(' '), html: n.html.slice(0, 220),
      summary: (n.failureSummary || '').slice(0, 400)
    })),
    count: v.nodes.length
  }));
  const wcag = await axe.run(document, { runOnly: { type: 'tag', values: tags }, resultTypes: ['violations', 'incomplete'] });
  const bp = await axe.run(document, { runOnly: { type: 'tag', values: ['best-practice'] }, resultTypes: ['violations'] });
  return { violations: slim(wcag.violations), incomplete: slim(wcag.incomplete), passes: wcag.passes.length, bestPractice: slim(bp.violations) };
}"""

JS_STRUCTURE = """() => {
  const vis = (el) => { const cs = getComputedStyle(el); return cs.display !== 'none' && cs.visibility !== 'hidden'; };
  const hs = [...document.querySelectorAll('h1,h2,h3,h4,h5,h6')].filter(h => !h.closest('[hidden]'));
  const levels = hs.map(h => ({ level: +h.tagName[1], text: h.textContent.trim().replace(/\\s+/g, ' ').slice(0, 60) }));
  const jumps = [];
  for (let i = 1; i < levels.length; i++) {
    if (levels[i].level > levels[i - 1].level + 1) jumps.push(levels[i - 1].level + '→' + levels[i].level + ' „' + levels[i].text + '"');
  }
  const accName = (el) => (el.getAttribute('aria-label') || (el.getAttribute('aria-labelledby') ? document.getElementById(el.getAttribute('aria-labelledby'))?.textContent : '') || '').trim();
  const navs = [...document.querySelectorAll('nav, [role=navigation]')].map(n => accName(n));
  const ids = {};
  document.querySelectorAll('[id]').forEach(e => { ids[e.id] = (ids[e.id] || 0) + 1; });
  const dupIds = Object.entries(ids).filter(([k, v]) => v > 1).map(([k, v]) => k + '×' + v);
  const topBanners = [...document.querySelectorAll('header, [role=banner]')].filter(h => !h.parentElement.closest('article,aside,main,nav,section,header,footer') || h.getAttribute('role') === 'banner');
  const topFooters = [...document.querySelectorAll('footer, [role=contentinfo]')].filter(h => !h.parentElement.closest('article,aside,main,nav,section,header,footer') || h.getAttribute('role') === 'contentinfo');
  // Etykieta w nazwie (2.5.3): widoczny tekst musi zawierać się w nazwie dostępnej.
  const labelInName = [];
  document.querySelectorAll('a[href], button').forEach(el => {
    if (!vis(el)) return;
    const visible = [...el.childNodes].map(function walk(n) {
      if (n.nodeType === 3) return n.textContent;
      if (n.nodeType !== 1) return '';
      if (n.getAttribute('aria-hidden') === 'true') return '[ukryte:' + n.textContent + ']';
      if (n.classList.contains('sd-sr-only') || n.classList.contains('screen-reader-text')) return '';
      return [...n.childNodes].map(walk).join('');
    }).join('').replace(/\\s+/g, ' ').trim();
    const aria = el.getAttribute('aria-label');
    if (visible.includes('[ukryte:')) {
      const shown = visible.replace(/\\[ukryte:([^\\]]*)\\]/g, '$1').trim();
      const nameParts = [...el.childNodes].map(function walk(n) {
        if (n.nodeType === 3) return n.textContent;
        if (n.nodeType !== 1 || n.getAttribute('aria-hidden') === 'true') return '';
        return [...n.childNodes].map(walk).join('');
      }).join('').replace(/\\s+/g, ' ').trim();
      if (shown && !nameParts.toLowerCase().includes(shown.toLowerCase())) labelInName.push('„' + shown + '" → nazwa „' + nameParts + '"');
    } else if (aria && visible && !aria.toLowerCase().includes(visible.toLowerCase())) {
      labelInName.push('„' + visible + '" → aria-label „' + aria + '"');
    }
  });
  return {
    title: document.title, lang: document.documentElement.lang,
    h1: levels.filter(l => l.level === 1).length, headings: levels, jumps,
    mains: document.querySelectorAll('main, [role=main]').length,
    banners: topBanners.length, contentinfos: topFooters.length,
    navs, dupIds, labelInName
  };
}"""

JS_OVERFLOW = """() => {
  const W = document.documentElement.clientWidth;
  const sw = document.documentElement.scrollWidth;
  const off = [];
  if (sw > W + 1) {
    document.querySelectorAll('body *').forEach(el => {
      const r = el.getBoundingClientRect();
      if (r.width === 0 || r.right <= W + 1) return;
      // Pomijamy elementy w kontenerach z własnym przewijaniem (dozwolone dla tabel).
      let p = el.parentElement, scrolled = false;
      while (p && p !== document.body) { const o = getComputedStyle(p).overflowX; if (o === 'auto' || o === 'scroll' || o === 'hidden') { scrolled = true; break; } p = p.parentElement; }
      if (!scrolled) off.push(el.tagName.toLowerCase() + '.' + String(el.className).split(' ').slice(0, 2).join('.') + ' right=' + Math.round(r.right));
    });
  }
  return { width: W, scrollWidth: sw, offenders: off.slice(0, 8) };
}"""

CSS_TEXT_SPACING = """
* { line-height: 1.5 !important; letter-spacing: 0.12em !important; word-spacing: 0.16em !important; }
p { margin-bottom: 2em !important; }
"""

JS_CLIPPED = """() => {
  const out = [];
  document.querySelectorAll('body *').forEach(el => {
    if (!el.childNodes.length) return;
    const hasText = [...el.childNodes].some(n => n.nodeType === 3 && n.textContent.trim());
    if (!hasText) return;
    const cs = getComputedStyle(el);
    if (cs.display === 'none' || cs.visibility === 'hidden') return;
    if (el.closest('.sd-sr-only, .screen-reader-text')) return;
    const clipY = ['hidden', 'clip'].includes(cs.overflowY) && el.scrollHeight > el.clientHeight + 2;
    const clipX = ['hidden', 'clip'].includes(cs.overflowX) && el.scrollWidth > el.clientWidth + 2;
    if (clipY || clipX) out.push(el.tagName.toLowerCase() + '.' + String(el.className).split(' ').slice(0, 2).join('.') + ' „' + el.textContent.trim().slice(0, 40) + '"');
  });
  return out.slice(0, 10);
}"""

JS_FOCUS_INFO = """() => {
  const el = document.activeElement;
  if (!el || el === document.body || el === document.documentElement) return null;
  const r = el.getBoundingClientRect();
  const cs = getComputedStyle(el);
  const outline = cs.outlineStyle !== 'none' && parseFloat(cs.outlineWidth) >= 1;
  const shadow = cs.boxShadow && cs.boxShadow !== 'none';
  const cx = Math.min(Math.max(r.left + r.width / 2, 0), innerWidth - 1);
  const cy = Math.min(Math.max(r.top + r.height / 2, 0), innerHeight - 1);
  const top = document.elementFromPoint(cx, cy);
  const obscured = !!top && !(el === top || el.contains(top) || top.contains(el) || (el.labels && [...el.labels].some(l => l.contains(top))));
  const path = (e) => { const p = []; for (let i = 0; e && e.nodeType === 1 && i < 3; i++, e = e.parentElement) p.push(e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + (typeof e.className === 'string' && e.className ? '.' + e.className.split(' ')[0] : '')); return p.join(' < '); };
  return {
    idx: [...document.querySelectorAll('*')].indexOf(el),
    key: path(el), text: (el.innerText || el.value || el.getAttribute('aria-label') || '').trim().replace(/\\s+/g, ' ').slice(0, 40),
    outline, shadow, outlineColor: cs.outlineColor, width: Math.round(r.width), height: Math.round(r.height),
    visible: r.width > 0 && r.height > 0 && cs.visibility !== 'hidden' && cs.opacity !== '0',
    obscured, inView: r.bottom > 0 && r.top < innerHeight
  };
}"""


def keyboard_walk(page, limit=250):
    """Przejście Tabem przez stronę. Zwraca listę problemów i liczbę elementów."""
    problems, keys, first = [], [], None
    # Bez klikania myszą: kliknięcie ustawia punkt startowy nawigacji Tabem
    # w miejscu kliknięcia i pomija elementy przed nim. Strona jest świeżo
    # załadowana, więc Tab startuje od początku dokumentu — jak u użytkownika.
    for i in range(limit):
        page.keyboard.press('Tab')
        page.wait_for_timeout(20)
        info = page.evaluate(JS_FOCUS_INFO)
        if info is None:
            if i > 0:
                break                  # fokus wrócił do dokumentu — koniec obiegu
            continue
        # Tożsamość elementu = pozycja w dokumencie (przyciski radio w kolejnych
        # pytaniach mają identyczną ścieżkę i wartość, a to różne elementy).
        key = '%s|%s|%s' % (info['idx'], info['key'], info['text'])
        if first is None:
            first = info
        elif key == '%s|%s|%s' % (first['idx'], first['key'], first['text']) and i > 2:
            break                      # pełny obieg
        if keys[-3:] == [key, key, key]:
            problems.append('pułapka klawiatury na ' + key)
            break
        keys.append(key)
        if not info['visible']:
            problems.append('fokus na niewidocznym elemencie: ' + key)
        elif not (info['outline'] or info['shadow']):
            problems.append('brak widocznego wskaźnika fokusu: ' + key)
        if info['obscured']:
            problems.append('element z fokusem zasłonięty: ' + key)
    return {'count': len(keys), 'first': first, 'problems': problems}


def skip_link_check(page):
    page.keyboard.press('Tab')
    first = page.evaluate('() => { const e = document.activeElement; return e ? { cls: e.className, href: e.getAttribute("href"), text: e.textContent.trim() } : null; }')
    ok_first = bool(first and 'skip' in str(first.get('cls', '')))
    page.keyboard.press('Enter')
    time.sleep(0.2)
    page.keyboard.press('Tab')
    landed = page.evaluate('() => { const e = document.activeElement; return !!(e && e.closest("main")); }')
    return {'first': first, 'firstIsSkip': ok_first, 'tabAfterSkipInMain': landed}


def test_page(browser, path, only_axe=False):
    res = {'path': path}
    url = BASE + path

    # --- 1440 px: axe, struktura, klawiatura, odstępy tekstu -------------
    # reduced_motion: motyw włącza płynne przewijanie, a w trakcie animacji element
    # z fokusem nie jest jeszcze w widoku — test uznawał go za zasłonięty.
    ctx = browser.new_context(viewport={'width': 1440, 'height': 900}, locale='pl-PL', reduced_motion='reduce')
    page = ctx.new_page()
    resp = page.goto(url, wait_until='networkidle')
    res['status'] = resp.status if resp else None
    page.add_script_tag(content=AXE)
    res['axe_desktop'] = page.evaluate(JS_AXE, WCAG_TAGS)
    if not only_axe:
        res['structure'] = page.evaluate(JS_STRUCTURE)
        page.goto(url, wait_until='networkidle')
        res['skip'] = skip_link_check(page)
        page.goto(url, wait_until='networkidle')
        res['keyboard'] = keyboard_walk(page)
        page.goto(url, wait_until='networkidle')
        page.add_style_tag(content=CSS_TEXT_SPACING)
        time.sleep(0.2)
        res['text_spacing_clipped'] = page.evaluate(JS_CLIPPED)
    ctx.close()

    # --- 375 px: axe --------------------------------------------------------
    ctx = browser.new_context(viewport={'width': 375, 'height': 812}, locale='pl-PL', is_mobile=True, has_touch=True)
    page = ctx.new_page()
    page.goto(url, wait_until='networkidle')
    page.add_script_tag(content=AXE)
    res['axe_mobile'] = page.evaluate(JS_AXE, WCAG_TAGS)
    ctx.close()

    if not only_axe:
        # --- 320 px: zawijanie (1.4.10) ------------------------------------
        ctx = browser.new_context(viewport={'width': 320, 'height': 640}, locale='pl-PL')
        page = ctx.new_page()
        page.goto(url, wait_until='networkidle')
        res['reflow_320'] = page.evaluate(JS_OVERFLOW)
        ctx.close()
        # --- 200 %: widok 720 px przy gęstości 2 (1.4.4) --------------------
        ctx = browser.new_context(viewport={'width': 720, 'height': 450}, device_scale_factor=2, locale='pl-PL')
        page = ctx.new_page()
        page.goto(url, wait_until='networkidle')
        res['zoom_200'] = page.evaluate(JS_OVERFLOW)
        ctx.close()
    return res


def summarize(results):
    lines = []
    titles = {}
    totals = {'wcag': 0, 'bp': 0, 'kbd': 0, 'reflow': 0, 'zoom': 0, 'spacing': 0, 'struct': 0}
    for r in results:
        p = r['path']
        issues = []
        seen_rules = {}
        for view in ('axe_desktop', 'axe_mobile'):
            for v in r[view]['violations']:
                seen_rules.setdefault(v['id'], (v, set()))[1].add(view[4:])
        for rid, (v, views) in seen_rules.items():
            issues.append('WCAG axe [%s/%s] %s — %s (%d)' % (v['impact'], '+'.join(sorted(views)), rid, v['help'], v['count']))
            for n in v['nodes'][:3]:
                issues.append('      ' + n['target'][:110])
            totals['wcag'] += 1
        bp = {}
        for view in ('axe_desktop', 'axe_mobile'):
            for v in r[view]['bestPractice']:
                bp.setdefault(v['id'], v)
        for rid, v in bp.items():
            issues.append('best-practice %s — %s (%d)' % (rid, v['help'], v['count']))
            for n in v['nodes'][:2]:
                issues.append('      ' + n['target'][:110])
            totals['bp'] += 1
        inc = {}
        for view in ('axe_desktop', 'axe_mobile'):
            for v in r[view]['incomplete']:
                inc.setdefault(v['id'], v)
        if inc:
            issues.append('do ręcznej weryfikacji (axe incomplete): ' + ', '.join('%s(%d)' % (k, v['count']) for k, v in inc.items()))
        if 'structure' in r:
            s = r['structure']
            titles.setdefault(s['title'], []).append(p)
            if s['h1'] != 1:
                issues.append('H1: %d (ma być 1)' % s['h1']); totals['struct'] += 1
            for j in s['jumps']:
                issues.append('przeskok nagłówków ' + j); totals['struct'] += 1
            if s['mains'] != 1:
                issues.append('elementów main: %d' % s['mains']); totals['struct'] += 1
            if s['banners'] != 1 or s['contentinfos'] != 1:
                issues.append('banner: %d, contentinfo: %d (ma być po 1)' % (s['banners'], s['contentinfos'])); totals['struct'] += 1
            names = [n for n in s['navs']]
            if len(set(names)) != len(names) or '' in names:
                issues.append('nawigacje bez unikalnych nazw: %s' % names); totals['struct'] += 1
            if s['dupIds']:
                issues.append('zduplikowane id: %s' % s['dupIds']); totals['struct'] += 1
            for l in s['labelInName']:
                issues.append('2.5.3 etykieta w nazwie: ' + l); totals['struct'] += 1
            sk = r['skip']
            if not sk['firstIsSkip'] or not sk['tabAfterSkipInMain']:
                issues.append('odnośnik „przejdź do treści": pierwszy=%s, po użyciu w main=%s' % (sk['firstIsSkip'], sk['tabAfterSkipInMain'])); totals['kbd'] += 1
            for kp in r['keyboard']['problems']:
                issues.append('klawiatura: ' + kp); totals['kbd'] += 1
            if r['reflow_320']['scrollWidth'] > r['reflow_320']['width'] + 1:
                issues.append('320 px: przewijanie w poziomie %d > %d %s' % (r['reflow_320']['scrollWidth'], r['reflow_320']['width'], r['reflow_320']['offenders'])); totals['reflow'] += 1
            if r['zoom_200']['scrollWidth'] > r['zoom_200']['width'] + 1:
                issues.append('200 %%: przewijanie w poziomie %d > %d %s' % (r['zoom_200']['scrollWidth'], r['zoom_200']['width'], r['zoom_200']['offenders'])); totals['zoom'] += 1
            for c in r['text_spacing_clipped']:
                issues.append('1.4.12 ucięty tekst: ' + c); totals['spacing'] += 1
        head = '%s  [HTTP %s]' % (p, r['status'])
        if 'keyboard' in r:
            head += '  fokusowalnych: %d' % r['keyboard']['count']
        lines.append(head)
        lines.extend('   ' + i for i in issues) if issues else lines.append('   bez uwag')
    dups = {t: ps for t, ps in titles.items() if len(ps) > 1}
    if dups:
        lines.append('\n2.4.2 powtórzone tytuły stron:')
        for t, ps in dups.items():
            lines.append('   „%s": %s' % (t, ', '.join(ps)))
    lines.append('\nPODSUMOWANIE: reguły WCAG naruszone (axe): %(wcag)d | best-practice: %(bp)d | klawiatura: %(kbd)d | 320 px: %(reflow)d | 200 %%: %(zoom)d | odstępy tekstu: %(spacing)d | struktura: %(struct)d' % totals)
    return '\n'.join(lines), totals


def main():
    only_axe = '--only-axe' in sys.argv
    if '--pages' in sys.argv:
        pages = sys.argv[sys.argv.index('--pages') + 1].split(',')
    else:
        pages = collect_pages()
    print('Stron do testu: %d' % len(pages), flush=True)
    results = []
    with sync_playwright() as p:
        browser = p.chromium.launch()
        for i, path in enumerate(pages, 1):
            t = time.time()
            try:
                r = test_page(browser, path, only_axe)
            except Exception as e:
                r = {'path': path, 'error': str(e)[:300]}
            results.append(r)
            print('  [%2d/%d] %-52s %.1fs %s' % (i, len(pages), path, time.time() - t, r.get('error', '')), flush=True)
        browser.close()
    ok = [r for r in results if 'error' not in r]
    with open(os.path.join(OUT, 'wyniki.json'), 'w', encoding='utf-8') as fh:
        json.dump(results, fh, ensure_ascii=False, indent=1)
    text, totals = summarize(ok)
    with open(os.path.join(OUT, 'podsumowanie.txt'), 'w', encoding='utf-8') as fh:
        fh.write(text)
    print()
    print(text)


if __name__ == '__main__':
    main()
