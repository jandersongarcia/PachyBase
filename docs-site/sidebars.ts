import type {SidebarsConfig} from '@docusaurus/plugin-content-docs';

const sidebars: SidebarsConfig = {
  tutorialSidebar: [
    {
      type: 'category',
      label: 'Overview',
      items: ['intro', 'architecture', 'install', 'docker-install', 'local-install', 'configuration', 'supported-databases'],
    },
    {
      type: 'category',
      label: 'API',
      items: [
        'api-contract',
        'auth-security',
        'automatic-crud',
        'filters-pagination',
        'openapi',
        'ai-endpoints',
        'baas-platform',
      ],
    },
    {
      type: 'category',
      label: 'Operations',
      items: ['cli', 'production-deploy', 'agent-templates', 'testing'],
    },
    {
      type: 'category',
      label: 'Internals',
      items: ['database-layer', 'entity-metadata', 'input-validation', 'contract-enforcement', 'libraries'],
    },
    {
      type: 'category',
      label: 'Project',
      items: ['examples', 'release-process', 'contributing', 'roadmap'],
    },
  ],
};

export default sidebars;
