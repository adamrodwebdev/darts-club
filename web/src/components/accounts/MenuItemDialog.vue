<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/BaseButton.vue'
import BaseDialog from '@/components/BaseDialog.vue'
import BaseField from '@/components/BaseField.vue'
import { FORMATS_MAX, useMenuItemForm } from '@/composables/useMenuItemForm'
import type { BarItem, Station, StockUnit } from '@/models/EventAccounts'
import type { BarItemDraft } from '@/services/EventAccountsService'
import ChoiceGroup from './ChoiceGroup.vue'

const props = defineProps<{
  /** Article à modifier, ou null pour un nouveau */
  item: BarItem | null
  pending: boolean
  error: string | null
  fieldErrors: Readonly<Record<string, string>>
}>()
const emit = defineEmits<{ save: [draft: BarItemDraft]; remove: []; close: [] }>()
const { t } = useI18n()

const form = useMenuItemForm(props.item)
const { label, station, unit, garnishes, alcoholic, containerName, containerSize, planned, bought, returned, purchasePrice, formats, byTheLitre, errors, preview } = form

/** Contrôles locaux affichés après la première tentative d'envoi seulement */
const tried = ref(false)
const shown = (key: string): string | undefined => (tried.value ? errors.value[key] : undefined) ?? props.fieldErrors[key]

const stations = computed<{ value: Station; label: string }[]>(() => [
  { value: 'bar', label: t('events.accounts.menu.stations.bar') },
  { value: 'cuisine', label: t('events.accounts.menu.stations.cuisine') },
])
const units = computed<{ value: StockUnit; label: string }[]>(() => [
  { value: 'litre', label: t('events.accounts.menu.unitOptions.litre') },
  { value: 'unite', label: t('events.accounts.menu.unitOptions.unite') },
  { value: 'portion', label: t('events.accounts.menu.unitOptions.portion') },
])

function save(): void {
  tried.value = true
  const draft = form.draft()
  if (draft) emit('save', draft)
}
</script>

<template>
  <BaseDialog wide :title="item ? t('events.accounts.menu.editTitle', { name: item.label }) : t('events.accounts.menu.newTitle')" @close="emit('close')">
    <form class="stack" novalidate @submit.prevent="save">
      <BaseField v-model="label" :label="t('events.accounts.menu.fields.label')" required :maxlength="80" :error="fieldErrors.label" />
      <div class="pair">
        <ChoiceGroup v-model="station" name="station" :legend="t('events.accounts.menu.fields.station')" :options="stations" />
        <ChoiceGroup v-model="unit" name="unit" :legend="t('events.accounts.menu.fields.unit')" :options="units" />
      </div>

      <fieldset class="group">
        <legend>{{ t('events.accounts.menu.fields.purchase') }}</legend>
        <div class="grid">
          <BaseField v-model="containerName" :label="t('events.accounts.menu.fields.container')" :hint="t('events.accounts.menu.fields.containerHint')" :maxlength="40" />
          <BaseField
            v-model="containerSize"
            :label="t('events.accounts.menu.fields.size')"
            :hint="byTheLitre ? t('events.accounts.menu.fields.sizeHintLitre') : t('events.accounts.menu.fields.sizeHintCount')"
            :inputmode="byTheLitre ? 'decimal' : 'numeric'"
            required
            :error="shown('containerSize')"
          />
          <BaseField
            v-model="purchasePrice"
            :label="t('events.accounts.menu.fields.purchasePrice')"
            :hint="t('events.accounts.menu.fields.purchasePriceHint')"
            inputmode="decimal"
            required
            :error="fieldErrors.purchasePrice"
          />
        </div>
        <div class="grid grid--three">
          <BaseField v-model="planned" :label="t('events.accounts.menu.fields.planned')" inputmode="numeric" :error="shown('planned')" />
          <BaseField v-model="bought" :label="t('events.accounts.menu.fields.bought')" inputmode="numeric" :error="shown('bought')" />
          <BaseField v-model="returned" :label="t('events.accounts.menu.fields.returned')" inputmode="numeric" :error="shown('returned')" />
        </div>
        <p class="muted note">{{ t('events.accounts.menu.fields.quantitiesHint') }}</p>
        <p v-if="preview" class="preview" aria-live="polite">{{ t('events.accounts.menu.fields.preview', preview) }}</p>
      </fieldset>

      <fieldset class="group">
        <legend>{{ t('events.accounts.menu.fields.formats') }}</legend>
        <fieldset v-for="(format, index) in formats" :key="format.key" class="format" :class="{ 'format--removable': formats.length > 1 }">
          <legend class="visually-hidden">{{ t('events.accounts.menu.fields.formatLegend', { n: index + 1 }) }}</legend>
          <BaseField v-model="format.name" :label="t('events.accounts.menu.fields.formatName')" required :maxlength="30" />
          <BaseField
            v-if="byTheLitre"
            v-model="format.volume"
            :label="t('events.accounts.menu.fields.formatVolume')"
            inputmode="decimal"
            :error="shown(`format${format.key}`)"
          />
          <BaseField v-model="format.price" :label="t('events.accounts.menu.fields.formatPrice')" inputmode="decimal" required />
          <BaseField v-model="format.sold" :label="t('events.accounts.menu.fields.formatSold')" inputmode="numeric" :error="shown(`sold${format.key}`)" />
          <button
            v-if="formats.length > 1"
            type="button"
            class="icon-button"
            :aria-label="t('events.accounts.menu.fields.removeFormat', { name: format.name || index + 1 })"
            @click="form.removeFormat(format.key)"
          >
            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
              <path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
          </button>
        </fieldset>
        <button v-if="formats.length < FORMATS_MAX" type="button" class="add-button" @click="form.addFormat()">{{ t('events.accounts.menu.fields.addFormat') }}</button>
        <p v-if="byTheLitre" class="muted note">{{ t('events.accounts.menu.fields.volumeNote') }}</p>
      </fieldset>

      <BaseField
        v-if="station === 'cuisine'"
        v-model="garnishes"
        :label="t('events.accounts.menu.fields.garnishes')"
        :hint="t('events.accounts.menu.fields.garnishesHint')"
        :error="fieldErrors.garnishes"
      />
      <label v-else class="check">
        <input v-model="alcoholic" type="checkbox" />
        <span class="check__text">
          <strong>{{ t('events.accounts.menu.fields.alcoholic') }}</strong>
          <span class="muted">{{ t('events.accounts.menu.fields.alcoholicHint') }}</span>
        </span>
      </label>

      <p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
      <div class="buttons">
        <BaseButton v-if="item" variant="danger" :disabled="pending" @click="emit('remove')">{{ t('events.accounts.remove') }}</BaseButton>
        <span class="buttons__end">
          <BaseButton variant="ghost" @click="emit('close')">{{ t('common.cancel') }}</BaseButton>
          <BaseButton type="submit" :pending="pending">{{ t('common.save') }}</BaseButton>
        </span>
      </div>
    </form>
  </BaseDialog>
</template>

<style scoped>
.pair {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 20rem), 1fr));
  gap: 1rem;
}

.group {
  display: grid;
  gap: 0.75rem;
  min-width: 0;
  margin: 0;
  padding: 0;
  border: 0;
}

.group > legend {
  margin-bottom: 0.4rem;
  padding: 0;
  font-size: 0.9rem;
  font-weight: 600;
}

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 9rem), 1fr));
  gap: 0.75rem 1rem;
}

.grid--three {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.note,
.preview {
  margin: 0;
  font-size: 0.875rem;
}

.preview {
  font-weight: 600;
}

/* Un format par carte : nom, (contenance), prix, vendus ; les champs passent à la ligne, le retrait reste en haut à droite */
.format {
  position: relative;
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 0.5rem 0.75rem;
  min-width: 0;
  margin: 0;
  padding: 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
}

.format--removable {
  padding-inline-end: 3.25rem;
}

.format > .field {
  flex: 1 1 6.5rem;
  min-width: 0;
}

.format .icon-button {
  position: absolute;
  inset-block-start: 0.25rem;
  inset-inline-end: 0.25rem;
}

.icon-button {
  display: grid;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  border: 0;
  border-radius: var(--radius);
  background: transparent;
  color: var(--color-muted);
  cursor: pointer;
}

.icon-button:hover {
  color: var(--color-text);
}

.add-button {
  justify-self: start;
  min-height: 2.75rem;
  padding: 0 1rem;
  border: 1px dashed var(--color-border);
  border-radius: var(--radius);
  background: transparent;
  color: var(--color-text);
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}

.check {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  min-height: 2.75rem;
  cursor: pointer;
}

.check input {
  flex: none;
  width: 1.25rem;
  height: 1.25rem;
  margin: 0.15rem 0 0;
  accent-color: var(--color-accent);
}

.check__text {
  display: grid;
  gap: 0.15rem;
}

.check__text .muted {
  font-size: 0.875rem;
}

.buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

/* Annuler et Enregistrer restent ensemble, à droite, même quand la ligne se coupe */
.buttons__end {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-inline-start: auto;
}
</style>
