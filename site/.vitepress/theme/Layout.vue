<script setup lang="ts">
import DefaultTheme from 'vitepress/theme'
import { useRoute, withBase } from 'vitepress'

const route = useRoute()

function openSearch(): void {
  window.dispatchEvent(new KeyboardEvent('keydown', { key: 'k', ctrlKey: true }))
}

function isActive(prefix: string): boolean {
  return route.path.startsWith(prefix)
}

function isGuidesActive(): boolean {
  return ['/guides/', '/tutorials/guide/', '/cookbook/'].some((prefix) => isActive(prefix))
}

function isTutorialsActive(): boolean {
  return isActive('/tutorials/') && !isActive('/tutorials/guide/')
}
</script>

<template>
  <div class="retro-site">
    <header class="retro-header">
      <div class="retro-masthead">
        <a class="retro-brand" :href="withBase('/')">
          TurboVision for PHP
        </a>
      </div>
      <div
        class="retro-gradient"
        aria-hidden="true"
        :style="{ backgroundImage: `url(${withBase('/brand-gradient.webp')})` }"
      />
      <div class="retro-tab-row">
        <nav class="retro-tabs" aria-label="Documentation sections">
          <a :href="withBase('/tutorials/')" :class="{ active: isTutorialsActive() }" :aria-current="isTutorialsActive() ? 'page' : undefined">Tutorials</a>
          <a :href="withBase('/guides/')" :class="{ active: isGuidesActive() }" :aria-current="isGuidesActive() ? 'page' : undefined">Guides</a>
          <a :href="withBase('/reference/')" :class="{ active: isActive('/reference/') }" :aria-current="isActive('/reference/') ? 'page' : undefined">Reference</a>
          <a :href="withBase('/explanation/')" :class="{ active: isActive('/explanation/') }" :aria-current="isActive('/explanation/') ? 'page' : undefined">Explanation</a>
        </nav>
        <button class="retro-search" type="button" aria-label="Search Ctrl K" @click="openSearch">
          Search <kbd>Ctrl K</kbd>
        </button>
      </div>
    </header>

    <DefaultTheme.Layout>
      <template #sidebar-nav-before>
        <div class="rail-heading">Developer manual</div>
      </template>
      <template #aside-outline-before>
        <div class="rail-heading">On this page</div>
      </template>
      <template #doc-before>
        <div class="story-kicker">TurboVision Online</div>
      </template>
    </DefaultTheme.Layout>
  </div>
</template>
