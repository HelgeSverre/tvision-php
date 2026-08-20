# Turbo Studio

Turbo Studio is a self-hosting visual interface builder for TurboVision PHP. It is a
real editor rather than a static showcase: projects can be manipulated with the mouse,
edited numerically, saved as JSON, previewed without design chrome, and exported as a
standalone runnable PHP application.

Run it with:

    composer demo:studio
    php examples/php/studio.php path/to/project.json path/to/generated.php

## Controls

- Tab and Shift-Tab move focus between Components, Design, and Inspector.
- In Components, Up/Down chooses a widget and Enter adds it. Double-clicking a tool
  also adds it to the canvas.
- Component sigils use fixed-width ASCII, so the layout does not depend on emoji
  presentation or a particular Nerd Font installation.
- The catalog includes panels, labels, buttons, text inputs, list boxes, checkboxes,
  separators, radio options, progress bars, and multi-line text areas.
- Click a component or layer to select it. Drag its body to move it and drag the
  themed corner handle to resize it. Arrow keys move it and `+`/`-` resize it.
- **G** toggles the design grid and **S** toggles two-cell snapping. **H** and **V**
  center the selected component horizontally or vertically.
- Right-click a component to duplicate, delete, align, center, or change its layer
  order.
- In Inspector, Up/Down chooses a property, Left/Right nudges numeric values, and
  Enter or double-click starts inline editing.
- Ctrl-S/Ctrl-O save and load JSON projects. Ctrl-Z/Ctrl-Y undo and redo.
- F2 switches between complete foreground-only themes. Each changes the chrome,
  canvas hierarchy, component colors, grid, shadows, handles, and overlays. F5 opens
  the clean run preview.
- F9 shows the generated PHP source; press **E** there to export it.
- **Q**, Ctrl-Q, or Alt-X quits.

Generated files locate `vendor/autoload.php` by walking upward from their directory,
so export them somewhere inside the Composer project or beside its `vendor` folder.

The default Graphite theme inherits the terminal or browser canvas. It never paints
explicit backgrounds behind labels, borders, selection handles, or symbols.
