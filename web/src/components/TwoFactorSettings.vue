<script setup lang="ts">
import { ref } from 'vue'
import BaseButton from '@/components/BaseButton.vue'
import BaseField from '@/components/BaseField.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import type { TwoFactorSetup } from '@/services/AuthService'
import { services } from '@/services'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

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
  const blob = new Blob([`Codes de secours Darts Club\n\n${backupCodes.value.join('\n')}\n`], { type: 'text/plain' })
  const url = URL.createObjectURL(blob)
  const link = Object.assign(document.createElement('a'), { href: url, download: 'darts-club-codes-de-secours.txt' })
  link.click()
  URL.revokeObjectURL(url)
}
</script>

<template>
  <section class="card stack" aria-labelledby="tfa-title">
    <h2 id="tfa-title">Double authentification</h2>

    <p v-if="auth.user?.twoFactorSetupRequired" class="notice notice--error" role="alert">
      Obligatoire pour les membres du bureau : active-la pour accéder aux fonctions du club.
    </p>

    <!-- Codes de secours affichés une seule fois -->
    <div v-if="backupCodes.length" class="notice notice--success stack" role="status">
      <p><strong>Double authentification activée.</strong> Garde ces codes de secours en lieu sûr : chacun ne sert qu’une fois.</p>
      <ul class="codes">
        <li v-for="c in backupCodes" :key="c"><code>{{ c }}</code></li>
      </ul>
      <div class="actions">
        <BaseButton variant="ghost" @click="downloadCodes">Télécharger</BaseButton>
        <BaseButton variant="ghost" @click="backupCodes = []">J’ai noté mes codes</BaseButton>
      </div>
    </div>

    <template v-else-if="auth.user?.twoFactorEnabled">
      <p class="muted">Active : un code de ton application est demandé à chaque connexion.</p>
      <form class="stack" novalidate @submit.prevent="deactivate">
        <BaseField v-model="disableCode" label="Code actuel ou code de secours" autocomplete="one-time-code" :maxlength="20" />
        <p v-if="disable.error.value" class="notice notice--error" role="alert">{{ disable.error.value }}</p>
        <BaseButton type="submit" variant="danger" :pending="disable.pending.value" :disabled="!disableCode">Désactiver</BaseButton>
      </form>
    </template>

    <template v-else-if="setup">
      <ol class="steps">
        <li>Scanne ce QR code avec une application d’authentification (Aegis, 2FAS, Google Authenticator…).</li>
        <li>Saisis le code à 6 chiffres affiché.</li>
      </ol>
      <img class="qr" :src="setup.qrCode" alt="QR code de configuration de la double authentification" width="240" height="240" />
      <details>
        <summary>Saisie manuelle</summary>
        <p class="secret"><code>{{ setup.secret }}</code></p>
      </details>
      <form class="stack" novalidate @submit.prevent="activate">
        <BaseField v-model="code" label="Code à 6 chiffres" autocomplete="one-time-code" :maxlength="6" required />
        <p v-if="confirm.error.value" class="notice notice--error" role="alert">{{ confirm.error.value }}</p>
        <BaseButton type="submit" :pending="confirm.pending.value">Activer</BaseButton>
      </form>
    </template>

    <template v-else>
      <p class="muted">Protège ton compte avec un code temporaire en plus du mot de passe.</p>
      <p v-if="start.error.value" class="notice notice--error" role="alert">{{ start.error.value }}</p>
      <BaseButton :pending="start.pending.value" @click="begin">Configurer</BaseButton>
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
