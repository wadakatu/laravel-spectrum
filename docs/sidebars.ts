import type {SidebarsConfig} from '@docusaurus/plugin-content-docs';

// This runs in Node.js - Don't use client-side code here (browser APIs, JSX...)

/**
 * Creating a sidebar enables you to:
 - create an ordered group of docs
 - render a sidebar for each doc of that group
 - provide next/previous navigation

 The sidebars can be generated from the filesystem, or explicitly defined here.

 Create as many sidebars as you want.
 */
const sidebars: SidebarsConfig = {
  tutorialSidebar: [
    'index',
    {
      type: 'category',
      label: 'Getting Started',
      items: [
        'quickstart',
        'installation',
        'basic-usage',
        'features',
      ],
    },
    {
      type: 'category',
      label: 'Configuration',
      items: [
        'config-reference',
        'customization',
        'middleware',
        'authentication',
      ],
    },
    {
      type: 'category',
      label: 'Features',
      items: [
        'validation-detection',
        'conditional-validation',
        'api-resources',
        'response-analysis',
        'error-handling',
        'pagination',
        'export',
        'mock-server',
      ],
    },
    {
      type: 'category',
      label: 'Advanced',
      items: [
        'advanced-features',
        'performance',
        'ci-cd-integration',
      ],
    },
    {
      type: 'category',
      label: 'Reference',
      items: [
        'api-reference',
        'cli-reference',
        'openapi-extensions',
        'comparison',
        'migration-guide',
      ],
    },
    {
      type: 'category',
      label: 'Help',
      items: [
        'troubleshooting',
        'faq',
        'known-issues',
        'security',
        'contributing',
      ],
    },
  ],
};

export default sidebars;
