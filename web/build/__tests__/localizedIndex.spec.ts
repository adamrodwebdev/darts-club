import { describe, expect, it } from 'vitest'
import { robotsTxt, sitemapXml } from '../localizedIndex'

const SITE = 'https://skulldarts71.fr'

describe('robots.txt and sitemap', () => {
  it('keeps the members and board areas out of the index', () => {
    const robots = robotsTxt(SITE)
    for (const path of ['/api/', '/compte', '/admin', '/association', '/tresorerie', '/messages', '/notifications', '/infos']) {
      expect(robots).toContain(`Disallow: ${path}\n`)
    }
    // Pages d'une soirée et écrans du bureau, sous /evenements/
    expect(robots).toContain('Disallow: /evenements/\n')
    expect(robots).toContain(`Sitemap: ${SITE}/sitemap.xml`)
  })

  // Signalé le 2 novembre 2026 : « Disallow: /evenements » bloquait aussi la liste publique (SEO 66)
  it('lets search engines index the public events list', () => {
    expect(robotsTxt(SITE)).not.toMatch(/^Disallow: \/evenements$/m)
    expect(sitemapXml(SITE, ['/', '/en'])).toContain(`<loc>${SITE}/evenements</loc>`)
  })

  it('points to no sitemap without a public address', () => {
    expect(robotsTxt('')).not.toContain('Sitemap:')
  })
})
