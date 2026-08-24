<script setup lang="ts">
import { withBase } from 'vitepress'
</script>

<template>
<div class="portal-home">
  <aside class="portal-quick" aria-label="Start here">
    <div class="portal-bar">Start here</div>
    <a class="portal-hot" href="/tutorials/first-application">Build your first app</a>
    <a href="/tutorials/guide/">Build a complete app</a>
    <a href="/cookbook/structure-an-application">Structure a larger app</a>
    <a href="/reference/component-catalog">Choose a component</a>
    <a href="/reference/">Look up an API</a>

    <strong>Popular topics</strong>
    <a href="/cookbook/dialogs-and-forms">Dialogs &amp; forms</a>
    <a href="/cookbook/events-and-commands">Events &amp; commands</a>
    <a href="/cookbook/render-and-capture">Headless rendering</a>
    <a href="/reference/drivers-and-tools">Drivers &amp; tools</a>

    <strong>Project</strong>
    <a href="https://github.com/HelgeSverre/tvision-php">Source on GitHub</a>
    <a href="/contributing">Contributing</a>
  </aside>

  <main class="portal-main">
    <p class="portal-kicker">Framework spotlight</p>
    <h1>Build full-screen terminal applications in pure PHP</h1>
    <p class="portal-lead">TurboVision for PHP brings the classic windowed text-mode interface to modern PHP: a retained view tree, overlapping windows, menus, dialogs, editors, keyboard and mouse input, and deterministic headless tests.</p>
    <p class="portal-cta"><a href="/tutorials/first-application">Build an application from scratch</a><span aria-hidden="true">▶</span></p>

    <figure class="portal-demo">
      <img :src="withBase('/workbench.png')" alt="Turbo Workbench running in TurboVision for PHP, with overlapping windows, dashboard controls, and a status line">
      <figcaption>A real TurboVision frame, captured through the headless renderer.</figcaption>
    </figure>

    <div class="portal-bar portal-section-bar">Framework highlights</div>
    <div class="portal-briefs">
      <article>
        <h2><a href="/explanation/view-tree">A real application framework</a></h2>
        <p>Compose retained views, focusable controls, overlapping windows, nested menus, status lines, modal dialogs, editors, file pickers, help, and persistence.</p>
        <a class="more" href="/reference/views-and-controls">Explore the view system <span aria-hidden="true">▶</span></a>
      </article>
      <article>
        <h2><a href="/explanation/rendering-and-terminal">Terminal behavior included</a></h2>
        <p>Capability detection, Unicode drawing, keyboard and mouse protocols, resize handling, ANSI diff rendering, and safe terminal restoration stay behind the driver.</p>
        <a class="more" href="/reference/drivers-and-tools">Read the driver reference <span aria-hidden="true">▶</span></a>
      </article>
      <article>
        <h2><a href="/tutorials/headless-testing">Test without a terminal</a></h2>
        <p>Boot applications in memory, queue events, inspect the screen buffer, and create deterministic HTML or PNG captures without taking over a TTY.</p>
        <a class="more" href="/tutorials/headless-testing">Try headless testing <span aria-hidden="true">▶</span></a>
      </article>
      <article>
        <h2><a href="/explanation/turbo-vision-and-php">Classic ideas, modern PHP</a></h2>
        <p>Keep the durable ideas from Turbo Vision while using typed PHP, Composer, explicit dependencies, injectable drivers, and modern tests.</p>
        <a class="more" href="/explanation/turbo-vision-and-php">Read the design story <span aria-hidden="true">▶</span></a>
      </article>
    </div>
  </main>

  <aside class="portal-insight" aria-label="Framework information">
    <div class="portal-bar">Quick install</div>
    <p>Requires PHP 8.5 and <code>ext-mbstring</code>.</p>
    <pre><code>composer require helgesverre/turbovision</code></pre>

    <div class="portal-bar">Build next</div>
    <h2><a href="/tutorials/guide/">Grow an application step by step</a></h2>
    <p>Add menus, windows, custom drawing, scrolling, dialogs, and form data.</p>
    <h2><a href="/cookbook/structure-an-application">Keep a larger application tidy</a></h2>
    <p>Separate the application shell, windows, dialogs, state, and tests.</p>
    <h2><a href="/cookbook/scrolling-lists-and-editors">Show and edit real data</a></h2>
    <p>Pick a list, scroller, outline, or editor and wire it into a window.</p>
    <h2><a href="/reference/component-catalog">Find the right building block</a></h2>
    <p>Compare the framework's visible components by job and key methods.</p>

    <div class="portal-bar">Compatibility</div>
    <p>Designed for POSIX terminals, with optional <code>pcntl</code> and <code>posix</code> enhancements and a TTY-free headless path.</p>
  </aside>
</div>
</template>
