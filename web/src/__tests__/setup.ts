import { config } from '@vue/test-utils'
import { i18n } from '@/i18n'

// Composants montés avec les traductions, comme dans l'application
config.global.plugins.push(i18n)
