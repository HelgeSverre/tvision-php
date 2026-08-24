# Explanation

These articles describe the framework's core design: how retained views form an
interactive desktop, how events become commands, how a frame reaches a terminal, and
how the PHP API relates to Turbo Vision.

- [The retained view tree](./view-tree) — ownership, local coordinates, overlap,
  focus, and modal surfaces.
- [The event and command model](./event-model) — physical input, event propagation,
  commands, broadcasts, and command availability.
- [Drawing and terminal ownership](./rendering-and-terminal) — buffers, clipping,
  incremental ANSI output, terminal lifecycle, optional protocols, and headless
  rendering.
- [From Turbo Vision to PHP](./turbo-vision-and-php) — the classic concepts retained
  by the framework, PHP-facing API choices, and the scope of compatibility.

For a guided learning path, begin with the [tutorials](/tutorials/). For task-focused
instructions, use the [cookbook](/cookbook/). For public types, runtime support,
and exact contracts, use the [reference](/reference/).
