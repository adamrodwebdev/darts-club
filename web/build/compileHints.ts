import type { Plugin } from 'vite'

/** Indication de compilation de V8 (Chrome 136+) : toutes les fonctions du fichier servent au chargement */
export const COMPILE_HINT = '//# allFunctionsCalledOnLoad\n'

/**
 * Chaque fichier JavaScript est compilé en entier pendant son téléchargement, hors du fil principal.
 * Sans l'indication, V8 compile chaque fonction à son premier appel, dans les tâches du démarrage
 * (évaluation des modules, services et routeur, en-tête, rendu de la page). Tous les fichiers la
 * portent : chacun est chargé au démarrage ou à l'ouverture de sa page, dont il sert aussitôt la
 * plupart des fonctions. Les autres moteurs ignorent ce commentaire.
 *
 * Ajoutée en dernier : après la minification, qui retirerait le commentaire, et après la table des
 * préchargements que Vite place en tête de certains fichiers, pour en couvrir toutes les fonctions.
 * `npm run budget` vérifie que chaque script construit commence par elle.
 */
export function compileHints(): Plugin {
  return {
    name: 'compile-hints',
    apply: 'build',
    generateBundle: {
      order: 'post',
      handler(_, bundle) {
        for (const file of Object.values(bundle)) {
          if (file.type === 'chunk') file.code = COMPILE_HINT + file.code
        }
      },
    },
  }
}
