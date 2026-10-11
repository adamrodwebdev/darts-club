import { SONG } from '../src/content/song.ts'

/** 355 → « PT5M55S » (durée ISO 8601 attendue par schema.org) */
export function isoDuration(seconds: number): string {
  return `PT${Math.floor(seconds / 60)}M${seconds % 60}S`
}

/**
 * Données structurées de l'hymne (schema.org MusicRecording), lisibles sans
 * JavaScript : titre, durée, fichier audio et paroles complètes.
 * `url` rend un chemin absolu quand l'URL publique du site est connue.
 */
export function songStructuredData(url: (path: string) => string): object {
  const club = { '@type': 'SportsOrganization', name: 'Skull Darts 71', url: url('/') }
  const duration = isoDuration(SONG.duration)
  const lyrics = SONG.sections
    .flatMap((section) => (section.instrumental ? [] : [section.lines.filter((line) => !line.startsWith('(')).join('\n')]))
    .join('\n\n')
  const mp3 = SONG.recordings.find((recording) => recording.type === 'audio/mpeg')

  return {
    '@context': 'https://schema.org',
    '@type': 'MusicRecording',
    '@id': url('/#hymne'),
    url: url('/#hymne'),
    name: SONG.title,
    duration,
    inLanguage: 'fr',
    creator: club,
    ...(mp3 && {
      audio: { '@type': 'AudioObject', name: SONG.title, contentUrl: url(mp3.src), encodingFormat: mp3.type, duration },
    }),
    recordingOf: {
      '@type': 'MusicComposition',
      name: SONG.title,
      inLanguage: 'fr',
      lyricist: club,
      lyrics: { '@type': 'CreativeWork', inLanguage: 'fr', text: lyrics },
    },
  }
}
