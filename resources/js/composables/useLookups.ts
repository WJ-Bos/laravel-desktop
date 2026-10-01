import { shallowRef } from 'vue';
import { api } from '@/lib/api';
import type { Lookups, NetworkHeadend } from '@/types/erp';

const lookups = shallowRef<Lookups | null>(null);
const network = shallowRef<NetworkHeadend[] | null>(null);
let loadingLookups: Promise<Lookups> | null = null;
let loadingNetwork: Promise<NetworkHeadend[]> | null = null;

/** Option lists shared by every form and toolbar, fetched once. */
export function useLookups() {
    if (!lookups.value && !loadingLookups) {
        loadingLookups = api
            .get<Lookups>('/api/lookups')
            .then((data) => (lookups.value = data))
            .catch((error) => {
                loadingLookups = null;
                throw error;
            });
    }

    return {
        lookups,
        refresh: async () => {
            lookups.value = await api.get<Lookups>('/api/lookups');
        },
    };
}

/** The headend → gateway → meter tree for the navigator, fetched once and refreshable. */
export function useNetwork() {
    async function refresh(): Promise<void> {
        const { data } = await api.get<{ data: NetworkHeadend[] }>(
            '/api/network',
        );
        network.value = data;
    }

    if (!network.value && !loadingNetwork) {
        loadingNetwork = refresh()
            .then(() => network.value ?? [])
            .catch((error) => {
                loadingNetwork = null;
                throw error;
            });
    }

    return { network, refresh };
}
