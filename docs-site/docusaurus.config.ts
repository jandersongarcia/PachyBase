import {themes as prismThemes} from 'prism-react-renderer';
import type {Config} from '@docusaurus/types';
import type * as Preset from '@docusaurus/preset-classic';

const config: Config = {
  title: 'PachyBase',
  tagline: 'Predictable backend documentation for humans and AI',
  favicon: 'img/favicon.ico',
  future: {
    v4: true,
  },
  url: 'https://jandersongarcia.github.io',
  baseUrl: '/pachybase/',
  organizationName: 'jandersongarcia',
  projectName: 'pachybase',
  trailingSlash: false,
  onBrokenLinks: 'throw',
  i18n: {
    defaultLocale: 'en',
    locales: ['en', 'pt-BR'],
    localeConfigs: {
      en: {
        htmlLang: 'en',
        label: 'English',
      },
      'pt-BR': {
        htmlLang: 'pt-BR',
        label: 'Portugues (Brasil)',
      },
    },
  },
  presets: [
    [
      'classic',
      {
        docs: {
          sidebarPath: './sidebars.ts',
          routeBasePath: '/',
          editUrl:
            'https://github.com/jandersongarcia/pachybase/tree/main/docs-site/',
        },
        blog: false,
        theme: {
          customCss: './src/css/custom.css',
        },
      } satisfies Preset.Options,
    ],
  ],
  themeConfig: {
    image: 'img/docusaurus-social-card.jpg',
    colorMode: {
      respectPrefersColorScheme: true,
    },
    navbar: {
      title: 'PachyBase',
      logo: {
        alt: 'PachyBase Logo',
        src: 'img/logo.svg',
      },
      items: [
        {
          type: 'docSidebar',
          sidebarId: 'tutorialSidebar',
          position: 'left',
          label: 'Documentation',
        },
        {
          to: '/install',
          label: 'Install',
          position: 'left',
        },
        {
          to: '/examples',
          label: 'Examples',
          position: 'left',
        },
        {
          type: 'localeDropdown',
          position: 'right',
        },
        {
          href: 'https://github.com/jandersongarcia/pachybase/archive/refs/heads/main.zip',
          label: 'Download ZIP',
          position: 'right',
        },
        {
          href: 'https://github.com/jandersongarcia/pachybase',
          label: 'GitHub',
          position: 'right',
        },
      ],
    },
    footer: {
      style: 'dark',
      links: [
        {
          title: 'Docs',
          items: [
            {
              label: 'Overview',
              to: '/',
            },
            {
              label: 'Install',
              to: '/install',
            },
            {
              label: 'Examples',
              to: '/examples',
            },
          ],
        },
        {
          title: 'API',
          items: [
            {
              label: 'API Contract',
              to: '/api-contract',
            },
            {
              label: 'Automatic CRUD',
              to: '/automatic-crud',
            },
            {
              label: 'OpenAPI',
              to: '/openapi',
            },
          ],
        },
        {
          title: 'Project',
          items: [
            {
              label: 'Contributing',
              to: '/contributing',
            },
            {
              label: 'Roadmap',
              to: '/roadmap',
            },
            {
              label: 'GitHub',
              href: 'https://github.com/jandersongarcia/pachybase',
            },
          ],
        },
      ],
      copyright: `Copyright (c) ${new Date().getFullYear()} PachyBase. Built with Docusaurus.`,
    },
    prism: {
      theme: prismThemes.github,
      darkTheme: prismThemes.dracula,
    },
  } satisfies Preset.ThemeConfig,
};

export default config;
