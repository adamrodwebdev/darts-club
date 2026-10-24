/**
 * Règles d'un nouveau mot de passe, les mêmes que l'API (App\Validator\PasswordPolicy) : affichées
 * pendant la saisie. L'API reste seule juge (elle refuse aussi les mots de passe divulgués).
 */
export const PASSWORD_MIN_LENGTH = 6

export type PasswordRule = 'length' | 'digit' | 'special'

const CHECKS: Readonly<Record<PasswordRule, (password: string) => boolean>> = {
  // Caractères comptés comme l'API (points de code)
  length: (password) => [...password].length >= PASSWORD_MIN_LENGTH,
  digit: (password) => /\p{Nd}/u.test(password),
  // Ni lettre (accents compris) ni chiffre : ponctuation, symbole ou espace
  special: (password) => /[^\p{L}\p{M}\p{Nd}]/u.test(password),
}

export const PASSWORD_RULES = Object.keys(CHECKS) as readonly PasswordRule[]

export function meetsRule(password: string, rule: PasswordRule): boolean {
  return CHECKS[rule](password)
}
