import account from './account.json'
import admin from './admin.json'
import core from './core.json'
import events from './events.json'
import messaging from './messaging.json'
import treasury from './treasury.json'

/** Catalogue anglais complet : un seul fichier chargé à la demande (pas de cascade de requêtes). */
export default { ...core, ...account, ...events, ...messaging, ...treasury, ...admin }
