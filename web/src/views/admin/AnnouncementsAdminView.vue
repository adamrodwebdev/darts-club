<script setup lang="ts">
import { onMounted, ref } from 'vue'
import BaseButton from '@/components/BaseButton.vue'
import BaseDialog from '@/components/BaseDialog.vue'
import BaseField from '@/components/BaseField.vue'
import RelativeTime from '@/components/RelativeTime.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import type { Announcement } from '@/models/Admin'
import { services } from '@/services'

const announcements = ref<Announcement[]>([])
const title = ref('')
const body = ref('')
const pinned = ref(false)
const publish = useAsyncAction()
const action = useAsyncAction()
const editing = ref<Announcement | null>(null)
const editTitle = ref('')
const editBody = ref('')
const deleting = ref<Announcement | null>(null)

async function refresh(): Promise<void> {
  announcements.value = await services.admin.announcements().catch(() => [])
}
onMounted(refresh)

async function submit(): Promise<void> {
  const created = await publish.run(() => services.admin.publish({ title: title.value, body: body.value, pinned: pinned.value }))
  if (created) {
    title.value = body.value = ''
    pinned.value = false
    await refresh()
  }
}

async function togglePin(a: Announcement): Promise<void> {
  if (await action.run(() => services.admin.updateAnnouncement(a.id, { pinned: !a.pinned }))) await refresh()
}

function startEdit(a: Announcement): void {
  editing.value = a
  editTitle.value = a.title
  editBody.value = a.body
}

async function saveEdit(): Promise<void> {
  const a = editing.value
  if (a && (await action.run(() => services.admin.updateAnnouncement(a.id, { title: editTitle.value, body: editBody.value })))) {
    editing.value = null
    await refresh()
  }
}

async function confirmDelete(): Promise<void> {
  const a = deleting.value
  if (!a) return
  const ok = await action.run(async () => {
    await services.admin.deleteAnnouncement(a.id)
    return true
  })
  if (ok) {
    deleting.value = null
    await refresh()
  }
}
</script>

<template>
  <div class="stack">
    <form class="card stack" novalidate @submit.prevent="submit">
      <h2>Publier une annonce</h2>
      <BaseField v-model="title" label="Titre" required :maxlength="120" />
      <BaseField v-model="body" label="Texte" multiline required :maxlength="5000" />
      <label class="check"><input v-model="pinned" type="checkbox" /> Épingler en haut du fil</label>
      <p class="muted">Tous les adhérents sont prévenus (centre de notifications, email / push selon leurs préférences).</p>
      <p v-if="publish.error.value" class="notice notice--error" role="alert">{{ publish.error.value }}</p>
      <BaseButton type="submit" :pending="publish.pending.value" :disabled="!title.trim() || !body.trim()">Publier</BaseButton>
    </form>

    <p v-if="action.error.value && !editing && !deleting" class="notice notice--error" role="alert">{{ action.error.value }}</p>

    <article v-for="a in announcements" :key="a.id" class="card">
      <h3>{{ a.pinned ? '📌 ' : '' }}{{ a.title }}</h3>
      <p class="body">{{ a.body }}</p>
      <p class="muted"><RelativeTime :date="a.publishedAt" /> · {{ a.author }}</p>
      <div class="buttons">
        <BaseButton variant="ghost" @click="togglePin(a)">{{ a.pinned ? 'Désépingler' : 'Épingler' }}</BaseButton>
        <BaseButton variant="ghost" @click="startEdit(a)">Modifier</BaseButton>
        <BaseButton variant="danger" @click="deleting = a">Supprimer</BaseButton>
      </div>
    </article>

    <BaseDialog v-if="editing" title="Modifier l’annonce" @close="editing = null">
      <form class="stack" @submit.prevent="saveEdit">
        <BaseField v-model="editTitle" label="Titre" required :maxlength="120" />
        <BaseField v-model="editBody" label="Texte" multiline required :maxlength="5000" />
        <p class="muted">La correction ne renvoie pas de notification.</p>
        <p v-if="action.error.value" class="notice notice--error" role="alert">{{ action.error.value }}</p>
        <div class="buttons">
          <BaseButton variant="ghost" @click="editing = null">Annuler</BaseButton>
          <BaseButton type="submit" :pending="action.pending.value">Enregistrer</BaseButton>
        </div>
      </form>
    </BaseDialog>

    <BaseDialog v-if="deleting" title="Supprimer l’annonce ?" @close="deleting = null">
      <p>« {{ deleting.title }} » disparaîtra du fil des adhérents.</p>
      <div class="buttons">
        <BaseButton variant="ghost" @click="deleting = null">Annuler</BaseButton>
        <BaseButton variant="danger" :pending="action.pending.value" @click="confirmDelete">Supprimer</BaseButton>
      </div>
    </BaseDialog>
  </div>
</template>

<style scoped>
h2,
h3 {
  margin: 0;
}

.body {
  white-space: pre-wrap;
}

.check {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-height: 44px;
}

.check input {
  width: 1.3rem;
  height: 1.3rem;
  accent-color: var(--color-accent);
}

.buttons {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.5rem;
}
</style>
