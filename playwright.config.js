import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/browser',
  timeout: 30000,
  workers: 1,
  reporter: 'list',
  outputDir: 'storage/app/runtime/browser-results',
  use: {
    channel: process.env.PLAYWRIGHT_CHANNEL || undefined,
    headless: true,
    launchOptions: { args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream'] },
  },
});
