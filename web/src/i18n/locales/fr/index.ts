import account from './account.json'
import admin from './admin.json'
import core from './core.json'
import events from './events.json'
import messaging from './messaging.json'
import treasury from './treasury.json'

/** Catalogue français complet, chargé à la demande comme les autres langues (un seul fichier). */
export default { ...core, ...account, ...events, ...messaging, ...treasury, ...admin }
