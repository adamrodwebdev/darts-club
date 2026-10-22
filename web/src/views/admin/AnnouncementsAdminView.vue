<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/BaseButton.vue'
import BaseDialog from '@/components/BaseDialog.vue'
import BaseField from '@/components/BaseField.vue'
import RelativeTime from '@/components/RelativeTime.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import type { Announcement } from '@/models/Admin'
import { useServices } from '@/services'

const services = useServices()

const { t } = useI18n()
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
      <h2>{{ t('admin.announcements.title') }}</h2>
      <BaseField v-model="title" :label="t('admin.announcements.subject')" required :maxlength="120" />
      <BaseField v-model="body" :label="t('admin.announcements.body')" multiline required :maxlength="5000" />
      <label class="check"><input v-model="pinned" type="checkbox" /> {{ t('admin.announcements.pin') }}</label>
      <p class="muted">{{ t('admin.announcements.notifyHint') }}</p>
      <p v-if="publish.error.value" class="notice notice--error" role="alert">{{ publish.error.value }}</p>
      <BaseButton type="submit" :pending="publish.pending.value" :disabled="!title.trim() || !body.trim()">{{ t('admin.announcements.publish') }}</BaseButton>
    </form>

    <p v-if="action.error.value && !editing && !deleting" class="notice notice--error" role="alert">{{ action.error.value }}</p>

    <article v-for="a in announcements" :key="a.id" class="card">
      <h3>{{ a.pinned ? t('admin.announcements.pinnedTitle', { title: a.title }) : a.title }}</h3>
      <p class="body">{{ a.body }}</p>
      <p class="muted"><RelativeTime :date="a.publishedAt" /> · {{ a.author }}</p>
      <div class="buttons">
        <BaseButton variant="ghost" @click="togglePin(a)">{{ a.pinned ? t('admin.announcements.unpinAction') : t('admin.announcements.pinAction') }}</BaseButton>
        <BaseButton variant="ghost" @click="startEdit(a)">{{ t('common.edit') }}</BaseButton>
        <BaseButton variant="danger" @click="deleting = a">{{ t('common.delete') }}</BaseButton>
      </div>
    </article>

    <BaseDialog v-if="editing" :title="t('admin.announcements.editTitle')" @close="editing = null">
      <form class="stack" @submit.prevent="saveEdit">
        <BaseField v-model="editTitle" :label="t('admin.announcements.subject')" required :maxlength="120" />
        <BaseField v-model="editBody" :label="t('admin.announcements.body')" multiline required :maxlength="5000" />
        <p class="muted">{{ t('admin.announcements.editHint') }}</p>
        <p v-if="action.error.value" class="notice notice--error" role="alert">{{ action.error.value }}</p>
        <div class="buttons">
          <BaseButton variant="ghost" @click="editing = null">{{ t('common.cancel') }}</BaseButton>
          <BaseButton type="submit" :pending="action.pending.value">{{ t('common.save') }}</BaseButton>
        </div>
      </form>
    </BaseDialog>

    <BaseDialog v-if="deleting" :title="t('admin.announcements.deleteTitle')" @close="deleting = null">
      <p>{{ t('admin.announcements.deleteText', { title: deleting.title }) }}</p>
      <div class="buttons">
        <BaseButton variant="ghost" @click="deleting = null">{{ t('common.cancel') }}</BaseButton>
        <BaseButton variant="danger" :pending="action.pending.value" @click="confirmDelete">{{ t('common.delete') }}</BaseButton>
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
