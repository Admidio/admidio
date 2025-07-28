import os, re, xml.etree.ElementTree as ET
import argparse

p = argparse.ArgumentParser()
p.add_argument('--exclude', default='', help='Comma-separated dirs to skip')
args = p.parse_args()
excl = {d.strip() for d in args.exclude.split(',') if d.strip()}

root = ET.parse('languages/en.xml').getroot()
keys = [e.attrib['name'] for e in root.findall('.//string')
        if re.fullmatch(r'[A-Z0-9_]+', e.attrib['name'])]

unused = []
for k in keys:
    used = False
    for dp, _, fs in os.walk('.'):
        if any(part in excl for part in dp.split(os.sep)):
            continue
        for f in fs:
            if f.endswith(('.php','.js','.html','.tpl')):
                if k in open(os.path.join(dp, f), 'r', errors='ignore').read():
                    used = True; break
        if used: break
    if not used:
        unused.append(k)

if unused:
    for k in unused:
        print(f"UNUSED: {k}")
    exit(1)  # triggers warning via continue-on-error
