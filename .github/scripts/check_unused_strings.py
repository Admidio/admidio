import os, re, xml.etree.ElementTree as ET
import argparse

p = argparse.ArgumentParser()
p.add_argument('--exclude', default='', help='Comma-separated dirs to skip')
args = p.parse_args()
excl = {d.strip() for d in args.exclude.split(',') if d.strip()}

root = ET.parse('languages/en.xml').getroot()
keys = [e.attrib['name'] for e in root.findall('.//string')
        if re.fullmatch(r'[A-Z0-9_]+', e.attrib['name'])]

for dp, _, fs in os.walk('.'):
    if any(part in excl for part in dp.split(os.sep)):
        continue
    for f in fs:
        if f.endswith(('.php', '.js', '.html', '.tpl')):
            file_path = os.path.join(dp, f)
            file_content = open(file_path, 'r', errors='ignore').read()
            used_keys = set()
            for k in keys:
                if k in file_content:
                    used_keys.add(k)

unused = [k for k in keys if k not in used_keys]
if unused:
    for k in unused:
        print(f"UNUSED: {k}")
    exit(1)  # triggers warning via continue-on-error
