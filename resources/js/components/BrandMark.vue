<script setup lang="ts">
import { useId } from 'vue';

// Emblème MeriMap : épingle + serveur + orbite + sol cartographique.
// compact = sans le sol (petites tailles : barre latérale, onglet).
withDefaults(defineProps<{ compact?: boolean }>(), { compact: false });

const id = useId();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        :viewBox="compact ? '4 5 58 52' : '0 0 64 64'"
        role="img"
        aria-label="MeriMap"
    >
        <defs>
            <linearGradient :id="`${id}-pin`" x1="0" y1="0" x2="0.6" y2="1">
                <stop offset="0" stop-color="#1D5A9A" />
                <stop offset="0.55" stop-color="#0B2A5B" />
            </linearGradient>
            <linearGradient :id="`${id}-tiles`" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#B7C4D6" />
                <stop offset="1" stop-color="#4E6A93" />
            </linearGradient>
        </defs>
        <template v-if="!compact">
            <path
                d="M3 51.5 25.5 44 61 50 35.5 61.5Z"
                :fill="`url(#${id}-tiles)`"
            />
            <path
                d="M14.5 47.8 48 55.6M33 45.3 18.5 56.5M45.5 47.4 27.5 59.3"
                stroke="#fff"
                stroke-width="1.6"
            />
        </template>
        <path
            d="M31 53.5C25 45 13.5 36.5 13.5 24.5a17.5 17.5 0 0 1 35 0C48.5 36.5 37 45 31 53.5Z"
            :fill="`url(#${id}-pin)`"
        />
        <circle cx="31" cy="24.5" r="10.6" fill="#fff" />
        <g fill="#0B2A5B">
            <rect x="24" y="18.3" width="14" height="3.6" rx="1.1" />
            <rect x="24" y="22.7" width="14" height="3.6" rx="1.1" />
            <rect x="24" y="27.1" width="14" height="3.6" rx="1.1" />
        </g>
        <g fill="#fff">
            <circle cx="35.6" cy="20.1" r="0.75" />
            <circle cx="35.6" cy="24.5" r="0.75" />
            <circle cx="35.6" cy="28.9" r="0.75" />
        </g>
        <path
            d="M7.5 40.5C1.8 37.6 9 31.2 21.5 25.9 33.5 20.8 49.5 15.4 61 13.6 51 16.6 36 22.5 25.5 28 14.5 33.7 7.4 37.5 9.9 40.7Z"
            fill="#EE8A14"
        />
    </svg>
</template>
