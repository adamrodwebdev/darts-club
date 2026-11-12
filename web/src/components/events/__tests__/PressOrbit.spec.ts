import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { parseEventPhoto } from '@/models/ClubEvent'
import { PressClipping } from '@/models/PressClipping'
import { createPreferenceControllers, PREFERENCES } from '@/preferences'
import { MOTION_STORAGE_KEY } from '@/preferences/MotionController'
import { MemoryPreferenceStorage } from '@/preferences/PreferenceStorage'
import PressOrbit from '../PressOrbit.vue'

/** Cinq journaux, le dernier inconnu (« Coupure 5 sur 5 » sans source) */
const OUTLETS = ['La Gazette de la Bresse', 'L’Écho de la Seille', 'Le Courrier du 71', 'La Gazette de la Bresse', null]

function clippings(count: number): PressClipping[] {
  return Array.from({ length: count }, (_, n) =>
    PressClipping.from(
      parseEventPhoto({
        id: `p${n}`, thumbUrl: `/api/event-photos/p${n}/thumb`, largeUrl: `/api/event-photos/p${n}/large`, width: 480, height: 640,
        caption: null, uploader: 'Alice', uploadedAt: '2026-11-04T10:00:00+01:00', hidden: false, hiddenBy: null, mine: false,
        press: { outlet: OUTLETS[n % OUTLETS.length] ?? null, publishedOn: '2026-11-03', title: `Article ${n + 1}`, credit: null },
      }),
    ),
  )
}

/** Le bloc mesuré à `width` px de large (pas de mise en page sous jsdom : ResizeObserver simulé) */
function stubWidth(width: number): void {
  vi.stubGlobal(
    'ResizeObserver',
    class {
      constructor(private readonly callback: ResizeObserverCallback) {}
      observe(): void {
        this.callback([{ contentRect: { width } } as ResizeObserverEntry], this as unknown as ResizeObserver)
      }
      unobserve(): void {}
      disconnect(): void {}
    },
  )
}

let wrapper: VueWrapper | null = null

interface OrbitOptions {
  count?: number
  /** Largeur du bloc, en px (20rem = 320 px : l'anneau a la place) */
  width?: number
  motion?: 'off'
}

async function mountOrbit(options: OrbitOptions = {}): Promise<VueWrapper> {
  stubWidth(options.width ?? 960)
  const storage = new MemoryPreferenceStorage()
  if (options.motion) storage.set(MOTION_STORAGE_KEY, options.motion)
  wrapper = mount(PressOrbit, {
    props: { clippings: clippings(options.count ?? 5), titleId: 'press-title' },
    attachTo: document.body,
    global: { plugins: [createPinia()], provide: { [PREFERENCES as symbol]: createPreferenceControllers(window, storage) } },
  })
  await flushPromises()
  return wrapper
}

function pointer(type: string, clientX: number): PointerEvent {
  return new PointerEvent(type, { bubbles: true, cancelable: true, clientX, pointerId: 1, isPrimary: true, pointerType: 'mouse', button: 0 })
}

const status = (orbit: VueWrapper) => orbit.get('.orbit__status').text()
const announced = (orbit: VueWrapper) => orbit.get('[aria-live="polite"]').text()
const cardButton = (orbit: VueWrapper, index: number) => orbit.findAll<HTMLButtonElement>('.clipping__open')[index]

afterEach(() => {
  wrapper?.unmount()
  wrapper = null
  vi.unstubAllGlobals()
  document.documentElement.dataset.motion = 'on'
})

describe('PressOrbit', () => {
  it('turns to the next and previous clipping with its arrows, and announces it', async () => {
    const orbit = await mountOrbit()
    expect(orbit.get('[role="group"]').attributes('aria-label')).toBe('Coupures de presse')
    expect(status(orbit)).toBe('Coupure 1 sur 5 · La Gazette de la Bresse')

    await orbit.get('button[aria-label="Coupure suivante"]').trigger('click')
    expect(status(orbit)).toBe('Coupure 2 sur 5 · L’Écho de la Seille')
    expect(announced(orbit)).toBe('Coupure 2 sur 5 · L’Écho de la Seille')
    expect(orbit.get('.orbit__ring').attributes('style')).toContain('--turn: -72deg')

    // Avant la première vient la dernière, sans journal connu
    await orbit.get('button[aria-label="Coupure précédente"]').trigger('click')
    await orbit.get('button[aria-label="Coupure précédente"]').trigger('click')
    expect(status(orbit)).toBe('Coupure 5 sur 5')
    expect(orbit.get('.orbit__ring').attributes('style')).toContain('--turn: 72deg')
  })

  it('follows the arrow keys from a card, the focus going with the card in front', async () => {
    const orbit = await mountOrbit()
    cardButton(orbit, 0)?.element.focus()

    await cardButton(orbit, 0)?.trigger('keydown', { key: 'ArrowRight' })
    expect(status(orbit)).toBe('Coupure 2 sur 5 · L’Écho de la Seille')
    expect(document.activeElement).toBe(cardButton(orbit, 1)?.element)

    await cardButton(orbit, 1)?.trigger('keydown', { key: 'End' })
    expect(status(orbit)).toBe('Coupure 5 sur 5')
    await cardButton(orbit, 4)?.trigger('keydown', { key: 'ArrowRight' })
    expect(status(orbit)).toBe('Coupure 1 sur 5 · La Gazette de la Bresse')
    expect(document.activeElement).toBe(cardButton(orbit, 0)?.element)
    // Le focus a parlé : rien de plus à annoncer
    expect(announced(orbit)).toBe('')
  })

  it('announces the keys pressed on its arrows, where the focus stays', async () => {
    const orbit = await mountOrbit()
    const next = orbit.get('button[aria-label="Coupure suivante"]')

    await next.trigger('keydown', { key: 'ArrowLeft' })

    expect(status(orbit)).toBe('Coupure 5 sur 5')
    expect(announced(orbit)).toBe('Coupure 5 sur 5')
  })

  it('brings a card to the front when it takes the focus', async () => {
    const orbit = await mountOrbit()

    cardButton(orbit, 2)?.element.focus()
    await flushPromises()

    expect(status(orbit)).toBe('Coupure 3 sur 5 · Le Courrier du 71')
  })

  it('turns with a drag, settles on a card, and never opens the card under the pointer', async () => {
    const orbit = await mountOrbit()
    const stage = orbit.get('.orbit__stage').element
    const card = cardButton(orbit, 0)?.element
    expect(card).toBeDefined()

    // Un glissé de presque une carte vers la gauche : la suivante vient devant
    card?.dispatchEvent(pointer('pointerdown', 300))
    stage.dispatchEvent(pointer('pointermove', 200))
    await flushPromises()
    expect(orbit.classes()).toContain('orbit--dragging')
    stage.dispatchEvent(pointer('pointermove', 150))
    stage.dispatchEvent(pointer('pointerup', 150))
    card?.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, detail: 1 }))
    await flushPromises()

    expect(status(orbit)).toBe('Coupure 2 sur 5 · L’Écho de la Seille')
    expect(orbit.classes()).not.toContain('orbit--dragging')
    expect(orbit.emitted('open')).toBeUndefined()
  })

  it('goes back to its card after a short drag, and on to the next after a long enough one', async () => {
    const orbit = await mountOrbit()
    const stage = orbit.get('.orbit__stage').element

    stage.dispatchEvent(pointer('pointerdown', 300))
    stage.dispatchEvent(pointer('pointermove', 280))
    stage.dispatchEvent(pointer('pointerup', 280))
    await flushPromises()
    expect(status(orbit)).toBe('Coupure 1 sur 5 · La Gazette de la Bresse')

    stage.dispatchEvent(pointer('pointerdown', 300))
    stage.dispatchEvent(pointer('pointermove', 340))
    stage.dispatchEvent(pointer('pointerup', 340))
    await flushPromises()
    expect(status(orbit)).toBe('Coupure 5 sur 5')
  })

  it('opens the clipping that is clicked and turns it to the front behind the window', async () => {
    const orbit = await mountOrbit()

    await cardButton(orbit, 2)?.trigger('click')

    expect(orbit.emitted('open')).toEqual([[2]])
    expect(status(orbit)).toBe('Coupure 3 sur 5 · Le Courrier du 71')
  })

  const flatCases: [string, OrbitOptions][] = [
    ['motion is off', { motion: 'off' }],
    ['its block is too narrow', { width: 300 }],
    ['it has only two clippings', { count: 2 }],
  ]

  it.each(flatCases)('lies flat in a grid of the same cards when %s', async (_case, options) => {
    const orbit = await mountOrbit(options)

    expect(orbit.classes()).not.toContain('orbit--ring')
    expect(orbit.find('[role="group"]').exists()).toBe(false)
    expect(orbit.find('button[aria-label="Coupure suivante"]').exists()).toBe(false)
    expect(orbit.get('.orbit__ring').attributes('style')).toBeUndefined()
    expect(orbit.findAll('li.clipping')).toHaveLength(options.count ?? 5)
    expect(orbit.get('h2#press-title').text()).toBe('Dans la presse')

    // Les flèches du clavier ne font rien tourner : il n'y a pas d'anneau
    await cardButton(orbit, 0)?.trigger('keydown', { key: 'ArrowRight' })
    expect(orbit.find('.orbit__status').exists()).toBe(false)
    await cardButton(orbit, 1)?.trigger('click')
    expect(orbit.emitted('open')).toEqual([[1]])
  })

  it('describes each card by its details, read after its name', async () => {
    const orbit = await mountOrbit()
    const button = cardButton(orbit, 1)

    expect(button?.attributes('aria-label')).toBe('Agrandir la coupure 2 sur 5')
    const caption = orbit.get(`#${button?.attributes('aria-describedby') ?? ''}`)
    expect(caption.text()).toContain('L’Écho de la Seille')
    expect(caption.text()).toContain('3 novembre 2026')
    expect(caption.text()).toContain('Article 2')
  })
})
