// Fausse API de la mesure Lighthouse en CI : l'aperçu (vite preview) n'a pas d'API derrière lui.
// Les pages publiques y reçoivent les réponses de la production pour un visiteur sans compte ;
// sans elle, chaque appel échoue (502) et compte comme une erreur dans la console.
import { createServer } from 'node:http'

const PORT = Number(process.env.API_STUB_PORT ?? 8787)

const upcoming = {
  slug: 'soiree-flechettes-2026-11-14-a1b2c3',
  title: 'Soirée fléchettes',
  description: 'Parties libres, initiation pour les nouveaux.',
  city: 'Bellevesvre',
  startsAt: '2026-11-14T19:00:00+00:00',
  endsAt: '2026-11-14T23:00:00+00:00',
  status: 'prevu',
  cancelReason: null,
  capacity: null,
  going: 4,
  maybe: 2,
  full: false,
  started: false,
  past: false,
  poster: null,
}
const past = { ...upcoming, slug: 'soiree-de-rentree-2026-10-03-d4e5f6', title: 'Soirée de rentrée', startsAt: '2026-10-03T17:30:00+00:00', endsAt: '2026-10-03T21:30:00+00:00', started: true, past: true }

/** Réponses GET, par chemin */
const ROUTES = {
  '/api/public/events': { items: [upcoming], past: [past] },
  '/api/auth/oauth/providers': { providers: [] },
}

createServer((request, response) => {
  const path = new URL(request.url ?? '/', 'http://stub').pathname
  const body = request.method === 'GET' ? ROUTES[path] : undefined
  response.writeHead(body === undefined ? 404 : 200, { 'Content-Type': 'application/json' })
  response.end(JSON.stringify(body ?? { error: `Route absente du bouchon Lighthouse : ${request.method} ${path}` }))
}).listen(PORT, '127.0.0.1', () => {
  console.log(`Bouchon d'API Lighthouse : http://127.0.0.1:${PORT}`)
})
