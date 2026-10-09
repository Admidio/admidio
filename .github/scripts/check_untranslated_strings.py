"""
List the texts of the English language files that are not translated in the other languages.

Every languages folder (core and plugins) is checked. Each <language>.xml is compared with en.xml and
each countries-<language>.xml with countries-en.xml. Missing texts are only an information, because
Admidio shows the English text if a translation is missing.

The result is written as Markdown to the file of --summary (the job summary of the workflow). With
--notice the given languages also get one notice annotation per language file with the untranslated
text ids, which is printed to stdout.
"""
import argparse
import glob
import os
import xml.etree.ElementTree as ET

p = argparse.ArgumentParser()
p.add_argument('--exclude', default='', help='Comma-separated dirs to skip')
p.add_argument('--summary', required=True, help='File to which the Markdown result is appended')
p.add_argument('--notice', default='', help='Comma-separated languages that get a notice annotation')
args = p.parse_args()
excl = {d.strip() for d in args.exclude.split(',') if d.strip()}
notice_languages = {d.strip() for d in args.notice.split(',') if d.strip()}


def read_keys(file_path):
    return {e.attrib['name'] for e in ET.parse(file_path).getroot().findall('.//string')}


results = []
for dp, _, fs in os.walk('.'):
    if any(part in excl for part in dp.split(os.sep)) or os.path.basename(dp) != 'languages':
        continue
    for prefix in ('', 'countries-'):
        en_file = os.path.join(dp, prefix + 'en.xml')
        if not os.path.isfile(en_file):
            continue
        en_keys = read_keys(en_file)
        for file_path in sorted(glob.glob(os.path.join(dp, prefix + '*.xml'))):
            language = os.path.basename(file_path)[len(prefix):-4]
            if file_path == en_file or (prefix == '' and language.startswith('countries-')):
                continue
            try:
                missing = sorted(en_keys - read_keys(file_path))
            except ET.ParseError as e:
                print(f'::warning file={file_path[2:]}::Language file could not be read: {e}')
                continue
            results.append((file_path[2:], language, len(en_keys), missing))

with open(args.summary, 'a') as summary:
    print('## Untranslated texts\n', file=summary)
    print('This is only an information. If a text is missing in a language, Admidio shows the English text.\n', file=summary)
    print('| Language file | Untranslated | Translated |', file=summary)
    print('|---|---:|---:|', file=summary)
    incomplete = [r for r in sorted(results, key=lambda r: (r[0].count('/'), r[0])) if r[3]]
    for file_path, language, total, missing in incomplete:
        print(f'| `{file_path}` | {len(missing)} | {round(100 * (total - len(missing)) / total)} % |', file=summary)
    print(f'\nAll other {len(results) - len(incomplete)} language files are completely translated.', file=summary)

    for file_path, language, total, missing in incomplete:
        if missing:
            print(f'\n<details><summary><code>{file_path}</code>: {len(missing)} untranslated</summary>\n', file=summary)
            print(', '.join(missing), file=summary)
            print('\n</details>', file=summary)
            if language in notice_languages:
                if len(missing) == 1:
                    print(f'::notice file={file_path}::1 text is not translated: {missing[0]}')
                else:
                    print(f'::notice file={file_path}::{len(missing)} texts are not translated: {", ".join(missing)}')
