Documentation captures
======================

Run every capture from site/:

    npm run captures

Run selected captures by id:

    npm run captures -- tutorials/first-application

The launcher selects PHP 8.5+ from `php` or `php85`. Set `TVISION_PHP` when the
desired executable has another name or path.

Each PHP file under captures/scenarios/ returns one CaptureScenario. The generator
discovers files automatically, so scenarios never share a registry file. Use an
80x25 terminal unless the documented UI needs more room. The factory must build an
Application with the supplied Screen. Use the optional prepare callback for stable,
scripted state changes before the final draw.

Generated images live at public/captures/<id>.png and are committed. Reference them
from Markdown with the globally registered component:

    <DocCapture
      src="/captures/<id>.png"
      alt="Specific description of the visible application state"
      caption="What the reader should verify in this result."
    />

The component's intrinsic dimensions default to the generator's 80x25 output
(1544x800). For another terminal size, pass the generated PNG's width and height
as numeric props so lazy loading does not shift the page.

Place a capture directly after the step whose result it shows. The component handles
base paths, lazy loading, responsive sizing, borders, and captions. Do not use a
capture when text output, a table, or a code block communicates the result more clearly.
