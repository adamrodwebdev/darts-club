import { globalIgnores } from 'eslint/config'
import { defineConfigWithVueTs, vueTsConfigs } from '@vue/eslint-config-typescript'
import pluginVue from 'eslint-plugin-vue'
import pluginPlaywright from 'eslint-plugin-playwright'
import pluginVitest from '@vitest/eslint-plugin'
import pluginOxlint from 'eslint-plugin-oxlint'
import pluginVueI18n from '@intlify/eslint-plugin-vue-i18n'
import skipFormatting from 'eslint-config-prettier/flat'

// To allow more languages other than `ts` in `.vue` files, uncomment the following lines:
// import { configureVueProject } from '@vue/eslint-config-typescript'
// configureVueProject({ scriptLangs: ['ts', 'tsx'] })
// More info at https://github.com/vuejs/eslint-config-typescript/#advanced-setup

export default defineConfigWithVueTs(
  {
    name: 'app/files-to-lint',
    files: ['**/*.{vue,ts,mts,tsx}'],
  },

  globalIgnores(['**/dist/**', '**/dist-ssr/**', '**/coverage/**']),

  ...pluginVue.configs['flat/essential'],
  vueTsConfigs.recommended,

  {
    ...pluginPlaywright.configs['flat/recommended'],
    files: ['e2e/**/*.{test,spec}.{js,ts,jsx,tsx}'],
    rules: {
      ...pluginPlaywright.configs['flat/recommended'].rules,
      // L'audit d'accessibilité (e2e/a11y.ts) est une vérification : ses `expect` sont à l'intérieur
      'playwright/expect-expect': ['warn', { assertFunctionNames: ['auditBothThemes'] }],
    },
  },

  {
    ...pluginVitest.configs.recommended,
    files: ['src/**/__tests__/*'],
  },

  // Aucun texte en dur dans les gabarits : tout passe par vue-i18n (fr.json / en.json)
  {
    name: 'app/i18n',
    files: ['src/**/*.vue'],
    // Textes juridiques rédigés par langue (un composant par langue)
    ignores: ['src/views/legal/fr/**', 'src/views/legal/en/**'],
    plugins: { '@intlify/vue-i18n': pluginVueI18n },
    settings: {
      'vue-i18n': { localeDir: './src/i18n/locales/*/*.json', messageSyntaxVersion: '^11.0.0' },
    },
    rules: {
      '@intlify/vue-i18n/no-raw-text': [
        'error',
        {
          // Noms propres, unités et symboles qui ne se traduisent pas
          ignoreText: ['Skull Darts 71', 'Facebook', 'BPM', 'TOTP', 'ICS', 'CSV'],
          ignorePattern: '^([\\s\\d.,:;!?·•…×*+\\-–—/()\\[\\]#%€↓↑↗→←✓✕]|BPM)+$',
        },
      ],
    },
  },

  ...pluginOxlint.buildFromOxlintConfigFile('.oxlintrc.json'),

  skipFormatting,
)
