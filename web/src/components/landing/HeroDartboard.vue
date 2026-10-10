<script setup lang="ts">
/** Cible de fléchettes en SVG (20 secteurs, anneaux double/triple) + fléchette qui se plante. */
const SECTORS = [20, 1, 18, 4, 13, 6, 10, 15, 2, 17, 3, 19, 7, 16, 8, 11, 14, 9, 12, 5]

function arc(r1: number, r2: number, start: number, end: number): string {
  const p = (r: number, a: number) => `${(Math.sin(a) * r).toFixed(2)} ${(-Math.cos(a) * r).toFixed(2)}`
  return `M${p(r1, start)} L${p(r2, start)} A${r2} ${r2} 0 0 1 ${p(r2, end)} L${p(r1, end)} A${r1} ${r1} 0 0 0 ${p(r1, start)}Z`
}

const step = (Math.PI * 2) / 20
const segments = SECTORS.flatMap((value, i) => {
  const start = i * step - step / 2
  const end = start + step
  const even = i % 2 === 0
  return [
    { d: arc(16, 99, start, end), fill: even ? 'var(--board-dark)' : 'var(--board-light)' },
    { d: arc(99, 107, start, end), fill: even ? 'var(--board-red)' : 'var(--board-green)' },
    { d: arc(107, 162, start, end), fill: even ? 'var(--board-dark)' : 'var(--board-light)' },
    { d: arc(162, 170, start, end), fill: even ? 'var(--board-red)' : 'var(--board-green)' },
    { label: String(value), x: Math.sin(i * step) * 186, y: -Math.cos(i * step) * 186 + 6 },
  ]
})
</script>

<template>
  <div class="board-wrap" aria-hidden="true">
    <svg class="board" viewBox="-205 -205 410 410" width="410" height="410">
      <circle r="204" fill="var(--board-frame)" />
      <g class="board__face">
        <template v-for="(s, i) in segments" :key="i">
          <path v-if="'d' in s" :d="s.d" :fill="s.fill" stroke="var(--board-wire)" stroke-width="1" />
          <text v-else :x="s.x" :y="s.y" class="board__num">{{ s.label }}</text>
        </template>
        <circle r="16" fill="var(--board-green)" stroke="var(--board-wire)" />
        <circle r="6.5" fill="var(--board-red)" stroke="var(--board-wire)" />
      </g>
    </svg>
    <!-- Fléchette : vole puis se plante dans le triple 20 -->
    <svg class="dart" viewBox="0 0 120 24" width="120" height="24">
      <path d="M0 12 L22 10 L22 14 Z" fill="var(--dart-tip)" />
      <rect x="22" y="8" width="40" height="8" rx="3" fill="var(--dart-barrel)" />
      <rect x="62" y="10.5" width="22" height="3" fill="var(--dart-shaft)" />
      <path d="M84 12 L120 0 L112 12 L120 24 Z" fill="var(--color-accent)" />
    </svg>
  </div>
</template>

<style scoped>
.board-wrap {
  --board-dark: #121214;
  --board-light: #e8dcc0;
  --board-red: #c8102e;
  --board-green: #1d6b3a;
  --board-wire: #9a9aa2;
  --board-frame: #1b1b1f;
  --dart-tip: #c9c9cf;
  --dart-barrel: #7b7b85;
  --dart-shaft: #2b2b31;

  position: relative;
  width: min(100%, 26rem);
  aspect-ratio: 1;
  margin-inline: auto;
}

.board {
  width: 100%;
  height: auto;
  filter: drop-shadow(0 1.5rem 3rem rgb(0 0 0 / 55%));
}

.board__face {
  transform-origin: center;
  animation: spin 120s linear infinite;
}

.board__num {
  fill: #f2f2f2;
  font: 700 18px system-ui, sans-serif;
  text-anchor: middle;
}

.dart {
  position: absolute;
  /* Triple 20 : un peu au-dessus du centre */
  top: 26%;
  left: 50%;
  width: 34%;
  height: auto;
  transform-origin: 0 50%;
  animation: throw 1.1s cubic-bezier(0.2, 0.7, 0.2, 1) 0.4s both;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

@keyframes throw {
  from {
    opacity: 0;
    transform: translate(140%, -160%) rotate(-28deg) scale(1.6);
  }
  85% {
    transform: translate(0, 0) rotate(-12deg) scale(1);
  }
  92% {
    transform: translate(0, 0) rotate(-16deg);
  }
  to {
    opacity: 1;
    transform: translate(0, 0) rotate(-14deg);
  }
}

@media (prefers-reduced-motion: reduce) {
  .board__face,
  .dart {
    animation: none;
  }

  .dart {
    transform: rotate(-14deg);
  }
}
</style>
