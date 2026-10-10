"""
List text ids that are used in the code but don't exist in any English language file.

Only places where a text is certainly translated are checked, to avoid false positives with internal
identifiers that look like text ids (e.g. category names like ROL_EVENT). Like Language::isTranslationStringId()
only ids with three letters before the first underscore are checked, so exceptions like 'LOGIN' or
'NO_VISIBLE_ROLES', which are used as internal signals, are ignored:
- $gL10n->get('ID') in PHP and $l10n->get('ID') in templates
- new Exception('ID') and new \\Exception('ID')
- 'helpTextId' => 'ID' and 'helpTextId' => array('ID', ...)
Lines that are comments (starting with *, // or #) are skipped.

Texts are searched in languages/en.xml and in the languages/en.xml of every plugin. Every missing
text id is printed as one line "<text id> <file>:<line>".
"""
import argparse
import os
import re
import xml.etree.ElementTree as ET

# same rule as Language::isTranslationStringId(), other strings are not translated
TEXT_ID = r"([A-Z]{3}_(?:[A-Z0-9]_?)*[A-Z0-9])"
PATTERNS = [
    re.compile(r"->get\(\s*['\"]" + TEXT_ID + r"['\"]"),
    re.compile(r"new\s+\\?(?:Admidio\\Infrastructure\\)?Exception\(\s*['\"]" + TEXT_ID + r"['\"]"),
    re.compile(r"['\"]helpTextId['\"]\s*=>\s*(?:array\(\s*)?['\"]" + TEXT_ID + r"['\"]"),
]
COMMENT = re.compile(r"^\s*(\*|//|#|/\*)")

p = argparse.ArgumentParser()
p.add_argument('--exclude', default='', help='Comma-separated dirs to skip')
args = p.parse_args()
excl = {d.strip() for d in args.exclude.split(',') if d.strip()}

keys = set()
for dp, _, fs in os.walk('.'):
    if any(part in excl for part in dp.split(os.sep)):
        continue
    if os.path.basename(dp) == 'languages' and 'en.xml' in fs:
        root = ET.parse(os.path.join(dp, 'en.xml')).getroot()
        keys.update(e.attrib['name'] for e in root.findall('.//string'))

missing = []
for dp, _, fs in os.walk('.'):
    if any(part in excl for part in dp.split(os.sep)):
        continue
    for f in fs:
        if not f.endswith(('.php', '.tpl')):
            continue
        file_path = os.path.join(dp, f)
        with open(file_path, 'r', errors='ignore') as fh:
            for number, line in enumerate(fh, 1):
                if COMMENT.match(line):
                    continue
                for pattern in PATTERNS:
                    for text_id in pattern.findall(line):
                        if text_id not in keys:
                            missing.append((text_id, file_path[2:], number))

for text_id, file_path, number in sorted(missing):
    print(f'{text_id} {file_path}:{number}')
