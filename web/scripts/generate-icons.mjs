/**
 * Génère favicons, icônes d'application (manifest) et image de partage
 * à partir de public/logo.svg.  Usage : npm run icons
 */
import { copyFile, readFile } from 'node:fs/promises'
import { fileURLToPath } from 'node:url'
import sharp from 'sharp'

const pub = (file) => fileURLToPath(new URL(`../public/${file}`, import.meta.url))
const BACKGROUND = '#0b0b0d'
const logo = await readFile(pub('logo.svg'))

/** Logo rendu net à la taille voulue */
const render = (size) => sharp(logo, { density: Math.ceil((size / 673) * 72 * 2) }).resize(size, size).png().toBuffer()

/** Logo centré sur fond sombre, avec une marge (zone de sécurité des icônes masquables) */
async function onBackground(size, logoRatio, output) {
  const inner = Math.round(size * logoRatio)
  await sharp({ create: { width: size, height: size, channels: 4, background: BACKGROUND } })
    .composite([{ input: await render(inner), gravity: 'center' }])
    .png({ compressionLevel: 9 })
    .toFile(pub(output))
}

await sharp(await render(32)).png({ compressionLevel: 9 }).toFile(pub('favicon-32.png'))
await copyFile(pub('favicon-32.png'), pub('favicon.ico')) // PNG dans un .ico : accepté par les navigateurs
await onBackground(180, 0.9, 'apple-touch-icon.png')
await sharp(await render(192)).png({ compressionLevel: 9 }).toFile(pub('icon-192.png'))
await sharp(await render(512)).png({ compressionLevel: 9 }).toFile(pub('icon-512.png'))
await onBackground(512, 0.78, 'icon-maskable-512.png')

// Image de partage (réseaux sociaux) 1200×630
const title = Buffer.from(`<svg xmlns="http://www.w3.org/2000/svg" width="640" height="630">
  <text x="0" y="270" font-family="Arial Black, Arial, sans-serif" font-weight="900" font-size="86" fill="#f2f2f2">SKULL</text>
  <text x="0" y="365" font-family="Arial Black, Arial, sans-serif" font-weight="900" font-size="86" fill="#f2f2f2">DARTS 71</text>
  <text x="4" y="430" font-family="Arial, sans-serif" font-weight="700" font-size="34" fill="#ff6b78">Fléchettes, fuzz et amitié</text>
</svg>`)
await sharp({ create: { width: 1200, height: 630, channels: 4, background: BACKGROUND } })
  .composite([
    { input: await render(470), left: 70, top: 80 },
    { input: title, left: 600, top: 0 },
  ])
  .png({ compressionLevel: 9 })
  .toFile(pub('og-image.png'))

console.log('Icônes générées.')
