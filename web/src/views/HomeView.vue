<template>
  <main class="home">
    <h1>Darts Club</h1>
    <p>Le site du club arrive bientôt.</p>
  </main>
</template>

<style scoped>
.home {
  display: grid;
  place-content: center;
  min-height: 100vh;
  padding: 1rem;
  text-align: center;
}

h1 {
  margin: 0;
  font-size: clamp(2.5rem, 8vw, 5rem);
  letter-spacing: -0.02em;
}

p {
  color: var(--color-muted);
}
</style>
