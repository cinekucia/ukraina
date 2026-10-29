# -*- coding: utf-8 -*-
"""
Stany interaktywne, których nie widać w statycznym teście strony:

  A. menu na telefonie obsługiwane wyłącznie klawiaturą (2.1.1, 2.1.2, 2.4.3, 4.1.2),
  B. formularz zgłoszeniowy po wysłaniu z błędami — PL, UA, EN (3.3.1, 3.3.3, 4.1.3),
  C. wynik samooceny (4.1.3, 2.4.3),
  D. okno zgody na pliki cookie (2.1.1, 2.4.3, 4.1.2) — tylko gdy w ustawieniach
     jest identyfikator GA4 (na kopii testowej wystarczy fikcyjny G-TEST000000),
  E. tryb wysokiego kontrastu Windows (forced-colors) — widoczność fokusu.

Na każdym stanie uruchamiany jest też axe-core (reguły WCAG 2.1 A/AA).
Uruchomienie: python interactions.py [A,B,C,D,E]
"""
import json
import os
import sys
import time

from playwright.sync_api import sync_playwright

# Adres testowanego serwisu: zmienna SD_BASE_URL, domyślnie lokalna kopia z Local.
BASE = os.environ.get('SD_BASE_URL', 'http://stop-dyskryminacji.local').rstrip('/')
HERE = os.path.dirname(os.path.abspath(__file__))
# axe-core nie jest w repozytorium (patrz README.md) — ścieżkę można wskazać zmienną SD_AXE_PATH.
AXE = open(os.environ.get('SD_AXE_PATH', os.path.join(HERE, 'axe.min.js')), encoding='utf-8').read()
TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']
results = []


def axe(page, label):
    page.add_script_tag(content=AXE)
    r = page.evaluate("""async (tags) => {
      const res = await axe.run(document, { runOnly: { type: 'tag', values: tags } });
      return res.violations.map(v => ({ id: v.id, impact: v.impact, help: v.help, nodes: v.nodes.map(n => n.target.join(' ')).slice(0, 5) }));
    }""", TAGS)
    check(label + ': axe WCAG 2.1 A/AA bez naruszeń', not r, '; '.join('%s %s' % (v['id'], v['nodes']) for v in r))


def check(label, ok, detail=''):
    results.append((label, bool(ok), detail))
    print('  [%s] %s %s' % (' OK ' if ok else 'BLAD', label, ('— ' + str(detail)) if detail and not ok else ''), flush=True)


def active(page):
    return page.evaluate("""() => { const e = document.activeElement; if (!e) return null;
      return { tag: e.tagName.toLowerCase(), id: e.id, cls: String(e.className), label: e.getAttribute('aria-label'), text: (e.innerText || '').trim().slice(0, 40),
               inDialog: !!e.closest('[role=dialog], .wp-block-navigation__responsive-container.is-menu-open') }; }""")


# --------------------------------------------------------------------------
def test_mobile_menu(browser, path):
    print('\nA. Menu mobilne z klawiatury: ' + path)
    ctx = browser.new_context(viewport={'width': 375, 'height': 812}, reduced_motion='reduce')
    page = ctx.new_page()
    page.goto(BASE + path, wait_until='networkidle')
    opener = page.locator('.sd-masthead button.wp-block-navigation__responsive-container-open').first
    check('przycisk otwierający menu ma nazwę dostępną', bool(opener.get_attribute('aria-label')), opener.get_attribute('aria-label'))
    # Dojście Tabem do przycisku.
    reached = False
    for _ in range(25):
        page.keyboard.press('Tab')
        a = active(page)
        if a and 'responsive-container-open' in a['cls']:
            reached = True
            break
    check('przycisk menu osiągalny klawiszem Tab', reached)
    page.keyboard.press('Enter')
    page.wait_for_timeout(300)
    dialog = page.locator('.wp-block-navigation__responsive-container.is-menu-open')
    check('Enter otwiera menu', dialog.count() == 1)
    # Rdzeń WordPressa nadaje role="dialog" wewnętrznemu elementowi okna menu.
    inner = page.locator('.wp-block-navigation__responsive-container.is-menu-open [role=dialog]')
    check('menu ma role="dialog", aria-modal="true" i nazwę', inner.count() == 1 and inner.first.get_attribute('aria-modal') == 'true' and bool(inner.first.get_attribute('aria-label') or inner.first.get_attribute('aria-labelledby')),
          (inner.first.get_attribute('aria-label') if inner.count() else None))
    a = active(page)
    check('fokus przenosi się do otwartego menu', bool(a and a['inDialog']), a)
    axe(page, 'otwarte menu mobilne')
    escaped = False
    for _ in range(20):
        page.keyboard.press('Tab')
        a = active(page)
        if not (a and a['inDialog']):
            escaped = True
            break
    check('Tab nie wychodzi poza otwarte menu (modalne okno)', not escaped, a)
    page.keyboard.press('Escape')
    page.wait_for_timeout(300)
    check('Escape zamyka menu', page.locator('.wp-block-navigation__responsive-container.is-menu-open').count() == 0)
    a = active(page)
    check('po zamknięciu fokus wraca na przycisk menu', bool(a and 'responsive-container-open' in a['cls']), a)
    ctx.close()


# --------------------------------------------------------------------------
def test_form_errors(browser, path, expect_lang):
    print('\nB. Formularz z błędami: ' + path)
    ctx = browser.new_context(viewport={'width': 1440, 'height': 900}, reduced_motion='reduce')
    page = ctx.new_page()
    page.goto(BASE + path, wait_until='networkidle')
    page.fill('#sdc-email', 'to-nie-jest-adres')
    time.sleep(5)   # formularz odrzuca wysyłkę szybszą niż 4 s jako robota
    page.locator('#sd-help-form button[type=submit]').click()
    page.wait_for_load_state('networkidle')
    check('po błędzie wracamy na tę samą stronę', page.url.startswith(BASE + path.split('?')[0]), page.url)
    summary = page.locator('#sd-errors')
    check('podsumowanie błędów jest na stronie', summary.count() == 1)
    if summary.count():
        check('podsumowanie ma role="alert"', summary.get_attribute('role') == 'alert')
        a = active(page)
        check('fokus ustawiony na podsumowaniu błędów', bool(a and a['id'] == 'sd-errors'), a)
        links = page.eval_on_selector_all('#sd-errors a', 'els => els.map(e => [e.getAttribute("href"), !!document.querySelector(e.getAttribute("href"))])')
        check('każdy błąd w podsumowaniu prowadzi do swojego pola', all(ok for _, ok in links), links)
        texts = page.eval_on_selector_all('#sd-errors li', 'els => els.map(e => e.textContent.trim())')
        polish = [t for t in texts if any(ch in t for ch in 'ąćęłńśźż')]
        if expect_lang == 'pl':
            check('komunikaty błędów po polsku', bool(polish) and len(polish) == len(texts), texts)
        else:
            check('komunikaty błędów w języku strony (%s), nie po polsku' % expect_lang, not polish, texts)
    for field in ('imie', 'email', 'opis', 'zgoda'):
        el = page.locator('#sdc-' + field)
        if not el.count():
            continue
        inv = el.get_attribute('aria-invalid')
        desc = el.get_attribute('aria-describedby') or ''
        err_ok = any(page.locator('#' + d).count() and 'error' in d for d in desc.split())
        check('pole %s: aria-invalid="true" i opis błędu powiązany aria-describedby' % field, inv == 'true' and err_ok, (inv, desc))
    check('wpisana wartość e-mail zachowana po błędzie', page.input_value('#sdc-email') == 'to-nie-jest-adres', page.input_value('#sdc-email'))
    axe(page, 'formularz z błędami ' + path)
    ctx.close()


# --------------------------------------------------------------------------
def test_self_assessment(browser):
    print('\nC. Samoocena — wynik')
    ctx = browser.new_context(viewport={'width': 1440, 'height': 900}, reduced_motion='reduce')
    page = ctx.new_page()
    page.goto(BASE + '/samoocena/', wait_until='networkidle')
    # Odpowiedzi klawiaturą: Tab do pierwszej grupy, strzałki zmieniają wybór w grupie radio.
    first_radio = page.locator('#sd-quiz input[type=radio]').first
    first_radio.focus()
    page.keyboard.press('Space')
    check('radio zaznaczane klawiszem Spacja', first_radio.is_checked())
    page.keyboard.press('ArrowDown')
    second = page.locator('#sd-quiz fieldset').first.locator('input[type=radio]').nth(1)
    check('strzałka zmienia wybór w grupie', second.is_checked())
    for fs in page.locator('#sd-quiz fieldset').all():
        fs.locator('input[type=radio]').nth(1).check()
    page.locator('#sd-quiz button[type=submit]').focus()
    page.keyboard.press('Enter')
    page.wait_for_timeout(400)
    result = page.locator('#sd-quiz-result')
    check('wynik pokazany po Enter', result.is_visible())
    a = active(page)
    check('fokus przeniesiony na wynik', bool(a and a['id'] == 'sd-quiz-result'), a)
    name = page.evaluate("""() => { const r = document.getElementById('sd-quiz-result');
        const l = r.getAttribute('aria-labelledby'); return (r.getAttribute('role') || '') + '|' + (l && document.getElementById(l) ? document.getElementById(l).textContent.trim() : ''); }""")
    check('obszar wyniku ma rolę i nazwę (czytnik ogłasza, co to za treść)', name.split('|')[0] in ('region', 'group', 'status') and bool(name.split('|')[1]), name)
    axe(page, 'samoocena z wynikiem')
    ctx.close()


# --------------------------------------------------------------------------
def test_consent(browser):
    print('\nD. Okno zgody na pliki cookie')
    ctx = browser.new_context(viewport={'width': 1440, 'height': 900}, reduced_motion='reduce')
    page = ctx.new_page()
    page.goto(BASE + '/', wait_until='networkidle')
    banner = page.locator('#sd-consent')
    if not banner.count() or not banner.is_visible():
        check('okno zgody widoczne (wymaga identyfikatora GA4 na czas testu)', False)
        ctx.close()
        return
    check('okno zgody widoczne', True)
    check('okno ma nazwę dostępną (aria-labelledby)', bool(page.evaluate("() => { const b = document.getElementById('sd-consent'); const l = b.getAttribute('aria-labelledby'); return l && document.getElementById(l) && document.getElementById(l).textContent.trim(); }")))
    axe(page, 'okno zgody')
    # Ile Tabów do pierwszego przycisku okna?
    steps = None
    for i in range(1, 80):
        page.keyboard.press('Tab')
        a = active(page)
        if a and page.evaluate("() => !!document.activeElement.closest('#sd-consent')"):
            steps = i
            break
    check('przyciski okna osiągalne klawiaturą', steps is not None, steps)
    check('okno zgody jest na początku kolejności fokusu (najpóźniej 3. Tab)', steps is not None and steps <= 3, steps)
    # Wybór klawiaturą.
    page.locator('#sd-consent [data-sd-consent="denied"]').focus()
    page.keyboard.press('Enter')
    page.wait_for_timeout(200)
    check('Enter na „Tylko niezbędne" zamyka okno', not banner.is_visible())
    a = active(page)
    check('po zamknięciu fokus nie ląduje na <body>', bool(a and a['tag'] != 'body'), a)
    stored = page.evaluate("() => localStorage.getItem('sd_consent_v1')")
    check('decyzja zapisana', stored == 'denied', stored)
    gtag = page.evaluate("() => [...document.scripts].some(s => (s.src || '').includes('googletagmanager'))")
    check('po odmowie nie załadowano Google Analytics', not gtag)
    ctx.close()

    # Fokus nie chowa się pod oknem przy przechodzeniu Tabem (okno przyklejone do dołu).
    ctx = browser.new_context(viewport={'width': 1440, 'height': 700}, reduced_motion='reduce')
    page = ctx.new_page()
    page.goto(BASE + '/', wait_until='networkidle')
    hidden_under = []
    for i in range(60):
        page.keyboard.press('Tab')
        info = page.evaluate("""() => { const e = document.activeElement; const b = document.getElementById('sd-consent');
          if (!e || !b || b.hidden || b.contains(e)) return null; const r = e.getBoundingClientRect(); const br = b.getBoundingClientRect();
          return { text: (e.innerText || '').trim().slice(0, 30), covered: r.bottom > br.top + 1 && r.top < br.bottom }; }""")
        if info and info['covered']:
            hidden_under.append(info['text'])
    check('element z fokusem nie jest zasłonięty przez okno zgody', not hidden_under, hidden_under[:5])
    ctx.close()


# --------------------------------------------------------------------------
def test_forced_colors(browser):
    print('\nE. Tryb wysokiego kontrastu (forced-colors)')
    ctx = browser.new_context(viewport={'width': 1440, 'height': 900}, forced_colors='active', reduced_motion='reduce')
    page = ctx.new_page()
    page.goto(BASE + '/potrzebuje-pomocy/', wait_until='networkidle')
    missing = []
    for i in range(45):
        page.keyboard.press('Tab')
        st = page.evaluate("""() => { const e = document.activeElement; if (!e || e === document.body) return null; const cs = getComputedStyle(e);
          return { t: (e.innerText || e.getAttribute('aria-label') || e.id || e.tagName).trim().slice(0, 30), ok: cs.outlineStyle !== 'none' && parseFloat(cs.outlineWidth) >= 1 }; }""")
        if st and not st['ok']:
            missing.append(st['t'])
    check('fokus widoczny w trybie wysokiego kontrastu', not missing, missing[:6])
    ctx.close()


def main():
    which = sys.argv[1].split(',') if len(sys.argv) > 1 else ['A', 'B', 'C', 'E']
    with sync_playwright() as p:
        browser = p.chromium.launch()
        if 'A' in which:
            test_mobile_menu(browser, '/')
            test_mobile_menu(browser, '/ua/')
        if 'B' in which:
            test_form_errors(browser, '/potrzebuje-pomocy/', 'pl')
            test_form_errors(browser, '/ua/dopomoha/', 'uk')
            test_form_errors(browser, '/en/get-help/', 'en')
        if 'C' in which:
            test_self_assessment(browser)
        if 'D' in which:
            test_consent(browser)
        if 'E' in which:
            test_forced_colors(browser)
        browser.close()
    ok = sum(1 for r in results if r[1])
    print('\nWYNIK: %d zaliczonych, %d bledow' % (ok, len(results) - ok))
    with open(os.path.join(HERE, 'results', 'interakcje-%s.json' % ''.join(which)), 'w', encoding='utf-8') as fh:
        json.dump(results, fh, ensure_ascii=False, indent=1)


if __name__ == '__main__':
    main()
