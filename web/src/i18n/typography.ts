/**
 * Typographie française : espace insécable avant « : ; ! ? % » et à l'intérieur des guillemets
 * « », pour qu'aucune ligne ne commence par un signe orphelin. Appliquée au texte affiché
 * (traductions, messages de l'API, paroles), jamais aux catalogues.
 */
const ORPHAN_SPACES = / (?=[:;!?»%])|(?<=«) /g

export function frenchTypography(text: string): string {
  return text.replace(ORPHAN_SPACES, ' ')
}

const unchanged = (text: string): string => text

/** Mise en forme du texte d'une langue (« fr », « fr-FR »…) : typographie française, sinon rien */
export function typographyFor(language: string): (text: string) => string {
  return language.toLowerCase().startsWith('fr') ? frenchTypography : unchanged
}
