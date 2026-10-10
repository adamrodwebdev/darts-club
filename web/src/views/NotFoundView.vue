<template>
  <main class="not-found">
    <h1>Page introuvable</h1>
    <p>Raté, la fléchette est à côté de la cible.</p>
    <RouterLink to="/">Retour à l'accueil</RouterLink>
  </main>
</template>

<style scoped>
.not-found {
  display: grid;
  place-content: center;
  gap: 0.5rem;
  min-height: 100vh;
  padding: 1rem;
  text-align: center;
}

p {
  color: var(--color-muted);
}
</style>
