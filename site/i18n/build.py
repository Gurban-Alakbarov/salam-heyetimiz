"""Landing translations: index.final.html (az, the source) -> index.en.html / index.ru.html.

  python -I build.py extract          # writes segments.json: every translatable az string, in page order
  python -I build.py build en ru      # needs en.json / ru.json ({az: translation}); writes ../index.<lang>.html

Translatable = text between tags (outside <style>/<svg>), a few attributes (alt, aria-label, placeholder,
title, meta description/og text) and the az string literals in the inline script. Internal page links get the
/<lang> prefix; <html> carries data-prefix so the in-page router keeps that prefix.
"""
import json
import os
import re
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
SRC = os.path.join(HERE, '..', 'index.final.html')
PAGES = ['services', 'how-it-works', 'solutions', 'faq', 'contact', 'privacy', 'terms', 'refund', 'account']
LOCALE = {'en': 'en_US', 'ru': 'ru_RU'}

ATTR_RE = re.compile(r'\b(alt|aria-label|placeholder|title)="([^"]*)"')
META_RE = re.compile(r'(<meta (?:content="([^"]*)" (?:name="description"|property="og:(?:title|description|image:alt)")'
                     r'|property="og:(?:title|description|image:alt)" content="([^"]*)")[^>]*>)')
JS_STR_RE = re.compile(r"'((?:[^'\\\n]|\\.)*)'")
TOKEN_RE = re.compile(r'(<style.*?</style>|<script.*?</script>|<svg.*?</svg>|<!--.*?-->|<[^>]+>)', re.S)


def needs(text):
    t = text.strip()
    return bool(t) and bool(re.search(r'[A-Za-zƏəÖöÜüĞğİıŞşÇç]', t)) and not t.startswith(('http', '#', '/'))


def js_strings(script):
    out = []
    for m in JS_STR_RE.finditer(script):
        v = m.group(1)
        # Human text only: has a space, an az letter or starts upper-case (skips selectors/ids/keys).
        if needs(v) and (re.search(r'[\sƏəÖöÜüĞğİıŞşÇç]', v) or v[:1].isupper()) and not re.search(r'[.#\[\]=(]', v[:1]):
            if v not in ('Salam Sesame',) and not re.match(r'^[a-z-]+$', v):
                out.append(v)
    return out


def scan(html):
    """Yield (kind, value) for every translatable string, in document order."""
    for tok in TOKEN_RE.split(html):
        if not tok:
            continue
        if tok.startswith('<script'):
            for v in js_strings(tok):
                yield 'js', v
        elif tok.startswith(('<style', '<svg', '<!--')):
            continue
        elif tok.startswith('<'):
            if tok.startswith('<meta'):
                m = META_RE.search(tok)
                if m:
                    yield 'attr', m.group(2) or m.group(3)
                continue
            for m in ATTR_RE.finditer(tok):
                if needs(m.group(2)):
                    yield 'attr', m.group(2)
        elif needs(tok):
            yield 'text', tok.strip()


def extract():
    html = open(SRC, encoding='utf-8').read()
    seen, segs = set(), []
    for kind, v in scan(html):
        if v not in seen:
            seen.add(v)
            segs.append({'kind': kind, 'az': v})
    json.dump(segs, open(os.path.join(HERE, 'segments.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
    print(len(segs), 'segments,', sum(len(x['az']) for x in segs), 'chars')


def build(lang):
    html = open(SRC, encoding='utf-8').read()
    tr = json.load(open(os.path.join(HERE, f'{lang}.json'), encoding='utf-8'))
    missing = [v for _, v in scan(html) if v not in tr]
    if missing:
        sys.exit(f'{lang}: {len(missing)} untranslated, e.g. {missing[:3]}')

    def t(v):
        return tr[v]

    def attr_fix(tag):
        tag = ATTR_RE.sub(lambda m: f'{m.group(1)}="{t(m.group(2)) if needs(m.group(2)) else m.group(2)}"', tag)
        if tag.startswith('<meta'):
            m = META_RE.search(tag)
            if m:
                v = m.group(2) or m.group(3)
                tag = tag.replace(f'content="{v}"', f'content="{t(v)}"')
        return tag

    out = []
    for tok in TOKEN_RE.split(html):
        if not tok:
            continue
        if tok.startswith('<script'):
            keep = set(js_strings(tok))
            tok = JS_STR_RE.sub(lambda m: "'" + t(m.group(1)).replace("'", "\\'") + "'" if m.group(1) in keep else m.group(0), tok)
        elif tok.startswith(('<style', '<svg', '<!--')):
            pass
        elif tok.startswith('<'):
            tok = attr_fix(tok)
        elif needs(tok):
            lead = tok[:len(tok) - len(tok.lstrip())]
            trail = tok[len(tok.rstrip()):]
            tok = lead + t(tok.strip()) + trail
        out.append(tok)
    s = ''.join(out)

    # Internal links and the page's own addresses get the language prefix; the switcher keeps its own hrefs.
    pages = '|'.join(re.escape(p) for p in PAGES)
    s = re.sub(r'href="/(' + pages + r')"', rf'href="/{lang}/\1"', s)
    s = re.sub(r'(<a (?![^>]*data-lang)[^>]*)href="/"', rf'\1href="/{lang}/"', s)
    s = s.replace('<html lang="az" data-prefix="">', f'<html lang="{lang}" data-prefix="/{lang}">')
    s = s.replace('<link rel="canonical" href="https://sezam.salam.az/"/>',
                  f'<link rel="canonical" href="https://sezam.salam.az/{lang}/"/>')
    s = s.replace('<meta property="og:locale" content="az_AZ"/>', f'<meta property="og:locale" content="{LOCALE[lang]}"/>')
    s = s.replace('<meta property="og:url" content="https://sezam.salam.az/"/>',
                  f'<meta property="og:url" content="https://sezam.salam.az/{lang}/"/>')
    s = s.replace('<a data-lang="az" href="/" class="active">AZ</a>', '<a data-lang="az" href="/">AZ</a>')
    s = s.replace(f'<a data-lang="{lang}" href="/{lang}/">', f'<a data-lang="{lang}" href="/{lang}/" class="active">')
    open(os.path.join(HERE, '..', f'index.{lang}.html'), 'w', encoding='utf-8', newline='').write(s)
    print(lang, 'ok', len(s))


if __name__ == '__main__':
    if sys.argv[1] == 'extract':
        extract()
    else:
        for lang in sys.argv[2:]:
            build(lang)
