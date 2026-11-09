<script setup lang="ts">
import { HardDrive, Network, Server, Unlink } from '@lucide/vue';
import { computed } from 'vue';
import type { HealthStatus } from './storage';

/**
 * Recorrido físico de un archivo: VPS → túnel Tailscale → NAS.
 * Fluye cuando hay conexión; se rompe en rojo cuando el NAS no responde.
 */
const props = defineProps<{
    status: HealthStatus;
    testing?: boolean;
    address: string;
    nasName: string;
}>();

type LinkState = 'flow' | 'idle' | 'broken' | 'steady' | 'scan';

const links = computed<[LinkState, LinkState]>(() => {
    if (props.testing) return ['scan', 'scan'];
    switch (props.status) {
        case 'available':
            return ['flow', 'flow'];
        case 'permission_error':
        case 'write_error':
            return ['steady', 'steady'];
        case 'disconnected':
        case 'error':
            return ['idle', 'broken'];
        default:
            return ['idle', 'idle'];
    }
});

const nasTone = computed(() => {
    if (props.testing) return 'border-brand/50 text-foreground';
    if (props.status === 'available') return 'border-success/50 text-success';
    if (props.status === 'permission_error' || props.status === 'write_error')
        return 'border-warning/60 text-warning';
    if (props.status === 'disconnected' || props.status === 'error')
        return 'border-destructive/50 text-destructive';

    return 'border-border text-muted-foreground';
});

const summary = computed(() => {
    if (props.testing) return 'Probando la ruta del servidor al NAS.';
    switch (props.status) {
        case 'available':
            return 'Ruta completa: el servidor llega al NAS por Tailscale.';
        case 'disconnected':
            return 'Ruta interrumpida: el servidor no alcanza el NAS.';
        case 'permission_error':
        case 'write_error':
            return 'El NAS responde, pero la carpeta tiene un problema de acceso.';
        case 'error':
            return 'Ruta con error: revisa el resultado de la última prueba.';
        default:
            return 'Ruta sin verificar.';
    }
});
</script>

<template>
    <div
        class="rounded-lg border bg-surface-raised px-3 py-4 sm:px-5"
        role="img"
        :aria-label="summary"
    >
        <ol class="flex items-start" aria-hidden="true">
            <li
                class="flex w-16 shrink-0 flex-col items-center gap-1.5 text-center sm:w-20"
            >
                <span
                    class="grid size-10 place-items-center rounded-full border bg-card text-foreground shadow-xs"
                >
                    <Server class="size-4.5" />
                </span>
                <span class="text-[11px] leading-tight font-semibold">VPS</span>
                <span class="text-[10px] leading-tight text-muted-foreground"
                    >MR. INSIGHT</span
                >
            </li>

            <li class="mt-5 min-w-4 flex-1">
                <span class="link" :data-state="links[0]" />
            </li>

            <li
                class="flex w-20 shrink-0 flex-col items-center gap-1.5 text-center sm:w-24"
            >
                <span
                    class="grid size-10 place-items-center rounded-full border bg-card shadow-xs transition-colors duration-500"
                    :class="
                        links[0] === 'flow'
                            ? 'border-success/50 text-success'
                            : 'text-foreground'
                    "
                >
                    <Network class="size-4.5" />
                </span>
                <span class="text-[11px] leading-tight font-semibold"
                    >Tailscale</span
                >
                <span
                    class="text-[10px] leading-tight break-all text-muted-foreground tabular-nums"
                    >{{ address }}</span
                >
            </li>

            <li class="relative mt-5 min-w-4 flex-1">
                <span class="link" :data-state="links[1]" />
                <Transition
                    enter-active-class="transition duration-300 ease-out"
                    enter-from-class="opacity-0 scale-50"
                    leave-active-class="transition duration-150"
                    leave-to-class="opacity-0 scale-50"
                >
                    <span
                        v-if="links[1] === 'broken'"
                        class="absolute top-1/2 left-1/2 grid size-6 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full border border-destructive/40 bg-card text-destructive"
                    >
                        <Unlink class="size-3.5" />
                    </span>
                </Transition>
            </li>

            <li
                class="flex w-16 shrink-0 flex-col items-center gap-1.5 text-center sm:w-20"
            >
                <span
                    class="relative grid size-10 place-items-center rounded-full border bg-card shadow-xs transition-colors duration-500"
                    :class="nasTone"
                >
                    <HardDrive class="size-4.5" />
                    <span
                        v-if="status === 'available' && !testing"
                        class="breathe absolute inset-0 rounded-full ring-2 ring-success/30"
                    />
                </span>
                <span class="text-[11px] leading-tight font-semibold">NAS</span>
                <span
                    class="text-[10px] leading-tight break-all text-muted-foreground"
                    >{{ nasName }}</span
                >
            </li>
        </ol>
    </div>
</template>

<style scoped>
.link {
    position: relative;
    display: block;
    height: 2px;
    margin-inline: 0.25rem;
    border-radius: 999px;
    overflow: hidden;
    transition: background-color 400ms var(--ease-editorial);
}
.link[data-state='idle'] {
    background-image: repeating-linear-gradient(
        90deg,
        var(--color-muted-foreground) 0 2px,
        transparent 2px 7px
    );
    opacity: 0.45;
}
.link[data-state='steady'] {
    background-color: color-mix(in srgb, var(--color-warning) 70%, transparent);
}
.link[data-state='broken'] {
    background-image: repeating-linear-gradient(
        90deg,
        var(--color-destructive) 0 5px,
        transparent 5px 10px
    );
}
.link[data-state='flow'] {
    background-color: color-mix(in srgb, var(--color-success) 22%, transparent);
    background-image: repeating-linear-gradient(
        90deg,
        var(--color-success) 0 6px,
        transparent 6px 14px
    );
    background-size: 14px 2px;
    animation: flow 900ms linear infinite;
}
.link[data-state='scan'] {
    background-color: color-mix(in srgb, var(--color-brand) 20%, transparent);
}
.link[data-state='scan']::after {
    content: '';
    position: absolute;
    inset: 0;
    width: 40%;
    background: linear-gradient(
        90deg,
        transparent,
        var(--color-brand),
        transparent
    );
    animation: scan 1.1s var(--ease-editorial) infinite;
}

.breathe {
    animation: breathe 2.8s ease-in-out infinite;
}
@keyframes breathe {
    0%,
    100% {
        transform: scale(1);
        opacity: 0.9;
    }
    50% {
        transform: scale(1.22);
        opacity: 0;
    }
}
@keyframes flow {
    to {
        background-position-x: 14px;
    }
}
@keyframes scan {
    from {
        transform: translateX(-100%);
    }
    to {
        transform: translateX(250%);
    }
}

@media (prefers-reduced-motion: reduce) {
    .link[data-state='flow'],
    .link[data-state='scan']::after,
    .breathe {
        animation: none;
    }
}
</style>
