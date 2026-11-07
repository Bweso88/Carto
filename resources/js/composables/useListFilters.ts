import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { reactive, watch } from 'vue';

/**
 * Filtres de liste synchronisés avec l'URL (?search=…&kind=…).
 * Chaque changement recharge la page Inertia en conservant l'état ; la recherche est temporisée.
 */
export function useListFilters<
    T extends Record<string, string | null | undefined>,
>(url: string, initial: T) {
    const filters = reactive({ ...initial }) as T;

    const apply = () => {
        const query = Object.fromEntries(
            Object.entries(filters).filter(
                ([, v]) => v !== null && v !== undefined && v !== '',
            ),
        );
        router.get(url, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };
    const debounced = useDebounceFn(apply, 300);

    watch(
        () => ({ ...filters }),
        (now, before) => (now.search !== before.search ? debounced() : apply()),
    );

    const reset = () => {
        for (const key of Object.keys(filters)) {
            (filters as Record<string, unknown>)[key] = null;
        }
    };

    const active = () =>
        Object.entries(filters).some(([k, v]) => k !== 'sort' && v);

    return { filters, reset, active };
}
