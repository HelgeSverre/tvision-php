import { defineConfig } from 'vitepress'

const guidesSidebar = [
  {
    text: 'Guide',
    items: [
      { text: 'Build a complete application', link: '/tutorials/guide/' },
      { text: '1. Application shell', link: '/tutorials/guide/application-shell' },
      { text: '2. Windows and scrolling', link: '/tutorials/guide/windows-and-scrolling' },
      { text: '3. Dialogs and data', link: '/tutorials/guide/dialogs-and-data' },
    ],
  },
  {
    text: 'Cookbook',
    items: [
      { text: 'Browse all recipes', link: '/cookbook/' },
      { text: 'Structure an application', link: '/cookbook/structure-an-application' },
      { text: 'Handle events and commands', link: '/cookbook/events-and-commands' },
      { text: 'Build menus and status lines', link: '/cookbook/menus-and-status-lines' },
      { text: 'Build dialogs and forms', link: '/cookbook/dialogs-and-forms' },
      { text: 'Use scrolling, lists, and editors', link: '/cookbook/scrolling-lists-and-editors' },
      { text: 'Render and capture applications', link: '/cookbook/render-and-capture' },
      { text: 'Add help and persistence', link: '/cookbook/help-and-persistence' },
    ],
  },
]

export default defineConfig({
  title: 'TurboVision for PHP',
  description: 'Build rich, mouse-friendly terminal applications in modern PHP.',
  lang: 'en-US',
  appearance: false,
  cleanUrls: true,
  lastUpdated: true,
  markdown: {
    image: {
      lazyLoading: true,
    },
    theme: {
      name: 'turbo-blue',
      type: 'dark',
      colors: {
        'editor.background': '#101b4d',
        'editor.foreground': '#f4f4f4',
      },
      settings: [
        { settings: { foreground: '#f4f4f4', background: '#101b4d' } },
        { scope: ['comment', 'punctuation.definition.comment'], settings: { foreground: '#b8b8b8', fontStyle: 'italic' } },
        { scope: ['keyword', 'storage', 'storage.type', 'storage.modifier'], settings: { foreground: '#ffdd55' } },
        { scope: ['string', 'string.quoted', 'constant.other.symbol'], settings: { foreground: '#55ffff' } },
        { scope: ['constant.numeric', 'constant.language'], settings: { foreground: '#ff9d55' } },
        { scope: ['entity.name.function', 'support.function'], settings: { foreground: '#55ff55' } },
        { scope: ['entity.name.type', 'entity.name.class', 'support.class'], settings: { foreground: '#ffffff', fontStyle: 'bold' } },
        { scope: ['variable', 'variable.other', 'meta.function-call'], settings: { foreground: '#e8e8ff' } },
        { scope: ['punctuation', 'meta.brace', 'meta.delimiter'], settings: { foreground: '#d8d8d8' } },
      ],
    },
  },
  head: [
    ['meta', { name: 'theme-color', content: '#101b4d' }],
    ['meta', { property: 'og:type', content: 'website' }],
    ['meta', { property: 'og:title', content: 'TurboVision for PHP' }],
    ['meta', { property: 'og:description', content: 'A pure-PHP framework for rich terminal applications.' }],
  ],
  themeConfig: {
    siteTitle: 'TurboVision for PHP',
    nav: [
      { text: 'Tutorials', link: '/tutorials/', activeMatch: '^/tutorials/(?!guide/)' },
      { text: 'Guides', link: '/guides/', activeMatch: '^/(guides/|cookbook/|tutorials/guide/)' },
      { text: 'Reference', link: '/reference/' },
      { text: 'Explanation', link: '/explanation/' },
    ],
    sidebar: {
      '/tutorials/': [
        {
          text: 'Tutorials',
          items: [
            { text: 'Start here', link: '/tutorials/' },
            { text: 'Build your first application', link: '/tutorials/first-application' },
            { text: 'Add commands and a dialog', link: '/tutorials/interactive-application' },
            { text: 'Test without a terminal', link: '/tutorials/headless-testing' },
          ],
        },
      ],
      '/guides/': guidesSidebar,
      '/tutorials/guide/': guidesSidebar,
      '/cookbook/': guidesSidebar,
      '/reference/': [
        {
          text: 'Reference',
          items: [
            { text: 'Overview', link: '/reference/' },
            { text: 'Requirements and support', link: '/reference/requirements' },
            { text: 'Application lifecycle', link: '/reference/application' },
            { text: 'Component catalog', link: '/reference/component-catalog' },
            { text: 'Views and controls', link: '/reference/views-and-controls' },
            { text: 'Geometry, drawing, and palettes', link: '/reference/geometry-drawing-palettes' },
            { text: 'Events, keys, and commands', link: '/reference/events-keys-commands' },
            { text: 'Data, help, and persistence', link: '/reference/data-help-and-persistence' },
            { text: 'Drivers, rendering, and tools', link: '/reference/drivers-and-tools' },
          ],
        },
      ],
      '/explanation/': [
        {
          text: 'Explanation',
          items: [
            { text: 'Overview', link: '/explanation/' },
            { text: 'The retained view tree', link: '/explanation/view-tree' },
            { text: 'The event and command model', link: '/explanation/event-model' },
            { text: 'Drawing and terminal ownership', link: '/explanation/rendering-and-terminal' },
            { text: 'From Turbo Vision to PHP', link: '/explanation/turbo-vision-and-php' },
          ],
        },
      ],
    },
    search: { provider: 'local' },
    outline: { level: [2, 3] },
    editLink: {
      pattern: 'https://github.com/HelgeSverre/tvision-php/edit/main/site/:path',
      text: 'Edit this page on GitHub',
    },
    socialLinks: [
      { icon: 'github', link: 'https://github.com/HelgeSverre/tvision-php' },
    ],
    footer: {
      message: 'Released under the MIT License.',
      copyright: 'TurboVision for PHP',
    },
  },
})
