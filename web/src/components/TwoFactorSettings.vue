<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/BaseButton.vue'
import BaseField from '@/components/BaseField.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import type { TwoFactorSetup } from '@/services/AuthService'
import { services } from '@/services'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const { t } = useI18n()

const setup = ref<TwoFactorSetup | null>(null)
const code = ref('')
const backupCodes = ref<string[]>([])
const start = useAsyncAction()
const confirm = useAsyncAction()
const disable = useAsyncAction()
const disableCode = ref('')

async function refreshProfile(): Promise<void> {
  auth.setUser(await services.auth.me())
}

async function begin(): Promise<void> {
  const result = await start.run(() => services.profile.startTwoFactor())
  if (result) setup.value = result
}

async function activate(): Promise<void> {
  const codes = await confirm.run(() => services.profile.confirmTwoFactor(code.value))
  if (codes) {
    backupCodes.value = codes
    setup.value = null
    code.value = ''
    await refreshProfile()
  }
}

async function deactivate(): Promise<void> {
  const ok = await disable.run(async () => {
    await services.profile.disableTwoFactor(disableCode.value)
    return true
  })
  if (ok) {
    disableCode.value = ''
    await refreshProfile()
  }
}

function downloadCodes(): void {
  const blob = new Blob([`${t('account.twoFactor.fileHeader')}\n\n${backupCodes.value.join('\n')}\n`], { type: 'text/plain' })
  const url = URL.createObjectURL(blob)
  const link = Object.assign(document.createElement('a'), { href: url, download: `${t('account.twoFactor.fileName')}.txt` })
  link.click()
  URL.revokeObjectURL(url)
}
</script>

<template>
  <section class="card stack" aria-labelledby="tfa-title">
    <h2 id="tfa-title">{{ t('account.twoFactor.title') }}</h2>

    <p v-if="auth.user?.twoFactorSetupRequired" class="notice notice--error" role="alert">
      {{ t('account.twoFactor.required') }}
    </p>

    <!-- Codes de secours affichés une seule fois -->
    <div v-if="backupCodes.length" class="notice notice--success stack" role="status">
      <p>
        <strong>{{ t('account.twoFactor.enabled') }}</strong> {{ t('account.twoFactor.backupIntro') }}
      </p>
      <ul class="codes">
        <li v-for="c in backupCodes" :key="c"><code>{{ c }}</code></li>
      </ul>
      <div class="actions">
        <BaseButton variant="ghost" @click="downloadCodes">{{ t('account.twoFactor.download') }}</BaseButton>
        <BaseButton variant="ghost" @click="backupCodes = []">{{ t('account.twoFactor.codesNoted') }}</BaseButton>
      </div>
    </div>

    <template v-else-if="auth.user?.twoFactorEnabled">
      <p class="muted">{{ t('account.twoFactor.active') }}</p>
      <form class="stack" novalidate @submit.prevent="deactivate">
        <BaseField v-model="disableCode" :label="t('account.twoFactor.disableCode')" autocomplete="one-time-code" :maxlength="20" />
        <p v-if="disable.error.value" class="notice notice--error" role="alert">{{ disable.error.value }}</p>
        <BaseButton type="submit" variant="danger" :pending="disable.pending.value" :disabled="!disableCode">{{ t('account.twoFactor.disable') }}</BaseButton>
      </form>
    </template>

    <template v-else-if="setup">
      <ol class="steps">
        <li>{{ t('account.twoFactor.stepScan') }}</li>
        <li>{{ t('account.twoFactor.stepCode') }}</li>
      </ol>
      <img class="qr" :src="setup.qrCode" :alt="t('account.twoFactor.qrAlt')" width="240" height="240" />
      <details>
        <summary>{{ t('account.twoFactor.manualEntry') }}</summary>
        <p class="secret"><code>{{ setup.secret }}</code></p>
      </details>
      <form class="stack" novalidate @submit.prevent="activate">
        <BaseField v-model="code" :label="t('account.twoFactor.codeLabel')" autocomplete="one-time-code" :maxlength="6" required />
        <p v-if="confirm.error.value" class="notice notice--error" role="alert">{{ confirm.error.value }}</p>
        <BaseButton type="submit" :pending="confirm.pending.value">{{ t('account.twoFactor.activate') }}</BaseButton>
      </form>
    </template>

    <template v-else>
      <p class="muted">{{ t('account.twoFactor.intro') }}</p>
      <p v-if="start.error.value" class="notice notice--error" role="alert">{{ start.error.value }}</p>
      <BaseButton :pending="start.pending.value" @click="begin">{{ t('account.twoFactor.configure') }}</BaseButton>
    </template>
  </section>
</template>

<style scoped>
.steps {
  margin: 0;
  padding-left: 1.25rem;
}

.qr {
  justify-self: center;
  width: min(240px, 100%);
  height: auto;
  border-radius: var(--radius);
  background: #fff;
}

.secret,
.codes code {
  font-family: ui-monospace, 'Cascadia Code', monospace;
  overflow-wrap: anywhere;
}

.codes {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(8rem, 1fr));
  gap: 0.5rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

summary {
  min-height: 44px;
  display: flex;
  align-items: center;
  cursor: pointer;
}
</style>
