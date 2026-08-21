# Kitchen Sink coverage

Run the interactive demo with `composer demo:kitchensink`. It uses the library's
public APIs directly; the Dashboard, Canvas, and Feature Navigator are focused
examples of extending `View`, `Scroller`, and `ListBox`.

| Framework area | Where to exercise it |
|---|---|
| Program, Application, Screen, Desktop, Background | The complete app shell and initial desktop |
| ANSI driver, input decoding, key modifiers, resize | Run in a terminal; move the pointer, use Alt/F-keys, and resize |
| View tree, Group routing, focus, state, clipping | Overlapping initial windows and every modal lab |
| DrawBuffer, palettes, Unicode one-cell rendering | Interactive landing Dashboard |
| Window, Frame, move, resize, zoom, close, Alt-1…9 | Every modeless lab and the Window menu |
| Desktop next/previous, tile, cascade | Window menu and F5/F6 shortcuts |
| MenuBar, MenuBox, MenuPopup, nested menus | Labs menu and Tools → Context menu |
| Standard edit command routing and clipboard bindings | Edit menu with the File editor selected |
| StatusLine and contextual help IDs | Bottom status line and F1 from different surfaces |
| CommandSet and disabled-command redraw | Tools → Toggle advanced labs |
| Built-in and custom palette modes | Theme menu, F8, and Palette editor |
| StaticText and text alignment | Navigator headers and rebuilt resource window |
| ListViewer, ListBox, multi-column lists | Feature Navigator and Controls lab |
| ScrollBar and Scroller | Canvas, Outline, Terminal, editors, file dialogs |
| Dialog, Button, Label, ParamText | Controls lab |
| InputLine, clipboard selection, overwrite, History | Controls lab |
| CheckBoxes, RadioButtons, MultiCheckBoxes | Controls lab |
| Filter, Range, Picture, StringLookup validators | Controls lab |
| MessageBox convenience API | Labs → Message boxes and Help → About |
| Editor, Memo, FileEditor, EditWindow, Indicator | Memo and File editor labs; Edit menu find/replace and File menu save/save-as |
| FileDialog, file collections and information pane | Labs → Files + data → Open file dialog |
| ChDirDialog, directory collection and tree | Labs → Files + data → Change directory |
| Outline linked nodes and expand/collapse navigation | Data views → Outline tree |
| TextDevice, Terminal, OutputTextStream, bounded reflow | Initial telemetry and Terminal lab |
| HelpFile, HelpTopic, paragraphs, cross-references, viewer | F1 contextual help |
| Color groups, selectors, preview and ColorDialog | Theme → Palette editor |
| StreamableRegistry and bounded safe StreamCodec | Resource round-trip lab |
| ResourceFile, locking, atomic replacement, StringList | Resource round-trip lab |
| ViewResource and explicit allow-list factories | Rebuilt Resource Tree window |
| HeadlessDriver and deterministic screen buffer | `tests/Feature/KitchenSinkTest.php` |
| Responsive fallback and state restoration | Resize below 80×25, then enlarge the terminal again |

The standalone help compiler (`bin/tvhc`), HTML renderer, screenshot renderer, fuzz
harness, and benchmark harness are non-interactive developer tools rather than views;
they are documented in the root README and exercised by their own tests/commands.
