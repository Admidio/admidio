from lxml import etree

file = 'languages/en.xml'

# Load XML with comments preserved
parser = etree.XMLParser(remove_blank_text=False)
tree = etree.parse(file, parser)
root = tree.getroot()

new_children = []
buffer = []

def flush_buffer():
    """Sort and add all <string> elements in the buffer."""
    if buffer:
        sorted_strings = sorted(buffer, key=lambda e: e.attrib.get('name', ''))
        new_children.extend(sorted_strings)
        buffer.clear()

for elem in root.iterchildren():
    if isinstance(elem, etree._Comment):
        flush_buffer()
        new_children.append(elem)
    elif elem.tag == 'string':
        buffer.append(elem)
    else:
        flush_buffer()
        new_children.append(elem)

# Flush anything left at the end
flush_buffer()

# Replace root content
root[:] = new_children

# Save result
tree.write(file, encoding='utf-8', xml_declaration=True, pretty_print=True)
print("en.xml successfully sorted.")
