import process from 'node:process'
import { defineConfig, devices } from '@playwright/test'

/**
 * Read environment variables from file.
 * https://github.com/motdotla/dotenv
 */
// require('dotenv').config();

/**
 * See https://playwright.dev/docs/test-configuration.
 */
/** Balayage des largeurs de 320 à 2560 px : une seule fois, en Chromium bureau */
const SWEEP = /responsive\.spec\.ts$/
/** Clés d'accès réelles (authentificateur virtuel du protocole de Chromium) : projets Chromium seulement */
const WEBAUTHN = /@webauthn/

export default defineConfig({
  testDir: './e2e',
  /* Maximum time one test can run for. */
  timeout: 30 * 1000,
  expect: {
    /**
     * Maximum time expect() should wait for the condition to be met.
     * For example in `await expect(locator).toHaveText();`
     */
    timeout: 5000,
  },
  /* Fail the build on CI if you accidentally left test.only in the source code. */
  forbidOnly: !!process.env.CI,
  /* Retry on CI only */
  retries: process.env.CI ? 2 : 0,
  /* Opt out of parallel tests on CI. */
  workers: process.env.CI ? 1 : undefined,
  /* Reporter to use. See https://playwright.dev/docs/test-reporters */
  reporter: 'html',
  /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
  use: {
    /* Maximum time each action such as `click()` can take. Defaults to 0 (no limit). */
    actionTimeout: 0,
    /* Base URL to use in actions like `await page.goto('/')`. */
    baseURL: process.env.CI ? 'http://localhost:4173' : 'http://localhost:5173',

    /* Navigateur francophone par défaut : la langue détectée est celle des parcours */
    locale: 'fr-FR',

    /* Le service worker (production) empêche Playwright d'intercepter les appels d'API simulés */
    serviceWorkers: 'block',

    /* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
    trace: 'on-first-retry',

    /* Only on CI systems run the tests headless */
    headless: !!process.env.CI,
  },

  /* Configure projects for major browsers */
  projects: [
    // Balayage des largeurs (e2e/responsive.spec.ts) : Chromium bureau seulement,
    // l'émulation mobile pouvant rétrécir un contenu trop large
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
      },
    },
    {
      name: 'firefox',
      use: {
        ...devices['Desktop Firefox'],
      },
      testIgnore: SWEEP,
      grepInvert: WEBAUTHN,
    },
    {
      name: 'webkit',
      use: {
        ...devices['Desktop Safari'],
      },
      testIgnore: SWEEP,
      grepInvert: WEBAUTHN,
    },

    /* Responsive design obligatoire : parcours testés sur mobile */
    {
      name: 'Mobile Chrome',
      use: {
        ...devices['Pixel 7'],
      },
      testIgnore: SWEEP,
    },
    {
      name: 'Mobile Safari',
      use: {
        ...devices['iPhone 14'],
      },
      testIgnore: SWEEP,
      grepInvert: WEBAUTHN,
    },

    /* Test against branded browsers. */
    // {
    //   name: 'Microsoft Edge',
    //   use: {
    //     channel: 'msedge',
    //   },
    // },
    // {
    //   name: 'Google Chrome',
    //   use: {
    //     channel: 'chrome',
    //   },
    // },
  ],

  /* Folder for test artifacts such as screenshots, videos, traces, etc. */
  // outputDir: 'test-results/',

  /* Run your local dev server before starting the tests */
  webServer: {
    /**
     * Use the dev server by default for faster feedback loop.
     * Use the preview server on CI for more realistic testing.
     * Playwright will re-use the local server if there is already a dev-server running.
     */
    command: process.env.CI ? 'npm run preview' : 'npm run dev',
    port: process.env.CI ? 4173 : 5173,
    reuseExistingServer: !process.env.CI,
  },
})
