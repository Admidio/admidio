import os
import re
import xml.etree.ElementTree as ET
import argparse

p = argparse.ArgumentParser()
p.add_argument('--exclude', default='', help='Comma-separated dirs to skip')
args = p.parse_args()
excl = {d.strip() for d in args.exclude.split(',') if d.strip()}

root = ET.parse('languages/en.xml').getroot()
keys = [e.attrib['name'] for e in root.findall('.//string')
        if re.fullmatch(r'[A-Z0-9_]+', e.attrib['name'])]
pattern = re.compile(r'\b(' + '|'.join(map(re.escape, keys)) + r')\b')
used_keys = set()

for dp, _, fs in os.walk('.'):
    if any(part in excl for part in dp.split(os.sep)):
        continue
    for f in fs:
        if f.endswith(('.php', '.js', '.html', '.tpl')):
            file_path = os.path.join(dp, f)
            with open(file_path, 'r', errors='ignore') as f:
                file_content = f.read()
            found_keys = pattern.findall(file_content)
            used_keys.update(found_keys)

unused = [k for k in keys if k not in used_keys]
if unused:
    for k in unused:
        print(f"{k}")
