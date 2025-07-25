import React from 'react';
import clsx from 'clsx';
import Link from '@docusaurus/Link';
import useDocusaurusContext from '@docusaurus/useDocusaurusContext';
import Layout from '@theme/Layout';
import HomepageFeatures from '@site/src/components/HomepageFeatures';
import Heading from '@theme/Heading';

import styles from './index.module.css';

function HomepageHeader() {
  const {siteConfig} = useDocusaurusContext();
  return (
    <header className={clsx('hero hero--primary', styles.heroBanner)}>
      <div className="container">
        <div className={styles.heroGlow}></div>
        <Heading as="h1" className={clsx('hero__title', styles.glowText)}>
          {siteConfig.title}
        </Heading>
        <p className={clsx('hero__subtitle', styles.heroSubtitle)}>
          {siteConfig.tagline}
        </p>
        <div className={styles.buttons}>
          <Link
            className="button button--secondary button--lg"
            to="/quickstart">
            🚀 Quick Start - 5min ⏱️
          </Link>
          <Link
            className="button button--outline button--secondary button--lg"
            to="/installation"
            style={{marginLeft: '1rem'}}>
            📚 Full Documentation
          </Link>
        </div>
        <div className={styles.heroCode}>
          <pre className={styles.codeBlock}>
            <code className="language-bash">
{`# Install
composer require wadakatu/laravel-spectrum --dev

# Generate docs
php artisan spectrum:generate

# View live
php artisan spectrum:watch`}
            </code>
          </pre>
        </div>
        <div className={styles.features}>
          <span className={styles.feature}>✨ Zero Annotations</span>
          <span className={styles.feature}>🔍 Smart Detection</span>
          <span className={styles.feature}>⚡ Real-time Updates</span>
          <span className={styles.feature}>🎭 Mock Server</span>
        </div>
      </div>
    </header>
  );
}

export default function Home(): JSX.Element {
  const {siteConfig} = useDocusaurusContext();
  return (
    <Layout
      title={`${siteConfig.title} - Zero-annotation API documentation`}
      description="Automatically generate OpenAPI documentation for Laravel & Lumen applications without annotations. Smart detection of FormRequests, API Resources, and validation rules.">
      <HomepageHeader />
      <main>
        <HomepageFeatures />
      </main>
    </Layout>
  );
}