import type {SidebarsConfig} from '@docusaurus/plugin-content-docs';

const sidebars: SidebarsConfig = {
  tutorialSidebar: [
    'intro',
    'install',
    'api-contract',
    'openapi',
    'libraries',
    {
      type: 'category',
      label: 'Getting Started',
      items: ['docker-install'],
    },
  ],
};

export default sidebars;
