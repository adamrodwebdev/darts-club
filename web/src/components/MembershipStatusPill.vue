<script setup lang="ts">
import type { MembershipRecord } from '@/models/Membership'

defineProps<{ membership: Pick<MembershipRecord, 'status' | 'overdue'> }>()
</script>

<template>
  <span class="status" :class="[`status--${membership.status.id}`, { 'status--overdue': membership.overdue }]">
    {{ membership.status.label }}<template v-if="membership.overdue"> · en retard</template>
  </span>
</template>

<style scoped>
.status {
  display: inline-block;
  padding: 0.15rem 0.6rem;
  border: 1px solid currentColor;
  border-radius: 999px;
  font-size: 0.8rem;
  font-weight: 700;
  white-space: nowrap;
}

.status--payee {
  color: var(--color-success);
}

.status--partielle {
  color: var(--badge-gold);
}

.status--impayee {
  color: var(--color-muted);
}

.status--overdue {
  color: var(--color-danger);
}
</style>
