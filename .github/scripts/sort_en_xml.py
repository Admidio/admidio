from lxml import etree

file = 'languages/en.xml'

# Load XML with comments preserved
parser = etree.XMLParser(remove_blank_text=False)
tree = etree.parse(file, parser)
root = tree.getroot()

new_children = []
buffer = []
seen_names = set()

def sort_key(element):
    # Split the 'name' attribute by '_' and return a tuple of parts.
    # This ensures that "_" is treated as the separator so that each word
    # is compared alphabetically.
    name = element.attrib.get('name', '')
    return tuple(name.split('_'))

def flush_buffer():
    """Sort <string> elements from the buffer alphabetically, filter duplicates, and add to new_children."""
    if buffer:
        sorted_strings = sorted(buffer, key=sort_key)
        for elem in sorted_strings:
            name = elem.attrib.get('name')
            if name is not None:
                if name in seen_names:
                    # If the name is already seen, skip this element.
                    print(f"Duplicate string found: {name}, skipping.")
                    continue
                seen_names.add(name)
            new_children.append(elem)
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

# Flush any remaining elements in the buffer.
flush_buffer()

# Replace the content of the root with the new, sorted children.
root[:] = new_children

# Save the result to file.
tree.write(file, encoding='utf-8', xml_declaration=True, pretty_print=True)
print("en.xml successfully sorted and duplicates removed.")