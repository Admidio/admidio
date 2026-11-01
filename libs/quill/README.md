# Quill 2.0.3

These files come from the official `quill@2.0.3` npm package:
https://registry.npmjs.org/quill/-/quill-2.0.3.tgz

- `quill.js` and `quill.snow.css` are the prebuilt full editor and Snow theme; their source maps are included.
- `LICENSE` is Quill's BSD license.
- `quill.js.LICENSE.txt` contains the bundle's license notice.

Admidio serves these files locally for its rich-text editor fields.
The default toolbar exposes Quill's built-in table module and uses Quill's
image width and height formats for resizing. No extra Quill plugins are needed.
Videos from YouTube and Vimeo use Quill's built-in video format. Admidio preserves
the iframe when saving and restricts its URL to those two video providers.
