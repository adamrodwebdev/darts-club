<script setup lang="ts">
/**
 * Scène d'accueil : cible de fléchettes en SVG (20 secteurs, anneaux double/triple), écusson du club
 * et fléchette. Séquence d'ouverture (assemblage, vol, impact dans le double 20), ambiance continue
 * (halo, faisceau, orbites, reflet) et inclinaison 3D au pointeur.
 * Uniquement transform / opacity : l'écusson (élément principal de la page) est visible dès le premier rendu.
 * Ce qui bouge en continu (orbites, double 20 touché) est un <svg> à part animé en entier : le
 * compositeur s'en charge, sans repeindre la cible à chaque image (ombres et halos peints une fois).
 */
import { useTemplateRef } from 'vue'
import { usePointerTilt } from '@/composables/usePointerTilt'
import { LANDING } from '@/content/landing'
import { vReplay } from '@/motion/vReplay'

const SECTORS = [20, 1, 18, 4, 13, 6, 10, 15, 2, 17, 3, 19, 7, 16, 8, 11, 14, 9, 12, 5]
const step = (Math.PI * 2) / 20

function arc(r1: number, r2: number, start: number, end: number): string {
  const p = (r: number, a: number) => `${(Math.sin(a) * r).toFixed(2)} ${(-Math.cos(a) * r).toFixed(2)}`
  return `M${p(r1, start)} L${p(r2, start)} A${r2} ${r2} 0 0 1 ${p(r2, end)} L${p(r1, end)} A${r1} ${r1} 0 0 0 ${p(r1, start)}Z`
}

const sectors = SECTORS.map((value, i) => {
  const start = i * step - step / 2
  const end = start + step
  const even = i % 2 === 0
  return {
    value,
    paths: [
      { d: arc(16, 99, start, end), fill: even ? 'var(--board-dark)' : 'var(--board-light)' },
      { d: arc(99, 107, start, end), fill: even ? 'var(--board-red)' : 'var(--board-green)' },
      { d: arc(107, 162, start, end), fill: even ? 'var(--board-dark)' : 'var(--board-light)' },
      { d: arc(162, 170, start, end), fill: even ? 'var(--board-red)' : 'var(--board-green)' },
    ],
    label: { x: Math.sin(i * step) * 187, y: -Math.cos(i * step) * 187 + 6 },
  }
})

/** Double 20, en haut : la zone touchée par la fléchette */
const hit = arc(162, 170, -step / 2, step / 2)
const WIRES = [170, 162, 107, 99]
/** Étincelles de l'impact : angles répartis vers le haut et les côtés */
const SPARKS = [-150, -120, -95, -70, -45, -20, 0, 25, 50, 75, 100, 130]

const stage = useTemplateRef<HTMLElement>('stage')
usePointerTilt(stage)
</script>

<template>
  <div ref="stage" v-replay class="stage" aria-hidden="true">
    <div class="stage__tilt">
      <!-- Ambiance : halo qui respire, faisceau tournant, anneaux en orbite -->
      <div class="halo" />
      <div class="sweep" />
      <svg class="orbit orbit--a" viewBox="-250 -250 500 500">
        <circle r="226" pathLength="100" />
        <circle class="orbit__dot" cx="0" cy="-226" r="3.5" />
      </svg>
      <svg class="orbit orbit--b" viewBox="-250 -250 500 500">
        <circle r="242" pathLength="100" />
        <circle class="orbit__dot" cx="242" cy="0" r="2.5" />
        <circle class="orbit__dot" cx="-171" cy="171" r="2.5" />
      </svg>

      <div class="board-shake">
        <svg class="board" viewBox="-205 -205 410 410" width="410" height="410">
          <circle r="204" class="board__frame" />
          <g class="board__face">
            <g v-for="(sector, i) in sectors" :key="sector.value" class="sector" :style="{ '--i': i }">
              <path v-for="(p, j) in sector.paths" :key="j" :d="p.d" :fill="p.fill" />
            </g>
            <!-- Fils métalliques : se dessinent à l'ouverture -->
            <circle v-for="(r, i) in WIRES" :key="r" class="wire" :r="r" pathLength="1" :style="{ '--i': i }" />
            <circle r="16" class="bull bull--outer" />
            <circle r="6.5" class="bull" />
          </g>
          <g class="board__numbers">
            <text v-for="(sector, i) in sectors" :key="sector.value" :x="sector.label.x" :y="sector.label.y" class="num" :style="{ '--i': i }">
              {{ sector.value }}
            </text>
          </g>
        </svg>
        <!-- Double 20 touché : calque à part, son halo pulse sans repeindre la cible -->
        <svg class="board-hit" viewBox="-205 -205 410 410" width="410" height="410">
          <path :d="hit" />
        </svg>

        <!-- Écusson : en avant-plan (profondeur à l'inclinaison), rebond à l'impact, reflet périodique -->
        <div class="emblem-depth">
          <div class="emblem-punch">
            <img class="emblem" :src="LANDING.logo" width="673" height="673" alt="" fetchpriority="high" />
            <span class="emblem__glint" />
          </div>
        </div>
      </div>

      <!-- Impact dans le double 20 : onde de choc et étincelles -->
      <div class="impact">
        <span v-for="n in 3" :key="n" class="shock" :style="{ '--i': n - 1 }" />
        <span v-for="a in SPARKS" :key="a" class="spark" :style="{ '--a': `${a}deg` }" />
      </div>

      <!-- Fléchette : la pointe est l'origine de toutes les transformations -->
      <div class="dart-anchor">
        <div class="dart-wobble">
          <svg class="dart" viewBox="0 0 300 32" width="300" height="32">
            <defs>
              <linearGradient id="dart-trail" x1="0" x2="1">
                <stop offset="0" stop-color="#fff" stop-opacity="0.9" />
                <stop offset="1" stop-color="#fff" stop-opacity="0" />
              </linearGradient>
              <linearGradient id="dart-barrel" x1="0" x2="0" y1="0" y2="1">
                <stop offset="0" stop-color="#e9e9ee" />
                <stop offset="0.5" stop-color="#8d8d97" />
                <stop offset="1" stop-color="#4a4a52" />
              </linearGradient>
              <linearGradient id="dart-flight" x1="0" x2="1">
                <stop offset="0" stop-color="#ff4d5e" />
                <stop offset="1" stop-color="#8f0f1c" />
              </linearGradient>
            </defs>
            <rect class="dart__trail" x="150" y="13" width="150" height="6" rx="3" fill="url(#dart-trail)" />
            <path d="M0 16 L30 13.5 L30 18.5 Z" fill="#d7d7dd" />
            <rect x="30" y="10" width="48" height="12" rx="4" fill="url(#dart-barrel)" />
            <path d="M38 10v12M44 10v12M50 10v12M56 10v12M62 10v12" stroke="#3a3a40" stroke-width="1.2" opacity="0.6" />
            <rect x="78" y="13.5" width="30" height="5" rx="1.5" fill="#26262c" />
            <path d="M104 16 L150 1 L140 16 L150 31 Z" fill="url(#dart-flight)" />
            <path d="M104 16 L150 1 L144 9 Z" fill="#fff" opacity="0.25" />
          </svg>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.stage {
  --board-dark: #111114;
  --board-light: #eadfc4;
  --board-red: #c8102e;
  --board-green: #1d6b3a;
  --board-wire: #b3b3bb;
  --board-frame: #18181c;
  --glow: var(--color-accent);
  /* Instant de l'impact : toute la séquence s'organise autour */
  --t-impact: 1.55s;

  position: relative;
  width: min(100%, 28rem);
  aspect-ratio: 1;
  margin-inline: auto;
  perspective: 70rem;
}

/* Cible sous le texte (mobile, tablette) : place pour la fléchette qui dépasse en haut */
.stage {
  margin-top: 16%;
}

@media (width >= 64rem) {
  .stage {
    margin-top: 0;
  }
}

.stage__tilt {
  position: absolute;
  inset: 0;
  /* Couches décoratives : ne jamais intercepter un clic (la fléchette déborde sur le texte) */
  pointer-events: none;
  transform: rotateX(var(--tilt-x, 0deg)) rotateY(var(--tilt-y, 0deg));
  transform-style: preserve-3d;
  transition: transform 0.6s cubic-bezier(0.2, 0.7, 0.2, 1);
}

/* ---------- Ambiance ---------- */
.halo {
  position: absolute;
  inset: -14%;
  border-radius: 50%;
  background: radial-gradient(
    circle,
    color-mix(in srgb, var(--glow) 55%, transparent) 0%,
    color-mix(in srgb, var(--glow) 18%, transparent) 38%,
    transparent 66%
  );
  opacity: 0.75;
  animation:
    fade-in 1.2s ease-out both,
    breathe 5.5s ease-in-out var(--t-impact) infinite;
}

.sweep {
  position: absolute;
  inset: -6%;
  border-radius: 50%;
  background: conic-gradient(from 0deg, transparent 0deg 250deg, rgb(255 255 255 / 0.03) 290deg, rgb(255 255 255 / 0.22) 352deg, transparent 360deg);
  mask: radial-gradient(circle, transparent 47%, #000 49%, #000 70%, transparent 72%);
  mix-blend-mode: screen;
  animation:
    fade-in 1s ease-out 1.2s both,
    rotate 9s linear 1.2s infinite;
}

:root[data-theme='light'] .sweep {
  background: conic-gradient(from 0deg, transparent 0deg 250deg, rgb(193 18 31 / 0.04) 290deg, rgb(193 18 31 / 0.28) 352deg, transparent 360deg);
  mix-blend-mode: multiply;
}

/* Chaque orbite tourne en entier (élément <svg>) : rotation prise en charge par le compositeur */
.orbit {
  position: absolute;
  inset: -11%;
  width: 122%;
  height: 122%;
  overflow: visible;
}

.orbit circle {
  fill: none;
  stroke: color-mix(in srgb, var(--color-muted) 45%, transparent);
  stroke-width: 1.5;
}

.orbit--a circle:first-child {
  stroke-dasharray: 0.6 2.4;
}

.orbit--b circle:first-child {
  stroke-dasharray: 18 7;
  stroke-opacity: 0.6;
}

.orbit .orbit__dot {
  fill: var(--glow);
  stroke: none;
  filter: drop-shadow(0 0 4px var(--glow));
}

.orbit--a {
  animation:
    fade-in 1.4s ease-out 0.6s both,
    rotate 48s linear infinite;
}

.orbit--b {
  animation:
    fade-in 1.4s ease-out 0.6s both,
    rotate 70s linear infinite reverse;
}

/* ---------- Cible ---------- */
.board-shake {
  position: absolute;
  inset: 0;
  transform-style: preserve-3d;
  animation: shake 0.42s cubic-bezier(0.3, 0.6, 0.2, 1) var(--t-impact) both;
}

/* Ombre de la cible (ronde) peinte une fois, au lieu d'un filtre recalculé à chaque image de l'assemblage */
.board-shake::before {
  content: '';
  position: absolute;
  inset: 0.4%;
  border-radius: 50%;
  box-shadow: 0 1.5rem 3rem rgb(0 0 0 / 0.55);
}

.board {
  width: 100%;
  height: auto;
  overflow: visible;
}

.board__frame {
  fill: var(--board-frame);
}

.board__face {
  opacity: 0.9;
}

.sector {
  transform-box: view-box;
  transform-origin: center;
  animation: sector-in 0.9s cubic-bezier(0.2, 0.8, 0.2, 1) calc(var(--i) * 0.035s) both;
}

.sector path {
  stroke: var(--board-wire);
  stroke-width: 0.6;
  stroke-opacity: 0.55;
}

.wire {
  fill: none;
  stroke: var(--board-wire);
  stroke-width: 1.4;
  stroke-dasharray: 1;
  animation: draw 1s cubic-bezier(0.4, 0, 0.2, 1) calc(0.15s + var(--i) * 0.12s) both;
}

.bull {
  fill: var(--board-red);
  stroke: var(--board-wire);
}

.bull--outer {
  fill: var(--board-green);
}

.board-hit {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  overflow: visible;
  opacity: 0;
  animation:
    hit-on 0.3s ease-out var(--t-impact) forwards,
    hit-pulse 2.2s ease-in-out calc(var(--t-impact) + 0.6s) infinite;
}

.board-hit path {
  fill: #ff3346;
  filter: drop-shadow(0 0 6px #ff3346) drop-shadow(0 0 14px rgb(255 51 70 / 0.7));
}

.num {
  fill: #f2f2f2;
  font: 800 19px system-ui, sans-serif;
  text-anchor: middle;
  transform-box: fill-box;
  transform-origin: center;
  animation: num-in 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) calc(0.55s + var(--i) * 0.03s) both;
}

/* ---------- Écusson ---------- */
.emblem-depth {
  position: absolute;
  inset: 13%;
  transform: translateZ(2.5rem);
  transform-style: preserve-3d;
}

.emblem-punch {
  position: relative;
  width: 100%;
  height: 100%;
  border-radius: 50%;
  animation: punch 0.55s cubic-bezier(0.3, 0.6, 0.2, 1) var(--t-impact) both;
}

.emblem {
  display: block;
  width: 100%;
  height: auto;
  border-radius: 50%;
  box-shadow:
    0 0 0 6px var(--board-frame),
    0 0 0 7px color-mix(in srgb, var(--board-wire) 40%, transparent),
    0 1.75rem 3.5rem rgb(0 0 0 / 0.6);
  /* Visible dès le premier rendu : seul le mouvement s'anime */
  animation: emblem-in 1.1s cubic-bezier(0.2, 0.8, 0.2, 1) both;
}

.emblem__glint {
  position: absolute;
  inset: 0;
  overflow: hidden;
  border-radius: 50%;
  pointer-events: none;
}

.emblem__glint::before {
  content: '';
  position: absolute;
  inset: -20% auto -20% -60%;
  width: 45%;
  background: linear-gradient(100deg, transparent, rgb(255 255 255 / 0.28), transparent);
  transform: translateX(0) skewX(-12deg);
  animation: glint 8s ease-in-out calc(var(--t-impact) + 0.4s) infinite;
}

/* ---------- Impact ---------- */
.impact {
  position: absolute;
  /* Double 20 : rayon 166 sur 205 (viewBox), en haut de la cible */
  left: 50%;
  top: 9.5%;
  width: 0;
  height: 0;
  transform: translateZ(1rem);
}

.shock {
  position: absolute;
  left: -3rem;
  top: -3rem;
  width: 6rem;
  height: 6rem;
  border: 2px solid rgb(255 255 255 / 0.85);
  border-radius: 50%;
  box-shadow: 0 0 1rem var(--glow);
  opacity: 0;
  transform: scale(0.1);
  animation: shock 0.9s cubic-bezier(0.1, 0.7, 0.3, 1) calc(var(--t-impact) + var(--i) * 0.11s) forwards;
}

.spark {
  position: absolute;
  left: -1px;
  top: -18px;
  width: 2px;
  height: 18px;
  border-radius: 2px;
  background: linear-gradient(to top, #fff, #ffb3ba, transparent);
  transform-origin: 50% 100%;
  opacity: 0;
  animation: spark 0.6s cubic-bezier(0.1, 0.7, 0.3, 1) var(--t-impact) forwards;
}

/* ---------- Fléchette ---------- */
.dart-anchor {
  position: absolute;
  left: 50%;
  top: 9.5%;
  /* La fléchette occupe la moitié gauche du dessin (la traînée la droite) */
  width: 74%;
  transform: translateZ(3rem);
  transform-style: preserve-3d;
}

.dart-wobble {
  transform-origin: 0 50%;
  animation: wobble 0.75s ease-out var(--t-impact) both;
}

.dart {
  display: block;
  width: 100%;
  height: auto;
  /* La pointe (milieu du bord gauche) coïncide avec le point d'ancrage */
  margin-top: -5.3%;
  overflow: visible;
  transform-origin: 0 50%;
  filter: drop-shadow(0.5rem 1rem 0.6rem rgb(0 0 0 / 0.5));
  animation: throw 0.45s cubic-bezier(0.55, 0, 0.9, 0.45) calc(var(--t-impact) - 0.45s) both;
}

.dart__trail {
  opacity: 0;
  animation: trail 0.45s linear calc(var(--t-impact) - 0.45s) both;
}

/* ---------- Images clés ---------- */
@keyframes fade-in {
  from {
    opacity: 0;
  }
}

@keyframes rotate {
  to {
    transform: rotate(360deg);
  }
}

@keyframes breathe {
  0%,
  100% {
    opacity: 0.75;
    transform: scale(1);
  }
  50% {
    opacity: 1;
    transform: scale(1.06);
  }
}

@keyframes sector-in {
  from {
    opacity: 0;
    transform: rotate(-35deg) scale(0.4);
  }
}

@keyframes draw {
  from {
    stroke-dashoffset: 1;
  }
  to {
    stroke-dashoffset: 0;
  }
}

@keyframes num-in {
  from {
    opacity: 0;
    transform: scale(0.4);
  }
}

@keyframes emblem-in {
  from {
    transform: scale(1.22) rotate(-14deg);
  }
}

@keyframes punch {
  0%,
  100% {
    transform: none;
  }
  25% {
    transform: scale(0.965) rotate(1.5deg);
  }
  60% {
    transform: scale(1.015) rotate(-0.5deg);
  }
}

@keyframes glint {
  0% {
    transform: translateX(0) skewX(-12deg);
  }
  14%,
  100% {
    transform: translateX(420%) skewX(-12deg);
  }
}

@keyframes shake {
  0%,
  100% {
    transform: none;
  }
  15% {
    transform: translate(1.5%, -1%) rotate(0.6deg);
  }
  35% {
    transform: translate(-1.2%, 0.8%) rotate(-0.5deg);
  }
  55% {
    transform: translate(0.7%, -0.4%) rotate(0.3deg);
  }
  75% {
    transform: translate(-0.3%, 0.2%);
  }
}

@keyframes hit-on {
  to {
    opacity: 1;
  }
}

@keyframes hit-pulse {
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0.45;
  }
}

@keyframes shock {
  0% {
    opacity: 0.95;
    transform: scale(0.1);
  }
  100% {
    opacity: 0;
    transform: scale(1.6);
  }
}

@keyframes spark {
  0% {
    opacity: 1;
    transform: rotate(var(--a)) translateY(0) scaleY(1.4);
  }
  100% {
    opacity: 0;
    transform: rotate(var(--a)) translateY(-3.2rem) scaleY(0.2);
  }
}

@keyframes throw {
  0% {
    opacity: 0;
    transform: translate(90%, -150%) rotate(-24deg) scale(2.6);
  }
  12% {
    opacity: 1;
  }
  100% {
    opacity: 1;
    transform: rotate(-38deg);
  }
}

@keyframes trail {
  0% {
    opacity: 0;
  }
  20%,
  80% {
    opacity: 0.9;
  }
  100% {
    opacity: 0;
  }
}

@keyframes wobble {
  0%,
  100% {
    transform: none;
  }
  18% {
    transform: rotate(-6deg);
  }
  38% {
    transform: rotate(4deg);
  }
  58% {
    transform: rotate(-2.4deg);
  }
  78% {
    transform: rotate(1deg);
  }
}

/* Animations coupées (réglage du site ou de l'appareil) : état final immobile, fléchette plantée */
:root[data-motion='off'] .sweep,
:root[data-motion='off'] .shock,
:root[data-motion='off'] .spark,
:root[data-motion='off'] .dart__trail {
  display: none;
}

:root[data-motion='off'] .board-hit {
  opacity: 0.85;
}
</style>
