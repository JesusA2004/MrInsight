<script setup lang="ts">
import {
    TransitionPresets,
    usePreferredReducedMotion,
    useTransition,
} from '@vueuse/core';
import { computed, onMounted, ref, watch } from 'vue';
import { formatBytes } from './storage';
import type { StorageSpace } from './storage';

/**
 * Medidor de capacidad en arco de 270°: el anillo gris es todo lo ocupado
 * en el disco; el dorado, lo que ocupa la biblioteca. Se llena al montar.
 */
const props = withDefaults(
    defineProps<{
        space: StorageSpace | null;
        libraryBytes: number;
        label: string;
        delay?: number;
    }>(),
    { delay: 0 },
);

const R = 52;
const C = 2 * Math.PI * R;
const ARC = C * 0.75;

const usedPct = computed(() => {
    const s = props.space;
    if (!s || s.totalBytes <= 0) return 0;
    const used = s.usedBytes > 0 ? s.usedBytes : s.totalBytes - s.freeBytes;

    return Math.min(1, Math.max(0, used / s.totalBytes));
});
const libraryPct = computed(() => {
    const s = props.space;
    if (!s || s.totalBytes <= 0) return 0;
    const pct = props.libraryBytes / s.totalBytes;

    // Un hilo visible aunque la biblioteca sea diminuta frente al disco.
    return Math.min(
        usedPct.value,
        props.libraryBytes > 0 ? Math.max(pct, 0.006) : 0,
    );
});

const reduced = usePreferredReducedMotion();
const target = ref<[number, number]>([0, 0]);
const animated = useTransition(target, {
    duration: computed(() => (reduced.value === 'reduce' ? 0 : 1400)),
    transition: TransitionPresets.easeOutCubic,
});

function sync() {
    target.value = [usedPct.value, libraryPct.value];
}
onMounted(() => {
    if (reduced.value === 'reduce' || props.delay <= 0) sync();
    else setTimeout(sync, props.delay);
});
watch([usedPct, libraryPct], sync);

const usedDash = computed(() => `${ARC * animated.value[0]} ${C}`);
const libDash = computed(() => `${ARC * animated.value[1]} ${C}`);
const percentText = computed(() => `${Math.round(animated.value[0] * 100)}%`);
const danger = computed(() => usedPct.value >= 0.9);
</script>

<template>
    <figure
        class="flex flex-col items-center gap-3 sm:flex-row sm:items-center sm:gap-5"
    >
        <div
            class="relative size-36 shrink-0"
            role="meter"
            :aria-label="label"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-valuenow="space ? Math.round(usedPct * 100) : undefined"
            :aria-valuetext="
                space
                    ? `${Math.round(usedPct * 100)} % ocupado, ${formatBytes(space.freeBytes)} libres`
                    : 'Sin datos'
            "
        >
            <svg
                viewBox="0 0 120 120"
                class="size-full -rotate-[225deg]"
                aria-hidden="true"
            >
                <circle
                    cx="60"
                    cy="60"
                    :r="R"
                    fill="none"
                    stroke-width="9"
                    stroke-linecap="round"
                    :class="
                        space ? 'stroke-muted' : 'stroke-muted-foreground/20'
                    "
                    :stroke-dasharray="`${ARC} ${C}`"
                />
                <template v-if="space">
                    <circle
                        cx="60"
                        cy="60"
                        :r="R"
                        fill="none"
                        stroke-width="9"
                        stroke-linecap="round"
                        :class="
                            danger
                                ? 'stroke-destructive/45'
                                : 'stroke-foreground/22'
                        "
                        :stroke-dasharray="usedDash"
                    />
                    <circle
                        cx="60"
                        cy="60"
                        :r="R"
                        fill="none"
                        stroke-width="9"
                        stroke-linecap="round"
                        class="stroke-brand drop-shadow-[0_0_6px_color-mix(in_srgb,var(--brand)_55%,transparent)]"
                        :stroke-dasharray="libDash"
                    />
                </template>
            </svg>
            <div class="absolute inset-0 grid place-items-center text-center">
                <div v-if="space">
                    <p
                        class="font-display text-3xl leading-none font-semibold tabular-nums"
                        :class="danger && 'text-destructive'"
                    >
                        {{ percentText }}
                    </p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        ocupado
                    </p>
                </div>
                <div v-else class="px-6">
                    <p class="text-xs font-semibold text-muted-foreground">
                        Sin datos
                    </p>
                </div>
            </div>
        </div>

        <figcaption class="w-full min-w-0 flex-1">
            <slot name="caption" />
            <dl
                v-if="space"
                class="grid grid-cols-3 gap-2 text-left sm:grid-cols-1 sm:gap-1.5"
            >
                <div
                    class="flex flex-col sm:flex-row sm:items-baseline sm:justify-between sm:gap-3"
                >
                    <dt class="text-xs text-muted-foreground">Total</dt>
                    <dd class="text-sm font-semibold tabular-nums">
                        {{ formatBytes(space.totalBytes) }}
                    </dd>
                </div>
                <div
                    class="flex flex-col sm:flex-row sm:items-baseline sm:justify-between sm:gap-3"
                >
                    <dt class="text-xs text-muted-foreground">Disponible</dt>
                    <dd class="text-sm font-semibold text-success tabular-nums">
                        {{ formatBytes(space.freeBytes) }}
                    </dd>
                </div>
                <div
                    class="flex flex-col sm:flex-row sm:items-baseline sm:justify-between sm:gap-3"
                >
                    <dt class="text-xs text-muted-foreground">Usado</dt>
                    <dd class="text-sm font-semibold tabular-nums">
                        {{
                            formatBytes(
                                space.usedBytes > 0
                                    ? space.usedBytes
                                    : space.totalBytes - space.freeBytes,
                            )
                        }}
                    </dd>
                </div>
            </dl>
            <slot v-else name="empty" />
            <ul
                v-if="space"
                class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-muted-foreground"
                aria-hidden="true"
            >
                <li class="inline-flex items-center gap-1.5">
                    <span class="size-2 rounded-full bg-brand" /> Biblioteca
                </li>
                <li class="inline-flex items-center gap-1.5">
                    <span class="size-2 rounded-full bg-foreground/25" /> Otros
                    datos
                </li>
            </ul>
        </figcaption>
    </figure>
</template>
