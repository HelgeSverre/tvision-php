# TVPHPHELP v1

`HelpFile` persists UTF-8 help as a text file beginning with the exact line
`TVPHPHELP 1`, followed by JSON: `{"version":1,"topics":{...}}`. Each topic
contains `paragraphs` (`text`, `wrap`) and `crossRefs` (`ref`, `offset`,
`length`, optional `label`). Offsets and lengths count Unicode graphemes, not
bytes. This intentionally replaces the platform-dependent Turbo Vision H32
object stream; H32 files are rejected with a clear error.

`bin/tvhc source.txt output.tvhelp [contexts.php]` accepts `.topic Name=42`
headers (or automatic IDs starting at 2), comma-separated aliases, wrapped
paragraphs, indented preformatted paragraphs, and `{label:Target}` links.
