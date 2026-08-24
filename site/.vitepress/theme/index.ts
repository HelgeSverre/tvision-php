import DefaultTheme from 'vitepress/theme'
import type { Theme } from 'vitepress'
import DocCapture from './DocCapture.vue'
import Layout from './Layout.vue'
import './style.css'

export default {
  extends: DefaultTheme,
  Layout,
  enhanceApp({ app }) {
    app.component('DocCapture', DocCapture)
  },
} satisfies Theme
