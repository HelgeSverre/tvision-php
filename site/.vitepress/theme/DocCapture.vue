<script setup lang="ts">
import { computed } from 'vue'
import { withBase } from 'vitepress'

const props = withDefaults(defineProps<{
  src: string
  alt: string
  caption: string
  width?: number
  height?: number
}>(), {
  width: 1544,
  height: 800,
})

const resolvedSrc = computed(() => withBase(props.src))
</script>

<template>
  <figure class="docs-capture">
    <img
      :src="resolvedSrc"
      :alt="alt"
      :width="width"
      :height="height"
      loading="lazy"
      decoding="async"
    >
    <figcaption>{{ caption }}</figcaption>
  </figure>
</template>
