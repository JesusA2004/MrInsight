<script setup lang="ts">
import {
    TransitionPresets,
    usePreferredReducedMotion,
    useTransition,
} from '@vueuse/core';
import { computed, onMounted, ref, watch } from 'vue';

/** Cifra que cuenta desde cero al montar y se desliza al cambiar. */
const props = withDefaults(
    defineProps<{
        value: number;
        format?: (n: number) => string;
        duration?: number;
        delay?: number;
    }>(),
    {
        format: (n: number) => Math.round(n).toLocaleString('es-MX'),
        duration: 1100,
        delay: 0,
    },
);

const reduced = usePreferredReducedMotion();
const source = ref(0);
const output = useTransition(source, {
    duration: computed(() => (reduced.value === 'reduce' ? 0 : props.duration)),
    transition: TransitionPresets.easeOutExpo,
});

onMounted(() => {
    if (reduced.value === 'reduce' || props.delay <= 0)
        source.value = props.value;
    else setTimeout(() => (source.value = props.value), props.delay);
});
watch(
    () => props.value,
    (v) => (source.value = v),
);

const text = computed(() => props.format(output.value));
</script>

<template>
    <span class="tabular-nums">
        <span aria-hidden="true">{{ text }}</span>
        <span class="sr-only">{{ props.format(props.value) }}</span>
    </span>
</template>
