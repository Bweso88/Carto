import type { ImpactState } from '@/types/cmdb';

// Habillage d'un nœud selon son état dans une simulation de panne
export function impactClasses(
    impact: ImpactState,
    simulating: boolean,
): string {
    if (impact === 'down') {
        return 'border-red-500 ring-2 ring-red-500/30 dark:border-red-400';
    }
    if (impact === 'degraded') {
        return 'border-amber-500 ring-2 ring-amber-500/25 dark:border-amber-400';
    }

    return simulating ? 'opacity-40' : '';
}

export const criticalityDot: Record<string, string> = {
    critical: 'bg-red-500',
    high: 'bg-amber-500',
    medium: 'bg-sky-500 dark:bg-cyan-400',
    low: 'bg-slate-400',
};
