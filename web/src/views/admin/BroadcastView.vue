<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/BaseButton.vue'
import BaseField from '@/components/BaseField.vue'
import RelativeTime from '@/components/RelativeTime.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { labelled } from '@/i18n/labels'
import type { AdminMember, BroadcastRecord } from '@/models/Admin'
import { useServices } from '@/services'

const services = useServices()

const { t } = useI18n()

const LABELS = (['cotisation', 'evenement', 'reunion', 'annulation'] as const).map((id) => labelled('category', id))

const label = ref('evenement')
const title = ref('')
const body = ref('')
const audience = ref<'all' | 'selection'>('all')
const selected = ref<string[]>([])
const filter = ref('')
const members = ref<AdminMember[]>([])
const history = ref<BroadcastRecord[]>([])
const sent = ref<string | null>(null)
const send = useAsyncAction()

const visibleMembers = computed(() => {
  const q = filter.value.trim().toLowerCase()
  return members.value.filter((m) => m.verified && !m.suspended && (!q || m.displayName.toLowerCase().includes(q)))
})

onMounted(async () => {
  const [list, past] = await Promise.all([services.admin.members().catch(() => []), services.admin.broadcasts().catch(() => [])])
  members.value = list
  history.value = past
})

async function submit(): Promise<void> {
  sent.value = null
  const result = await send.run(() =>
    services.admin.broadcast({
      label: label.value,
      title: title.value,
      body: body.value,
      userIds: audience.value === 'selection' ? selected.value : undefined,
    }),
  )
  if (result) {
    sent.value = t('admin.broadcast.sent', result.recipientCount)
    history.value = [result, ...history.value]
    title.value = body.value = ''
    selected.value = []
  }
}
</script>

<template>
  <div class="stack">
    <form class="card stack" novalidate @submit.prevent="submit">
      <h2>{{ t('admin.broadcast.title') }}</h2>

      <fieldset class="choices">
        <legend>{{ t('admin.broadcast.label') }}</legend>
        <label v-for="l in LABELS" :key="l.id" class="chip" :class="{ 'chip--on': label === l.id, 'chip--danger': l.id === 'annulation' }">
          <input v-model="label" type="radio" name="label" :value="l.id" class="visually-hidden" /> {{ l.label }}
        </label>
      </fieldset>

      <BaseField v-model="title" :label="t('admin.broadcast.subject')" required :maxlength="120" />
      <BaseField v-model="body" :label="t('admin.broadcast.body')" multiline required :maxlength="2000" />

      <fieldset class="choices">
        <legend>{{ t('admin.broadcast.recipients') }}</legend>
        <label><input v-model="audience" type="radio" value="all" /> {{ t('admin.broadcast.allMembers') }}</label>
        <label><input v-model="audience" type="radio" value="selection" /> {{ t('admin.broadcast.selection') }}</label>
      </fieldset>

      <div v-if="audience === 'selection'" class="selection">
        <BaseField v-model="filter" :label="t('admin.broadcast.filter')" />
        <p class="muted">{{ t('admin.broadcast.selected', selected.length) }}</p>
        <ul class="picker">
          <li v-for="m in visibleMembers" :key="m.id">
            <label><input v-model="selected" type="checkbox" :value="m.id" /> {{ m.displayName }}</label>
          </li>
        </ul>
      </div>

      <p class="muted">{{ t('admin.broadcast.delivery') }}</p>
      <p v-if="send.error.value" class="notice notice--error" role="alert">{{ send.error.value }}</p>
      <p v-if="sent" class="notice notice--success" role="status">{{ sent }}</p>
      <BaseButton type="submit" :pending="send.pending.value" :disabled="!title.trim() || !body.trim() || (audience === 'selection' && !selected.length)">
        {{ t('admin.broadcast.send') }}
      </BaseButton>
    </form>

    <section v-if="history.length" class="card" aria-labelledby="history-title">
      <h2 id="history-title">{{ t('admin.broadcast.history') }}</h2>
      <ul class="history">
        <li v-for="b in history" :key="b.id">
          <strong>[{{ b.labelText }}] {{ b.title }}</strong>
          <span class="muted">
            {{ b.toAllMembers ? t('admin.broadcast.toAll') : t('admin.broadcast.recipientCount', b.recipientCount) }} · {{ b.author }} · <RelativeTime :date="b.sentAt" />
          </span>
        </li>
      </ul>
    </section>
  </div>
</template>

<style scoped>
h2 {
  margin: 0;
}

.choices {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem 1rem;
  margin: 0;
  padding: 0;
  border: 0;
}

.choices legend {
  margin-bottom: 0.5rem;
  font-weight: 600;
}

.choices label {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  min-height: 44px;
}

.chip {
  padding: 0 1rem;
  border: 1px solid var(--color-border);
  border-radius: 999px;
  cursor: pointer;
}

.chip:focus-within {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
}

.chip--on {
  border-color: var(--color-accent);
  background: var(--color-accent);
  color: var(--color-on-accent);
}

.chip--danger.chip--on {
  border-color: var(--color-danger);
  background: transparent;
  color: var(--color-danger);
}

.selection {
  display: grid;
  gap: 0.5rem;
}

.picker {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr));
  gap: 0.25rem;
  max-height: 16rem;
  margin: 0;
  padding: 0;
  overflow-y: auto;
  list-style: none;
}

.picker label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-height: 44px;
}

.history {
  display: grid;
  gap: 0.75rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.history li {
  display: grid;
}
</style>
