<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { AUTONYMS, LOCALES, type Locale } from '@/i18n/locales'
import { THEME_PREFERENCES, type ThemePreference } from '@/preferences/ThemeController'
import { usePreferencesStore } from '@/stores/preferences'

/** Carte « Affichage » du compte : thème et langue, choix explicites. */
const preferences = usePreferencesStore()
const { t } = useI18n()

function onTheme(value: string): void {
  preferences.setTheme(value as ThemePreference)
}

async function onLocale(value: string): Promise<void> {
  await preferences.setLocale(value as Locale)
}
</script>

<template>
  <section class="card stack" aria-labelledby="display-title">
    <h2 id="display-title">{{ t('prefs.title') }}</h2>

    <fieldset class="choices">
      <legend>{{ t('prefs.theme.legend') }}</legend>
      <label v-for="option in THEME_PREFERENCES" :key="option" class="choice">
        <input type="radio" name="theme" :value="option" :checked="preferences.theme === option" @change="onTheme(option)" />
        {{ t(`prefs.theme.${option}`) }}
      </label>
    </fieldset>

    <fieldset class="choices">
      <legend>{{ t('prefs.language.legend') }}</legend>
      <label v-for="code in LOCALES" :key="code" class="choice" :lang="code">
        <input type="radio" name="locale" :value="code" :checked="preferences.locale === code" @change="onLocale(code)" />
        {{ AUTONYMS[code] }}
      </label>
    </fieldset>

    <p class="muted hint">{{ t('prefs.hint') }}</p>
  </section>
</template>

<style scoped>
.choices {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem 1rem;
  margin: 0;
  padding: 0;
  border: 0;
}

legend {
  margin-bottom: 0.25rem;
  padding: 0;
  font-weight: 700;
}

.choice {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  min-height: 44px;
  cursor: pointer;
}

.choice input {
  width: 1.25rem;
  height: 1.25rem;
  accent-color: var(--color-accent);
}

.hint {
  margin: 0;
  font-size: 0.9rem;
}
</style>
