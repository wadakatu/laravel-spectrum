#!/usr/bin/env node

const fs = require('fs-extra');
const path = require('path');

const projectRoot = path.join(__dirname, '..', '..');
const docsiteRoot = path.join(__dirname, '..');

async function syncDocs() {
  console.log('📚 Syncing documentation files...');

  // Sync English docs
  const enSource = path.join(projectRoot, 'docs', 'en');
  const enDest = path.join(docsiteRoot, 'docs');
  
  if (await fs.pathExists(enSource)) {
    await fs.ensureDir(enDest);
    await fs.copy(enSource, enDest, { overwrite: true });
    console.log('✅ English docs synced');
  } else {
    console.warn('⚠️  English docs source not found:', enSource);
  }

  // Sync Japanese docs
  const jaSource = path.join(projectRoot, 'docs', 'ja');
  const jaDest = path.join(docsiteRoot, 'i18n', 'ja', 'docusaurus-plugin-content-docs', 'current');
  
  if (await fs.pathExists(jaSource)) {
    await fs.ensureDir(jaDest);
    await fs.copy(jaSource, jaDest, { overwrite: true });
    console.log('✅ Japanese docs synced');
  } else {
    console.warn('⚠️  Japanese docs source not found:', jaSource);
  }

  console.log('📚 Documentation sync complete!');
}

// Run the sync
syncDocs().catch(err => {
  console.error('❌ Error syncing docs:', err);
  process.exit(1);
});