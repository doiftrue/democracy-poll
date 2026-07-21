import { defineConfig } from 'vitepress'

const gettingStartedSidebar = {
  text: 'Getting started',
  items: [
    { text: 'Overview', link: '/guide/' },
    { text: 'Install the plugin', link: '/guide/installation' },
    { text: 'Create your first poll', link: '/guide/getting-started' }
  ]
}

const userGuideSidebar = {
  text: 'User guide',
  items: [
    { text: 'Manage polls', link: '/user-guide/managing-polls' },
    { text: 'Display polls', link: '/user-guide/displaying-polls' },
    { text: 'Settings and voting rules', link: '/user-guide/settings' },
    { text: 'Design and appearance', link: '/user-guide/design' },
    { text: 'Vote logs', link: '/user-guide/logs' },
    { text: 'Widgets', link: '/user-guide/widgets' },
    { text: 'Troubleshooting', link: '/user-guide/troubleshooting' }
  ]
}

export default defineConfig({
  title: 'Democracy Poll',
  description: 'User and integration documentation for the Democracy Poll WordPress plugin.',
  base: '/democracy-poll/',
  cleanUrls: true,
  head: [
    ['meta', { name: 'theme-color', content: '#3858e9' }]
  ],
  themeConfig: {
    logo: '/logo.svg',
    siteTitle: 'Democracy Poll',
    search: {
      provider: 'local'
    },
    nav: [
      { text: 'User guide', link: '/guide/getting-started' },
      { text: 'Developer guide', link: '/developer/' },
      { text: 'Reference', link: '/reference/settings' }
    ],
    sidebar: {
      '/guide/': [
        gettingStartedSidebar,
        userGuideSidebar
      ],
      '/user-guide/': [
        gettingStartedSidebar,
        userGuideSidebar
      ],
      '/developer/': [
        {
          text: 'Developer guide',
          items: [
            { text: 'Integration overview', link: '/developer/' },
            { text: 'PHP functions', link: '/developer/php-functions' },
            { text: 'Hooks and filters', link: '/developer/hooks' },
            { text: 'Markup and CSS', link: '/developer/markup-css' },
            { text: 'Caching', link: '/developer/caching' }
          ]
        }
      ],
      '/reference/': [
        {
          text: 'Reference',
          items: [
            { text: 'Shortcodes', link: '/reference/shortcodes' },
            { text: 'Settings', link: '/reference/settings' },
            { text: 'FAQ', link: '/reference/faq' }
          ]
        }
      ]
    },
    socialLinks: [
      { icon: 'github', link: 'https://github.com/doiftrue/democracy-poll' }
    ],
    footer: {
      message: 'Released under the GPL-2.0-or-later license.',
      copyright: 'Democracy Poll documentation'
    },
    editLink: {
      pattern: 'https://github.com/doiftrue/democracy-poll/edit/dev/docs/:path',
      text: 'Edit this page on GitHub'
    }
  }
})
