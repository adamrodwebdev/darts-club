import { mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import type { EventPhoto } from '@/models/ClubEvent'
import { GalleryVideo, galleryItems } from '@/models/Gallery'
import MediaSlide from '../MediaSlide.vue'

const photo: EventPhoto = {
  id: 'p1', thumbUrl: '/api/event-photos/p1/thumb', largeUrl: '/api/event-photos/p1/large', width: 480, height: 320, caption: null,
  uploader: 'Bob', uploadedAt: new Date('2026-10-10T19:00:00Z'), mine: false, hidden: false, hiddenBy: null, press: null,
}
const clip = GalleryVideo.parse({
  id: 'v1', status: 'prete', fileName: 'IMG_2041.MOV', size: 12, received: 12, durationMs: 42_000, width: 720, height: 1280,
  videoUrl: '/api/event-videos/v1/file', av1Url: '/api/event-videos/v1/file/av1', posterUrl: '/api/event-videos/v1/poster',
  failure: null, hidden: false, hiddenBy: null, uploader: 'Présidente', uploadedAt: '2026-10-10T18:30:00Z', mine: true,
})
// Dans l'ordre d'arrivée : la vidéo (18 h 30) puis la photo (19 h)
const [videoItem, photoItem] = galleryItems([photo], [clip])

function slide(item: typeof photoItem, overrides: { active?: boolean; near?: boolean } = {}) {
  return mount(MediaSlide, { props: { item: item!, description: 'Fléchettes au bar', index: 0, total: 2, sizes: '100vw', active: true, near: true, ...overrides } })
}

afterEach(() => {
  vi.restoreAllMocks()
})

describe('MediaSlide', () => {
  it('asks for the large photo only once it comes near the screen, then keeps it', async () => {
    const wrapper = slide(photoItem, { near: false })
    expect(wrapper.find('img').exists()).toBe(false)

    await wrapper.setProps({ near: true })
    const media = wrapper.get('img[alt="Fléchettes au bar"]')
    expect(media.attributes('src')).toBe('/api/event-photos/p1/large')
    expect(media.attributes('srcset')).toBe('/api/event-photos/p1/thumb 480w, /api/event-photos/p1/large 1600w')

    await wrapper.setProps({ near: false })
    expect(wrapper.find('img[alt="Fléchettes au bar"]').exists()).toBe(true)
  })

  it('downloads nothing of a video before Play, then hands over to the player controls', async () => {
    const play = vi.spyOn(HTMLMediaElement.prototype, 'play').mockResolvedValue(undefined)
    const wrapper = slide(videoItem)
    expect(wrapper.get('video').attributes('preload')).toBe('none')
    expect(wrapper.get('video').attributes('controls')).toBeUndefined()

    await wrapper.get('button[aria-label="Lire la vidéo 1 sur 2, 0:42"]').trigger('click')

    expect(play).toHaveBeenCalledTimes(1)
    expect(wrapper.get('video').attributes('controls')).toBeDefined()
    expect(wrapper.find('button').exists()).toBe(false)
  })

  it('stops a video once it is no longer the one on screen', async () => {
    const pause = vi.spyOn(HTMLMediaElement.prototype, 'pause').mockImplementation(() => undefined)
    const wrapper = slide(videoItem)

    await wrapper.setProps({ active: false })

    expect(pause).toHaveBeenCalledTimes(1)
  })
})
