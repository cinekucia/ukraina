# -*- coding: utf-8 -*-
"""
Paczki ZIP motywu i wtyczki do instalacji przez panel WordPressa
(Wygląd → Motywy → Dodaj → Wyślij; Wtyczki → Dodaj → Wyślij).

Każda paczka ma dokładnie jeden katalog najwyższego poziomu o nazwie zgodnej
z identyfikatorem motywu / wtyczki — inaczej WordPress zainstaluje ją pod
inną nazwą i aktualizacja nie nadpisze starej wersji.

Uruchomienie:  python tools/build_zips.py        → dist/*.zip
Kontrola:      python tools/build_zips.py --check
"""
import hashlib
import os
import re
import sys
import zipfile

REPO = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
WP = os.path.join(REPO, 'stop-dyskryminacji', 'app', 'public', 'wp-content')
DIST = os.path.join(REPO, 'dist')
PACKAGES = [
    ('stop-dyskryminacji', os.path.join(WP, 'themes', 'stop-dyskryminacji'), 'style.css', 'Theme Name'),
    ('stop-dyskryminacji-core', os.path.join(WP, 'plugins', 'stop-dyskryminacji-core'), 'stop-dyskryminacji-core.php', 'Plugin Name'),
]
SKIP_NAMES = {'.DS_Store', 'Thumbs.db', 'desktop.ini'}
SKIP_DIRS = {'.git', 'node_modules', '__pycache__', '.idea', '.vscode'}
# Stała data wpisów — ta sama zawartość daje tę samą sumę kontrolną paczki.
FIXED_DATE = (2026, 1, 1, 0, 0, 0)


def files_of(root):
    out = []
    for dirpath, dirnames, filenames in os.walk(root):
        dirnames[:] = sorted(d for d in dirnames if d not in SKIP_DIRS and not d.startswith('.'))
        for name in sorted(filenames):
            if name in SKIP_NAMES or name.startswith('.'):
                continue
            full = os.path.join(dirpath, name)
            out.append((full, os.path.relpath(full, root).replace(os.sep, '/')))
    return out


def build():
    os.makedirs(DIST, exist_ok=True)
    for slug, root, main_file, header in PACKAGES:
        target = os.path.join(DIST, slug + '.zip')
        items = files_of(root)
        with zipfile.ZipFile(target, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as z:
            for full, rel in items:
                info = zipfile.ZipInfo(slug + '/' + rel, FIXED_DATE)
                info.compress_type = zipfile.ZIP_DEFLATED
                info.external_attr = 0o644 << 16
                with open(full, 'rb') as fh:
                    z.writestr(info, fh.read())
        raw = sum(os.path.getsize(f) for f, _ in items)
        print('%-28s %3d plików  %7.1f KB (z %7.1f KB)  sha256 %s' % (
            os.path.basename(target), len(items), os.path.getsize(target) / 1024, raw / 1024,
            hashlib.sha256(open(target, 'rb').read()).hexdigest()[:16]))


def check():
    errors = 0
    for slug, root, main_file, header in PACKAGES:
        target = os.path.join(DIST, slug + '.zip')
        with zipfile.ZipFile(target) as z:
            names = z.namelist()
            tops = {n.split('/')[0] for n in names}
            ok_top = tops == {slug}
            ok_sep = all('\\' not in n and not n.startswith('/') and '..' not in n.split('/') for n in names)
            text = z.read(slug + '/' + main_file).decode('utf-8', 'replace')
            ok_header = bool(re.search(r'^\s*\*?\s*' + header + r':', text, re.M))
            # Zgodność z bieżącym katalogiem źródłowym (paczka nie jest nieaktualna).
            current = {slug + '/' + rel: hashlib.sha256(open(f, 'rb').read()).hexdigest() for f, rel in files_of(root)}
            packed = {n: hashlib.sha256(z.read(n)).hexdigest() for n in names}
            ok_fresh = current == packed
        for label, ok in (('jeden katalog „%s"' % slug, ok_top), ('ścieżki względne, separator /', ok_sep),
                          ('nagłówek „%s" w %s' % (header, main_file), ok_header), ('zgodna ze źródłem', ok_fresh)):
            errors += 0 if ok else 1
            print('  [%s] %-24s %s' % (' OK ' if ok else 'BLAD', slug + '.zip', label))
    print('WYNIK: %s' % ('bez błędów' if not errors else '%d błędów' % errors))
    return errors


if __name__ == '__main__':
    if '--check' in sys.argv:
        sys.exit(1 if check() else 0)
    build()
    sys.exit(1 if check() else 0)
