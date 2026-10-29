"""Prepare the v2 Markdown/PDF package for WordPress DRAFTS. Never publishes."""
from __future__ import annotations
import argparse
import base64
import hashlib
import html
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
from html.parser import HTMLParser

CODE = re.compile(r'^(P[1-4]|A(?:0[1-9]|1[0-2]))_', re.I)
EDITORIAL = re.compile(r'UZUPEŁNIENIA|WERYFIKACJI|KONTROLA EKSPERTA|DATA AUTORYZACJI|DECYZJA|ZESTAW ZNAKÓW|do potwierdzenia|publikator', re.I)
EXPECTED = {f'A{i:02}' for i in range(1,13)} | {f'P{i}' for i in range(1,5)}
GUARD = "<?php\nif (!defined('ABSPATH')) { http_response_code(404); exit; }\n"

def publication_name(src):
    base=Path(src).stem
    base=re.sub(r'[_ -]+v\d+(?:[-.]\d+)*(?=$|[_ -])','',base,flags=re.I)
    return re.sub(r'(?:[_ -]+(?:final\w*|ostateczn\w*|robocz\w*|draft|autoryzowan\w*|po[_ -]autoryzacji))+$','',base,flags=re.I)

def table(text):
    rows={}
    for line in text.splitlines():
        if not line.startswith('|'): continue
        cells=[c.strip() for c in re.split(r'(?<!\\)\|',line)[1:-1]]
        if len(cells)>=2 and cells[0] not in ['Pole','---']:
            rows[cells[0]]=cells[1].strip('`')
    return rows

def section(text, label, level=2):
    prefix='#'*level
    pattern=rf'^{prefix} {re.escape(label)}[^\n]*\n(.*?)(?=^#{{1,{level}}} |\Z)'
    match=re.search(pattern,text,re.M|re.S)
    if not match: raise ValueError('Brak sekcji: '+label)
    return match[1].strip()

def version_check(path, text):
    for mark in re.findall(r'\[([^\[\]\n]{0,200})\]',text):
        if mark.upper().startswith('DOSTOSUJ'): continue
        if EDITORIAL.search(mark): raise ValueError(f'{path.name}: znacznik redakcyjny [{mark}]')
    filename=re.search(r'_v(\d+)[-.](\d+)',path.stem,re.I)
    source=re.search(r'^version:\s*["\']?(\d+[.,]\d+)',text,re.M)
    if filename and source and '.'.join(filename.groups())!=source[1].replace(',','.'):
        raise ValueError(f'{path.name}: numer wersji w treści nie zgadza się z nazwą pliku')

def parse_source(path):
    text=path.read_text(encoding='utf-8-sig').replace('\r\n','\n')
    version_check(path,text)
    code=CODE.match(path.name)[1].upper()
    md=re.sub(r'\A---\n.*?\n---\n','',text,count=1,flags=re.S).strip()
    titles=re.findall(r'^# (.+)$',md,re.M)
    if len(titles)!=1: raise ValueError(f'{code}: oczekiwano jednego H1, jest {len(titles)}')
    title=titles[0]
    after=md.split('\n',1)[1].lstrip()
    header=table(after.split('\n\n',1)[0])
    author=header.get('Autor','')
    legal=header.get('Stan prawny na dzień',header.get('Stan prawny',''))
    if not author or not legal: raise ValueError(code+': brak autora lub stanu prawnego')
    if code.startswith('A'):
        lead=section(md,'Lead')
        body=section(md,'Wersja pełna')
        # Removing the wrapper promotes its H3 subsections to H2, with no skipped levels.
        body=re.sub(r'^(#{3,6}) ',lambda m:m[1][:-1]+' ',body,flags=re.M)
        seo=table(section(md,'Metadane i dystrybucja'))
        meta_title=next((v for k,v in seo.items() if k.startswith('Meta title')),'')
        description=next((v for k,v in seo.items() if k.startswith('Meta description')),'')
        url=seo.get('Proponowany URL','')
        if not url.startswith('/artykuly/') or not re.fullmatch(r'/artykuly/[a-z0-9-]+',url): raise ValueError(code+': niepoprawny proponowany URL')
        if not meta_title or len(meta_title)>60 or not description or len(description)>160:
            raise ValueError(f'{code}: błędna długość SEO ({len(meta_title)}/{len(description)})')
        categories=[x.strip() for x in seo.get('Kategoria','').split('·') if x.strip()]
        keywords=[x.strip() for x in seo.get('Słowa kluczowe','').split(',') if x.strip()]
    else:
        note=re.search(r'^## Informacje dla redakcji i wydawcy.*$',after,re.M)
        if not note: raise ValueError(code+': brak granicy notatek redakcyjnych')
        before=after[:note.start()].strip()
        # Only the first, administrative table is omitted; every content table remains.
        before=re.sub(r'\A(?:\|[^\n]*\n)+\s*','',before,count=1)
        body=before.rstrip().removesuffix('---').rstrip()
        notes=after[note.start():]
        lead=section(notes,'Opis do katalogu',3)
        meta_title=''; description=''; url=''
        categories=[]
        meta=table(section(notes,'Metadane',3))
        keywords=[x.strip() for x in meta.get('Słowa kluczowe','').split(',') if x.strip()]
    if any(label in body for label in ['## Wersja skrócona dla mediów','## Metadane i dystrybucja','## Informacje dla redakcji i wydawcy']):
        raise ValueError(code+': notatki redakcyjne w treści strony')
    images=re.findall(r'!\[([^\]]+)\]\(([^)]+)\)',body)
    if len(images)!=1: raise ValueError(code+': oczekiwano jednej infografiki w pełnej treści')
    planned='2026-11' if code in ['A01','A11','A09','P2'] else '2026-12' if code in ['A02','A10','A05','P1','P3'] else '2027-01'
    return {'code':code,'type':'post' if code.startswith('A') else 'publikacja','title':title,'lead':lead,
            'markdown':body,'author':author,'legal_as_of':legal,'legal_as_of_iso':'2026-11-05',
            'seo':{'title':meta_title,'description':description,'url':url,'categories':categories,'keywords':keywords},
            'planned_month':planned,'planned_date':'2026-11-05' if code=='A01' else '',
            'publication_deadline':'2027-01-31','status':'draft','author_approval':'pending','license_approval':'pending',
            'source_file':path.name,'source_sha256':hashlib.sha256(path.read_bytes()).hexdigest(),
            'alt':images[0][0],'image_source':images[0][1]}

def locate(md_path, suffix, search_root):
    filename=md_path.with_suffix(suffix).name
    found=list(search_root.rglob(filename))
    if not found: raise ValueError('Brak pliku: '+filename)
    hashes={hashlib.sha256(p.read_bytes()).hexdigest() for p in found}
    if len(hashes)>1: raise ValueError('Różne pliki o tej samej nazwie: '+filename)
    return sorted(found)[0]

class Text(HTMLParser):
    def __init__(self): super().__init__(); self.parts=[]; self.alts=[]; self.headings=[]
    def handle_data(self,t): self.parts.append(t)
    def handle_starttag(self,tag,attrs):
        attrs=dict(attrs)
        if tag=='img': self.alts.append(attrs.get('alt',''))
        if re.fullmatch(r'h[1-6]',tag): self.headings.append(int(tag[1]))

def plain(value):
    p=Text(); p.feed(value); return ''.join(p.parts)

def create_package(files, out, publish_names=False, plugin=None, complete=True):
    # Validate the whole batch before writing any public-ready named files.
    entries=[parse_source(p) for p in files]
    codes=[p['code'] for p in entries]
    if len(set(codes))!=len(codes): raise ValueError('Powtórzone kody dokumentów')
    if complete and set(codes)!=EXPECTED: raise ValueError('Niekompletna paczka: brakuje '+', '.join(sorted(EXPECTED-set(codes))))
    common=Path(os.path.commonpath([str(p.parent.resolve()) for p in files]))
    search_root=common.parent.parent if common.name=='MD_do_CMS' else common
    assets={}
    for p,item in zip(files,entries):
        pdf=locate(p,'.pdf',search_root)
        img=p.parent.parent/item['image_source']
        if not img.is_file(): raise ValueError('Brak infografiki: '+str(img))
        name=publication_name(p) if publish_names else p.stem+'_podglad'
        for key,src,filename,mime in [
            (item['code']+'_PDF',pdf,name+'.pdf','application/pdf'),
            (item['code']+'_INFOGRAPHIC',img,item['code']+'_infografika.png','image/png')]:
            raw=src.read_bytes()
            assets[key]={'source':src,'filename':filename,'mime':mime,'sha256':hashlib.sha256(raw).hexdigest(),'bytes':len(raw)}
        from PIL import Image
        with Image.open(img) as image:
            if image.size!=(1080,1350): raise ValueError(item['code']+': infografika nie ma wymiarów 1080×1350')
        item['pdf_asset']=item['code']+'_PDF'; item['infographic_asset']=item['code']+'_INFOGRAPHIC'
        item['article_pdf_download']=False  # Optional article PDFs remain editorial attachments, not page content.
    node=os.environ.get('SDC_NODE_BIN') or str(Path.home()/'.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node.exe')
    proc=subprocess.run([node,str(Path(__file__).with_name('editorial_markdown.mjs'))],input=json.dumps(entries,ensure_ascii=False),text=True,encoding='utf-8',capture_output=True,check=True)
    rendered={r['code']:r for r in json.loads(proc.stdout)}
    for entry in entries:
        r=rendered[entry['code']]; entry['content']=r['content']; entry['excerpt']=plain(r['lead_html']); entry['lead_html']=r['lead_html']
        parsed=Text(); parsed.feed(entry['content'])
        if parsed.alts != [entry['alt']]: raise ValueError(entry['code']+': zmieniono tekst alternatywny')
        previous=1
        for heading in parsed.headings:
            if heading==1 or heading>previous+1: raise ValueError(entry['code']+': nieprawidłowa hierarchia nagłówków')
            previous=heading
    out.mkdir(parents=True,exist_ok=True)
    for folder in ['pdf','infografiki','okladki','html']: (out/folder).mkdir(exist_ok=True)
    for key,asset in assets.items():
        target=out/('pdf' if asset['mime']=='application/pdf' else 'infografiki')/asset['filename']
        shutil.copyfile(asset['source'],target)
        if hashlib.sha256(target.read_bytes()).hexdigest()!=asset['sha256']: raise RuntimeError('Kopia pliku jest niezgodna: '+str(target))
    # Supplied final PDFs are copied byte-for-byte. Only first-page PNG covers are rendered.
    import pypdfium2 as pdfium
    for entry in entries:
        if entry['type']!='publikacja': continue
        key=entry['code']+'_COVER'; pdf=pdfium.PdfDocument(assets[entry['pdf_asset']]['source'])
        page=pdf[0]; bitmap=page.render(scale=1.6)
        target=out/'okladki'/(entry['code']+'_okladka.png'); bitmap.to_pil().save(target)
        bitmap.close(); page.close(); pdf.close()
        raw=target.read_bytes(); assets[key]={'source':target,'filename':target.name,'mime':'image/png','sha256':hashlib.sha256(raw).hexdigest(),'bytes':len(raw)}
        entry['cover_asset']=key
    for entry in entries:
        (out/'html'/(entry['code']+'.html')).write_text('<h1>'+html.escape(entry['title'])+'</h1>\n<p>'+entry['lead_html']+'</p>\n'+entry['content'],encoding='utf-8')
        entry.pop('markdown'); entry.pop('image_source')
    public_assets={}
    for key,asset in assets.items():
        info={k:v for k,v in asset.items() if k!='source'}
        if asset['mime']=='image/png':
            with Image.open(asset['source']) as im: info.update(width=im.width,height=im.height)
        public_assets[key]=info
    digest=hashlib.sha256(json.dumps(entries,ensure_ascii=False,sort_keys=True).encode()).hexdigest()
    package={'version':'2.0','package_id':'martyniak-2.0-'+digest[:16],'sha256':digest,'post_status':'draft','author_approval':'pending','license_approval':'pending','entries':entries,'assets':public_assets}
    (out/'manifest-szkice.json').write_text(json.dumps(package,ensure_ascii=False,indent=2),encoding='utf-8')
    if plugin:
        data=plugin/'data'; private=data/'editorial-v2-assets'; private.mkdir(parents=True,exist_ok=True)
        for key,asset in assets.items():
            encoded=base64.b64encode(asset['source'].read_bytes()).decode('ascii')
            (private/(key+'.php')).write_text(GUARD+"return base64_decode('"+encoded+"');\n",encoding='ascii')
        encoded=base64.b64encode(json.dumps(package,ensure_ascii=False).encode()).decode('ascii')
        (data/'editorial-v2.php').write_text(GUARD+"return json_decode(base64_decode('"+encoded+"'), true);\n",encoding='ascii')
    print(f'Przygotowano {len(entries)} SZKICÓW (12 artykułów, 4 publikacje), {len(assets)} plików. Publikacja i harmonogram: wyłączone.')
    print('PDF-y zgodne bajt w bajt z dostarczonymi; alty 1:1; SEO i nagłówki sprawdzone.')
    return package

def main(argv=None):
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('files',nargs='*',type=Path)
    parser.add_argument('--all',type=Path,help='katalog paczki v2.0 albo MD_do_CMS')
    parser.add_argument('--out',required=True,type=Path)
    parser.add_argument('--publikacja',action='store_true',help='tylko nazwy plików bez wersji; NIE publikuje w CMS')
    parser.add_argument('--szkice-cms',action='store_true',help='przygotuj pełną paczkę 16 szkiców CMS')
    parser.add_argument('--wtyczka',type=Path,help='katalog stop-dyskryminacji-core; pakiet jest chroniony przed odczytem HTTP')
    args=parser.parse_args(argv)
    files=list(args.files)
    if args.all: files+=sorted(p for p in args.all.rglob('*.md') if CODE.match(p.name))
    if not files: parser.error('Nie znaleziono plików Markdown; niczego nie zapisano.')
    try: create_package(files,args.out,args.publikacja,args.wtyczka,complete=True)
    except (ValueError,RuntimeError,subprocess.CalledProcessError) as e:
        print('WSTRZYMANO: '+str(e),file=sys.stderr)
        if isinstance(e,subprocess.CalledProcessError): print(e.stderr,file=sys.stderr)
        return 1
    return 0

if __name__=='__main__': raise SystemExit(main())
