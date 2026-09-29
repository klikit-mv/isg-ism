import { defineConfig } from 'vitest/config';
import { fileURLToPath } from 'node:url';

export default defineConfig({
  resolve: { alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) } },
  test: {
    environment: 'node',
    include: ['tests/**/*.test.ts'],
    globalSetup: ['./tests/global-setup.ts'],
    setupFiles: ['./tests/setup.ts'],
    // One database, so run files one after another.
    fileParallelism: false,
    pool: 'forks',
    testTimeout: 20000,
    env: {
      APP_KEY: 'base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=',
      DB_HOST: process.env.TEST_DB_HOST ?? '127.0.0.1',
      DB_PORT: process.env.TEST_DB_PORT ?? '3306',
      DB_DATABASE: process.env.TEST_DB_DATABASE ?? 'scout_next_test',
      DB_USERNAME: process.env.TEST_DB_USERNAME ?? 'scout',
      DB_PASSWORD: process.env.TEST_DB_PASSWORD ?? 'secret',
      BCRYPT_ROUNDS: '4',
      NODE_ENV: 'test',
      SCOUT_TIMEZONE: 'Indian/Maldives',
      STORAGE_DIR: './storage-data/test',
    },
  },
});
