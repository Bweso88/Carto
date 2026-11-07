import type { EnumValue } from '@/types/cmdb';
import type { Tone } from './ToneBadge.vue';

// Correspondance valeurs métier -> couleur de badge
const map: Record<string, Tone> = {
    // criticité
    critical: 'danger',
    high: 'warning',
    medium: 'info',
    low: 'neutral',
    // statut serveur
    active: 'success',
    maintenance: 'warning',
    decommissioned: 'neutral',
    planned: 'info',
    // cycle de vie applicatif
    production: 'success',
    project: 'info',
    retiring: 'warning',
    retired: 'neutral',
    // classification
    public: 'neutral',
    internal: 'info',
    confidential: 'warning',
    secret: 'danger',
    // type d'application
    business: 'info',
    technical: 'accent',
    saas: 'neutral',
    database: 'accent',
};

export function toneOf(value: EnumValue | string | null | undefined): Tone {
    const key = typeof value === 'string' ? value : value?.value;

    return (key && map[key]) || 'neutral';
}
