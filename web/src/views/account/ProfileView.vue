<script setup lang="ts">
import { ref, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/BaseButton.vue'
import BaseField from '@/components/BaseField.vue'
import MemberBadges from '@/components/MemberBadges.vue'
import UserAvatar from '@/components/UserAvatar.vue'
import DisplaySettings from '@/components/preferences/DisplaySettings.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import type { Profile } from '@/models/Profile'
import { services } from '@/services'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const { t } = useI18n()
const user = auth.user as Profile

const displayName = ref(user.displayName)
const phone = ref(user.phone ?? '')
const saved = ref(false)
const form = useAsyncAction()
const avatar = useAsyncAction()
const fileInput = useTemplateRef<HTMLInputElement>('file')

async function save(): Promise<void> {
  saved.value = false
  const profile = await form.run(() => services.profile.update({ displayName: displayName.value, phone: phone.value }))
  if (profile) {
    auth.setUser(profile)
    saved.value = true
  }
}

async function onFile(event: Event): Promise<void> {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return
  const profile = await avatar.run(() => services.profile.uploadAvatar(file))
  if (profile) auth.setUser(profile)
  if (fileInput.value) fileInput.value.value = ''
}

async function removeAvatar(): Promise<void> {
  const profile = await avatar.run(() => services.profile.removeAvatar())
  if (profile) auth.setUser(profile)
}
</script>

<template>
  <div class="grid">
    <section class="card avatar-card" aria-labelledby="avatar-title">
      <h2 id="avatar-title">{{ t('account.profile.avatarTitle') }}</h2>
      <UserAvatar :name="auth.user?.displayName ?? ''" :src="auth.user?.avatarUrl" :size="112" />
      <MemberBadges :badges="auth.user?.badges ?? []" />
      <div class="avatar-actions">
        <label class="upload">
          <input ref="file" type="file" accept="image/jpeg,image/png,image/webp" class="visually-hidden" @change="onFile" />
          <span>{{ avatar.pending.value ? t('account.profile.uploading') : t('account.profile.change') }}</span>
        </label>
        <BaseButton v-if="auth.user?.avatarUrl" variant="ghost" :pending="avatar.pending.value" @click="removeAvatar">{{ t('account.profile.remove') }}</BaseButton>
      </div>
      <p class="muted">{{ t('account.profile.avatarHint') }}</p>
      <p v-if="avatar.error.value" class="notice notice--error" role="alert">{{ avatar.error.value }}</p>
    </section>

    <section class="card" aria-labelledby="info-title">
      <h2 id="info-title">{{ t('account.profile.infoTitle') }}</h2>
      <form class="stack" novalidate @submit.prevent="save">
        <dl class="readonly">
          <dt>{{ t('common.email') }}</dt>
          <dd>{{ auth.user?.email }}</dd>
          <dd class="muted">{{ t('account.profile.emailHint') }}</dd>
        </dl>
        <BaseField
          v-model="displayName"
          :label="t('account.profile.displayName')"
          :maxlength="40"
          required
          :error="form.fieldErrors.value.displayName"
          :hint="t('account.profile.displayNameHint')"
        />
        <BaseField
          v-model="phone"
          :label="t('account.profile.phone')"
          type="tel"
          autocomplete="tel"
          :error="form.fieldErrors.value.phone"
          :hint="t('account.profile.phoneHint')"
        />
        <p v-if="form.error.value" class="notice notice--error" role="alert">{{ form.error.value }}</p>
        <p v-if="saved" class="notice notice--success" role="status">{{ t('account.profile.saved') }}</p>
        <BaseButton type="submit" :pending="form.pending.value">{{ t('common.save') }}</BaseButton>
      </form>
    </section>

    <DisplaySettings />
  </div>
</template>

<style scoped>
.grid {
  display: grid;
  gap: 1.5rem;
}

.avatar-card {
  display: grid;
  justify-items: center;
  gap: 0.75rem;
  text-align: center;
}

.avatar-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 0.5rem;
}

.readonly {
  display: grid;
  gap: 0.2rem;
  margin: 0;
}

.readonly dt {
  font-weight: 600;
}

.readonly dd {
  margin: 0;
  overflow-wrap: anywhere;
}

.upload {
  display: inline-flex;
  align-items: center;
  min-height: 2.75rem;
  padding: 0 1.2rem;
  border-radius: var(--radius);
  background: var(--color-accent);
  color: var(--color-on-accent);
  font-weight: 700;
  cursor: pointer;
}

.upload:focus-within {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
}

@media (width >= 48rem) {
  .grid {
    grid-template-columns: minmax(220px, 1fr) 2fr;
    align-items: start;
  }
}
</style>
