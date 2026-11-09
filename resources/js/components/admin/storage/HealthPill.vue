<script setup lang="ts">
import { computed } from 'vue';
import { STATUS, TONE_DOT, TONE_SOFT } from './storage';
import type { HealthStatus } from './storage';

/** Estado de salud con punto "vivo": late en verde cuando hay conexión, en rojo cuando se perdió. */
const props = defineProps<{ status: HealthStatus; testing?: boolean }>();

const meta = computed(() => STATUS[props.status]);
const live = computed(
    () =>
        !props.testing &&
        (props.status === 'available' || meta.value.tone === 'destructive'),
);
</script>

<template>
    <Transition
        mode="out-in"
        enter-active-class="transition duration-300 ease-[var(--ease-editorial)]"
        enter-from-class="opacity-0 -translate-y-1 scale-95"
        leave-active-class="transition duration-150 ease-in"
        leave-to-class="opacity-0 translate-y-1 scale-95"
    >
        <span
            v-if="testing"
            key="testing"
            class="inline-flex h-7 items-center gap-2 rounded-full bg-brand/15 px-3 text-xs font-semibold text-foreground"
        >
            <span
                class="relative grid size-2 place-items-center"
                aria-hidden="true"
            >
                <span
                    class="absolute size-2 rounded-full bg-brand motion-safe:animate-ping"
                />
                <span class="relative size-2 rounded-full bg-brand" />
            </span>
            Probando…
        </span>
        <span
            v-else
            :key="status"
            class="inline-flex h-7 items-center gap-2 rounded-full px-3 text-xs font-semibold whitespace-nowrap"
            :class="TONE_SOFT[meta.tone]"
        >
            <span
                class="relative grid size-2 place-items-center"
                aria-hidden="true"
            >
                <span
                    v-if="live"
                    class="absolute size-2 rounded-full motion-safe:animate-ping"
                    :class="[
                        TONE_DOT[meta.tone],
                        status === 'available'
                            ? '[animation-duration:2.4s]'
                            : '[animation-duration:1.1s]',
                    ]"
                />
                <span
                    class="relative size-2 rounded-full"
                    :class="TONE_DOT[meta.tone]"
                />
            </span>
            <component :is="meta.icon" class="size-3.5" aria-hidden="true" />
            {{ meta.label }}
        </span>
    </Transition>
</template>
