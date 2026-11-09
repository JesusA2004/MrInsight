<script setup lang="ts">
import { CheckCircle2, CircleHelp, XCircle } from '@lucide/vue';
import { computed } from 'vue';
import type { CheckKey, Checks } from './storage';

/**
 * Verificaciones de la última prueba. Durante la prueba muestra un barrido;
 * al llegar un resultado nuevo, cada verificación aparece una tras otra.
 */
const props = defineProps<{
    checks: Checks | null;
    items: { key: CheckKey; label: string }[];
    testing?: boolean;
    /** Cambia con cada resultado para reproducir la revelación escalonada. */
    revision?: string | null;
}>();

const rows = computed(() =>
    props.items.map((item) => {
        const value = props.checks ? props.checks[item.key] : null;

        return {
            ...item,
            value,
            state: value === true ? 'ok' : value === false ? 'fail' : 'unknown',
        } as const;
    }),
);

const STATE_TEXT = {
    ok: 'Correcto',
    fail: 'Falló',
    unknown: 'Sin datos',
} as const;
</script>

<template>
    <div class="relative overflow-hidden rounded-lg border">
        <div
            v-if="testing"
            class="scan pointer-events-none absolute inset-0"
            aria-hidden="true"
        />

        <TransitionGroup
            :key="testing ? 'testing' : (revision ?? 'none')"
            tag="ul"
            appear
            class="divide-y"
            enter-active-class="transition duration-500 ease-[var(--ease-editorial)]"
            enter-from-class="opacity-0 -translate-x-2"
        >
            <li
                v-for="(row, i) in rows"
                :key="row.key"
                class="flex items-center gap-3 px-3 py-2.5 text-sm"
                :style="{ transitionDelay: testing ? '0ms' : `${i * 110}ms` }"
            >
                <span
                    class="grid size-6 shrink-0 place-items-center rounded-full"
                    :class="
                        testing
                            ? 'bg-muted text-muted-foreground'
                            : row.state === 'ok'
                              ? 'bg-success/12 text-success'
                              : row.state === 'fail'
                                ? 'bg-destructive/10 text-destructive'
                                : 'bg-muted text-muted-foreground'
                    "
                    aria-hidden="true"
                >
                    <span
                        v-if="testing"
                        class="size-1.5 rounded-full bg-current motion-safe:animate-pulse"
                    />
                    <CheckCircle2
                        v-else-if="row.state === 'ok'"
                        class="size-3.5"
                    />
                    <XCircle
                        v-else-if="row.state === 'fail'"
                        class="size-3.5"
                    />
                    <CircleHelp v-else class="size-3.5" />
                </span>
                <span class="min-w-0 flex-1 break-words">{{ row.label }}</span>
                <span
                    class="shrink-0 text-xs font-medium"
                    :class="
                        testing
                            ? 'text-muted-foreground'
                            : row.state === 'ok'
                              ? 'text-success'
                              : row.state === 'fail'
                                ? 'text-destructive'
                                : 'text-muted-foreground'
                    "
                >
                    {{ testing ? 'Verificando…' : STATE_TEXT[row.state] }}
                </span>
            </li>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.scan {
    background: linear-gradient(
        180deg,
        transparent 0%,
        color-mix(in srgb, var(--color-brand) 16%, transparent) 45%,
        color-mix(in srgb, var(--color-brand) 30%, transparent) 50%,
        transparent 55%
    );
    background-size: 100% 220%;
    animation: scan 1.4s var(--ease-editorial) infinite;
}
@keyframes scan {
    from {
        background-position: 0 120%;
    }
    to {
        background-position: 0 -120%;
    }
}
@media (prefers-reduced-motion: reduce) {
    .scan {
        animation: none;
        background: color-mix(in srgb, var(--color-brand) 8%, transparent);
    }
}
</style>
