import {themes as prismThemes} from 'prism-react-renderer';
import type {Config} from '@docusaurus/types';
import type * as Preset from '@docusaurus/preset-classic';

// This runs in Node.js - Don't use client-side code here (browser APIs, JSX...)

const config: Config = {
  title: 'Laravel Spectrum',
  tagline: 'Zero-annotation API documentation generator for Laravel',
  favicon: 'img/favicon.svg',

  // Future flags, see https://docusaurus.io/docs/api/docusaurus-config#future
  future: {
    v4: true, // Improve compatibility with the upcoming Docusaurus v4
  },

  markdown: {
    parseFrontMatter: async (params) => {
      // Reuse the default parser
      const result = await params.defaultParseFrontMatter(params);
      
      // Only process files without any frontmatter
      const hasFrontmatter = Object.keys(result.frontMatter).length > 0;
      
      if (!hasFrontmatter) {
        // Generate ID from file path
        const pathSegments = params.filePath.split('/');
        const fileName = pathSegments[pathSegments.length - 1];
        const id = fileName.replace(/\.mdx?$/, '');
        result.frontMatter.id = id;
        
        // Try to extract title from first heading
        if (result.content) {
          const match = result.content.match(/^#\s+(.+)$/m);
          if (match) {
            result.frontMatter.title = match[1];
          }
        }
        
        // Generate sidebar_label from title or id
        result.frontMatter.sidebar_label = result.frontMatter.title || result.frontMatter.id;
      }
      
      return result;
    },
  },

  // Set the production url of your site here
  url: 'https://wadakatu.github.io',
  // Set the /<baseUrl>/ pathname under which your site is served
  // For GitHub pages deployment, it is often '/<projectName>/'
  baseUrl: '/',

  // GitHub pages deployment config.
  // If you aren't using GitHub pages, you don't need these.
  organizationName: 'wadakatu', // Usually your GitHub org/user name.
  projectName: 'laravel-spectrum', // Usually your repo name.
  deploymentBranch: 'gh-pages',
  trailingSlash: false,

  onBrokenLinks: 'warn',
  onBrokenMarkdownLinks: 'warn',

  // Even if you don't use internationalization, you can use this field to set
  // useful metadata like html lang. For example, if your site is Chinese, you
  // may want to replace "en" with "zh-Hans".
  i18n: {
    defaultLocale: 'en',
    locales: ['en', 'ja'],
    path: 'i18n',
    localeConfigs: {
      en: {
        label: 'English',
        direction: 'ltr',
        htmlLang: 'en-US',
      },
      ja: {
        label: '日本語',
        direction: 'ltr',
        htmlLang: 'ja-JP',
      },
    },
  },

  presets: [
    [
      'classic',
      {
        docs: {
          sidebarPath: './sidebars.ts',
          routeBasePath: 'docs',
          // Please change this to your repo.
          // Remove this to remove the "edit this page" links.
          editUrl: ({locale, docPath}) => {
            if (locale === 'ja') {
              return `https://github.com/wadakatu/laravel-spectrum/tree/main/docs/i18n/ja/docusaurus-plugin-content-docs/current/${docPath}`;
            }
            return `https://github.com/wadakatu/laravel-spectrum/tree/main/docs/docs/${docPath}`;
          },
          remarkPlugins: [],
          rehypePlugins: [],
          showLastUpdateTime: true,
          showLastUpdateAuthor: true,
        },
        blog: false, // Disable blog
        theme: {
          customCss: './src/css/custom.css',
        },
      } satisfies Preset.Options,
    ],
  ],

  themeConfig: {
    // Replace with your project's social card
    image: 'img/laravel-spectrum-social-card.png',
    colorMode: {
      defaultMode: 'dark',
      disableSwitch: false,
      respectPrefersColorScheme: true,
    },
    navbar: {
      title: 'Laravel Spectrum',
      logo: {
        alt: 'Laravel Spectrum Logo',
        src: 'img/logo.svg',
        srcDark: 'img/logo-dark.svg',
      },
      items: [
        {
          type: 'doc',
          docId: 'index',
          position: 'left',
          label: 'Docs',
        },
        {
          type: 'doc',
          docId: 'quickstart',
          position: 'left',
          label: 'Quick Start',
        },
        {
          type: 'doc',
          docId: 'api-reference',
          position: 'left',
          label: 'API',
        },
        {
          type: 'localeDropdown',
          position: 'right',
        },
        {
          href: 'https://github.com/wadakatu/laravel-spectrum',
          position: 'right',
          className: 'header-github-link',
          'aria-label': 'GitHub repository',
        },
      ],
    },
    footer: {
      style: 'dark',
      links: [
        {
          title: 'Documentation',
          items: [
            {
              label: 'Quick Start',
              to: '/docs/quickstart',
            },
            {
              label: 'Installation',
              to: '/docs/installation',
            },
            {
              label: 'Features',
              to: '/docs/features',
            },
            {
              label: 'API Reference',
              to: '/docs/api-reference',
            },
          ],
        },
        {
          title: 'Community',
          items: [
            {
              label: 'GitHub',
              href: 'https://github.com/wadakatu/laravel-spectrum',
            },
            {
              label: 'Issues',
              href: 'https://github.com/wadakatu/laravel-spectrum/issues',
            },
            {
              label: 'Discussions',
              href: 'https://github.com/wadakatu/laravel-spectrum/discussions',
            },
          ],
        },
        {
          title: 'More',
          items: [
            {
              label: 'Changelog',
              href: 'https://github.com/wadakatu/laravel-spectrum/releases',
            },
            {
              label: 'Contributing',
              to: '/docs/contributing',
            },
            {
              label: 'License',
              href: 'https://github.com/wadakatu/laravel-spectrum/blob/main/LICENSE',
            },
          ],
        },
      ],
      copyright: `Copyright © ${new Date().getFullYear()} Laravel Spectrum. Made with ❤️ by <a href="https://github.com/wadakatu">Wadakatu</a>`,
    },
    prism: {
      theme: prismThemes.github,
      darkTheme: prismThemes.dracula,
      additionalLanguages: ['php', 'bash', 'json', 'yaml'],
    },
    algolia: {
      // The application ID provided by Algolia
      appId: 'YOUR_APP_ID',
      // Public API key: it is safe to commit it
      apiKey: 'YOUR_SEARCH_API_KEY',
      indexName: 'laravel_spectrum',
      // Optional: see doc section below
      contextualSearch: true,
      // Optional: Algolia search parameters
      searchParameters: {},
      // Optional: path for search page that enabled by default (`false` to disable it)
      searchPagePath: 'search',
      // Optional: whether the insights feature is enabled or not on Docsearch (`false` by default)
      insights: false,
    },
  } satisfies Preset.ThemeConfig,
};

export default config;