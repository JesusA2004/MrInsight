<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Files,
    HardDrive,
    KeyRound,
    Loader2,
    PlugZap,
    Power,
    Server,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AnimatedNumber from '@/components/admin/storage/AnimatedNumber.vue';
import CapacityGauge from '@/components/admin/storage/CapacityGauge.vue';
import ConnectionPath from '@/components/admin/storage/ConnectionPath.vue';
import HealthChecks from '@/components/admin/storage/HealthChecks.vue';
import HealthPill from '@/components/admin/storage/HealthPill.vue';
import {
    formatBytes,
    formatDate,
    formatRelative,
    normalizeChecks,
    normalizeStatus,
    STATUS,
} from '@/components/admin/storage/storage';
import type {
    CheckKey,
    StorageProvider,
} from '@/components/admin/storage/storage';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import almacenamiento from '@/routes/admin/almacenamiento';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{ providers: StorageProvider[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Almacenamiento', href: almacenamiento.index() },
        ] as BreadcrumbItem[],
    },
});

const TOUCH = 'pointer-coarse:h-11';
const PRESS =
    'transition-[transform,background-color,box-shadow] duration-150 motion-safe:active:scale-[0.97]';

/** Valores reales del montaje; se usan si el servidor no envía `details`. */
const NAS_FALLBACK = {
    name: 'SynologyExtra',
    address: '100.67.89.20',
    protocol: 'SMB/CIFS 3.0',
    share: 'MrInsight',
    mountPath: '/mnt/mrinsight-storage',
};

const local = computed(
    () => props.providers.find((p) => p.key === 'local') ?? null,
);
const synology = computed(
    () => props.providers.find((p) => p.key === 'synology') ?? null,
);
const nas = computed(() => ({
    ...NAS_FALLBACK,
    ...synology.value?.details,
}));

const activeProvider = computed(
    () => props.providers.find((p) => p.isActive) ?? null,
);
const totalFiles = computed(() =>
    props.providers.reduce((sum, p) => sum + p.filesCount, 0),
);
const totalBytes = computed(() =>
    props.providers.reduce((sum, p) => sum + p.filesBytes, 0),
);

/** Reparto de la biblioteca entre proveedores (barra apilada del resumen). */
const distribution = computed(() =>
    props.providers.map((p) => ({
        id: p.id,
        label: p.label,
        key: p.key,
        bytes: p.filesBytes,
        pct: totalBytes.value > 0 ? (p.filesBytes / totalBytes.value) * 100 : 0,
    })),
);

const LOCAL_CHECKS: { key: CheckKey; label: string }[] = [
    { key: 'write', label: 'Escritura' },
    { key: 'read', label: 'Lectura' },
    { key: 'delete', label: 'Eliminación' },
];
const nasChecks = computed<{ key: CheckKey; label: string }[]>(() => [
    { key: 'mounted', label: 'Montaje detectado' },
    {
        key: 'source',
        label: `Origen //${nas.value.address}/${nas.value.share}`,
    },
    { key: 'write', label: 'Escritura' },
    { key: 'read', label: 'Lectura' },
    { key: 'delete', label: 'Eliminación' },
]);

function statusOf(provider: StorageProvider) {
    return normalizeStatus(provider.status ?? provider.lastTestResult?.status);
}

function activationBlocker(provider: StorageProvider): string {
    const status = statusOf(provider);
    if (status === 'untested') return 'Prueba la conexión antes de activarlo.';

    return `Solo se puede activar cuando está «Disponible». Estado actual: ${STATUS[status].label.toLowerCase()}.`;
}

// ----------------------------------------------------------------- Acciones

const testing = ref<number | null>(null);
function testProvider(provider: StorageProvider) {
    if (testing.value !== null) return;
    testing.value = provider.id;
    router.post(
        almacenamiento.probar(provider.id).url,
        {},
        {
            preserveScroll: true,
            onError: () =>
                toast.error('No se pudo ejecutar la prueba. Intenta de nuevo.'),
            onFinish: () => (testing.value = null),
        },
    );
}

const pendingActivation = ref<StorageProvider | null>(null);
const activating = ref<number | null>(null);
function confirmActivation() {
    const provider = pendingActivation.value;
    if (!provider) return;
    activating.value = provider.id;
    router.post(
        almacenamiento.activar(provider.id).url,
        {},
        {
            preserveScroll: true,
            onError: () => toast.error('No se pudo activar el proveedor.'),
            onFinish: () => {
                activating.value = null;
                pendingActivation.value = null;
            },
        },
    );
}

/** Entrada escalonada: un solo momento orquestado al cargar la página. */
function enter(step: number) {
    return { animationDelay: `${step * 90}ms` };
}
const ENTER =
    'motion-safe:animate-in motion-safe:fade-in-0 motion-safe:slide-in-from-bottom-3 motion-safe:duration-700 [animation-fill-mode:backwards]';
</script>

<template>
    <Head title="Almacenamiento" />

    <TooltipProvider :delay-duration="200">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
            <AdminPageHeader
                eyebrow="Sistema"
                title="Almacenamiento"
                description="Dónde se guardan las fotografías y videos de la biblioteca. Las subidas nuevas van al proveedor activo; los archivos existentes se quedan donde están."
            />

            <!-- Resumen -->
            <section
                :class="cn('overflow-hidden rounded-xl border bg-card', ENTER)"
                :style="enter(0)"
                aria-label="Resumen de almacenamiento"
            >
                <div
                    class="grid divide-y sm:grid-cols-[1.3fr_1fr_1fr] sm:divide-x sm:divide-y-0"
                >
                    <div class="flex flex-col gap-3 p-5">
                        <span class="text-xs font-medium text-muted-foreground"
                            >Proveedor activo</span
                        >
                        <Transition
                            mode="out-in"
                            enter-active-class="transition duration-400 ease-[var(--ease-editorial)]"
                            enter-from-class="opacity-0 translate-y-2"
                            leave-active-class="transition duration-150"
                            leave-to-class="opacity-0 -translate-y-2"
                        >
                            <div
                                :key="activeProvider?.id ?? 'none'"
                                class="flex flex-wrap items-center gap-3"
                            >
                                <span
                                    class="active-chip inline-flex items-center gap-2.5 rounded-full py-1.5 pr-3.5 pl-2"
                                >
                                    <span
                                        class="grid size-7 place-items-center rounded-full bg-brand/15 text-brand"
                                    >
                                        <component
                                            :is="
                                                activeProvider?.key ===
                                                'synology'
                                                    ? Server
                                                    : HardDrive
                                            "
                                            class="size-3.5"
                                        />
                                    </span>
                                    <span
                                        class="font-display text-xl leading-none font-semibold"
                                        >{{
                                            activeProvider?.label ?? 'Ninguno'
                                        }}</span
                                    >
                                </span>
                            </div>
                        </Transition>
                        <p class="text-xs text-muted-foreground">
                            Recibe las subidas nuevas.
                        </p>
                    </div>
                    <div class="flex flex-col gap-3 p-5">
                        <span
                            class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground"
                            ><Files class="size-3.5" /> Archivos en la
                            biblioteca</span
                        >
                        <p
                            class="font-display text-3xl leading-none font-semibold"
                        >
                            <AnimatedNumber :value="totalFiles" :delay="150" />
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Fotografías y videos de todos los proveedores.
                        </p>
                    </div>
                    <div class="flex flex-col gap-3 p-5">
                        <span
                            class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground"
                            ><HardDrive class="size-3.5" /> Espacio de la
                            biblioteca</span
                        >
                        <p
                            class="font-display text-3xl leading-none font-semibold"
                        >
                            <AnimatedNumber
                                :value="totalBytes"
                                :format="formatBytes"
                                :delay="250"
                            />
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Suma de lo guardado en cada proveedor.
                        </p>
                    </div>
                </div>

                <div v-if="totalBytes > 0" class="border-t px-5 py-3">
                    <div
                        class="flex h-1.5 w-full gap-0.5 overflow-hidden rounded-full bg-muted"
                        role="img"
                        :aria-label="
                            distribution
                                .map(
                                    (d) => `${d.label}: ${Math.round(d.pct)} %`,
                                )
                                .join(', ')
                        "
                    >
                        <span
                            v-for="(d, i) in distribution"
                            :key="d.id"
                            class="grow-bar h-full rounded-full"
                            :class="
                                d.key === 'synology'
                                    ? 'bg-brand'
                                    : 'bg-foreground/70'
                            "
                            :style="{
                                width: `${d.pct}%`,
                                animationDelay: `${300 + i * 120}ms`,
                            }"
                        />
                    </div>
                    <ul
                        class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-[11px] text-muted-foreground"
                    >
                        <li
                            v-for="d in distribution"
                            :key="d.id"
                            class="inline-flex items-center gap-1.5"
                        >
                            <span
                                class="size-2 rounded-full"
                                :class="
                                    d.key === 'synology'
                                        ? 'bg-brand'
                                        : 'bg-foreground/70'
                                "
                            />
                            {{ d.label }}
                            <span class="tabular-nums">{{
                                formatBytes(d.bytes)
                            }}</span>
                        </li>
                    </ul>
                </div>
            </section>

            <div class="grid items-start gap-5 lg:grid-cols-12">
                <!-- Local -->
                <article
                    v-if="local"
                    :class="
                        cn(
                            'provider-card flex flex-col gap-5 rounded-xl border bg-card p-5 lg:col-span-5',
                            ENTER,
                            local.isActive && 'is-active',
                        )
                    "
                    :style="enter(1)"
                    :aria-busy="testing === local.id"
                >
                    <header class="flex items-start gap-3">
                        <span
                            class="grid size-11 shrink-0 place-items-center rounded-xl"
                            :class="
                                local.isActive
                                    ? 'bg-brand/15 text-brand'
                                    : 'bg-muted text-muted-foreground'
                            "
                        >
                            <HardDrive class="size-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2
                                    class="font-display text-lg leading-tight font-semibold"
                                >
                                    {{ local.label }}
                                </h2>
                                <span
                                    v-if="local.isActive"
                                    class="active-chip inline-flex h-6 items-center rounded-full px-2.5 text-[11px] font-semibold"
                                    >Activo</span
                                >
                                <span
                                    v-else
                                    class="inline-flex h-6 items-center rounded-full bg-muted px-2.5 text-[11px] font-semibold text-muted-foreground"
                                    >Inactivo</span
                                >
                            </div>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Disco del mismo servidor que el sitio. Es el
                                respaldo siempre disponible.
                            </p>
                        </div>
                    </header>

                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                        aria-live="polite"
                    >
                        <HealthPill
                            :status="statusOf(local)"
                            :testing="testing === local.id"
                        />
                        <span
                            class="text-xs text-muted-foreground"
                            :title="formatDate(local.lastTestedAt)"
                            >{{ formatRelative(local.lastTestedAt) }}</span
                        >
                    </div>

                    <CapacityGauge
                        :space="local.space"
                        :library-bytes="local.filesBytes"
                        label="Capacidad del VPS"
                        :delay="250"
                    >
                        <template #caption>
                            <p class="mb-2 text-sm font-semibold">
                                Capacidad del VPS
                            </p>
                        </template>
                        <template #empty>
                            <p class="text-sm text-muted-foreground">
                                El servidor no reportó la capacidad del disco.
                            </p>
                        </template>
                    </CapacityGauge>

                    <dl class="grid grid-cols-2 gap-3">
                        <div class="rounded-lg bg-surface-raised p-3">
                            <dt class="text-xs text-muted-foreground">
                                Archivos guardados
                            </dt>
                            <dd
                                class="mt-1 font-display text-2xl leading-none font-semibold"
                            >
                                <AnimatedNumber
                                    :value="local.filesCount"
                                    :delay="300"
                                />
                            </dd>
                        </div>
                        <div class="rounded-lg bg-surface-raised p-3">
                            <dt class="text-xs text-muted-foreground">
                                Ocupa la biblioteca
                            </dt>
                            <dd
                                class="mt-1 font-display text-2xl leading-none font-semibold"
                            >
                                <AnimatedNumber
                                    :value="local.filesBytes"
                                    :format="formatBytes"
                                    :delay="300"
                                />
                            </dd>
                        </div>
                    </dl>

                    <HealthChecks
                        v-if="
                            normalizeChecks(local.lastTestResult) ||
                            testing === local.id
                        "
                        :checks="normalizeChecks(local.lastTestResult)"
                        :items="LOCAL_CHECKS"
                        :testing="testing === local.id"
                        :revision="local.lastTestResult?.checkedAt"
                    />

                    <Transition
                        mode="out-in"
                        enter-active-class="transition duration-300"
                        enter-from-class="opacity-0"
                        leave-active-class="transition duration-150"
                        leave-to-class="opacity-0"
                    >
                        <p
                            v-if="local.lastTestResult?.message"
                            :key="local.lastTestResult.checkedAt"
                            class="text-sm text-muted-foreground"
                            aria-live="polite"
                        >
                            {{ local.lastTestResult.message }}
                        </p>
                    </Transition>

                    <footer
                        class="mt-auto flex flex-wrap items-center gap-2 border-t pt-4"
                    >
                        <Button
                            variant="outline"
                            :class="cn(TOUCH, PRESS)"
                            :disabled="testing !== null"
                            :aria-busy="testing === local.id"
                            @click="testProvider(local)"
                        >
                            <Loader2
                                v-if="testing === local.id"
                                class="size-4 motion-safe:animate-spin"
                            />
                            <PlugZap v-else class="size-4" />
                            {{ testing === local.id ? 'Probando…' : 'Probar' }}
                        </Button>
                        <Button
                            v-if="!local.isActive"
                            variant="secondary"
                            :class="cn(TOUCH, PRESS)"
                            :disabled="
                                !local.canActivate || activating !== null
                            "
                            @click="pendingActivation = local"
                        >
                            <Power class="size-4" />
                            Activar proveedor
                        </Button>
                        <span v-else class="text-xs text-muted-foreground"
                            >Las subidas nuevas se guardan aquí.</span
                        >
                        <p
                            v-if="!local.isActive && !local.canActivate"
                            class="w-full text-xs text-muted-foreground"
                        >
                            {{ activationBlocker(local) }}
                        </p>
                    </footer>
                </article>

                <!-- Synology -->
                <article
                    v-if="synology"
                    :class="
                        cn(
                            'provider-card flex flex-col gap-5 rounded-xl border bg-card p-5 lg:col-span-7',
                            ENTER,
                            synology.isActive && 'is-active',
                        )
                    "
                    :style="enter(2)"
                    :aria-busy="testing === synology.id"
                >
                    <header class="flex items-start gap-3">
                        <span
                            class="grid size-11 shrink-0 place-items-center rounded-xl"
                            :class="
                                synology.isActive
                                    ? 'bg-brand/15 text-brand'
                                    : 'bg-muted text-muted-foreground'
                            "
                        >
                            <Server class="size-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2
                                    class="font-display text-lg leading-tight font-semibold"
                                >
                                    {{ synology.label }}
                                </h2>
                                <span
                                    v-if="synology.isActive"
                                    class="active-chip inline-flex h-6 items-center rounded-full px-2.5 text-[11px] font-semibold"
                                    >Activo</span
                                >
                                <span
                                    v-else
                                    class="inline-flex h-6 items-center rounded-full bg-muted px-2.5 text-[11px] font-semibold text-muted-foreground"
                                    >Inactivo</span
                                >
                            </div>
                            <p class="mt-1 text-sm text-muted-foreground">
                                NAS {{ nas.name }} montado en el servidor por
                                {{ nas.protocol }} a través de Tailscale. Ideal
                                para videos pesados.
                            </p>
                        </div>
                    </header>

                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                        aria-live="polite"
                    >
                        <HealthPill
                            :status="statusOf(synology)"
                            :testing="testing === synology.id"
                        />
                        <span class="text-xs text-muted-foreground">
                            Última prueba:
                            <time
                                :datetime="synology.lastTestedAt ?? undefined"
                                :title="formatRelative(synology.lastTestedAt)"
                                class="font-medium text-foreground"
                            >
                                {{ formatDate(synology.lastTestedAt) }}
                            </time>
                        </span>
                    </div>

                    <ConnectionPath
                        :status="statusOf(synology)"
                        :testing="testing === synology.id"
                        :address="nas.address"
                        :nas-name="nas.name"
                    />

                    <div class="grid gap-5 md:grid-cols-2">
                        <dl class="grid content-start gap-x-4 gap-y-3 text-sm">
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Nombre
                                </dt>
                                <dd class="font-medium">{{ nas.name }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Dirección Tailscale
                                </dt>
                                <dd class="font-medium tabular-nums">
                                    {{ nas.address }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Protocolo
                                </dt>
                                <dd class="font-medium">{{ nas.protocol }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Carpeta compartida
                                </dt>
                                <dd class="font-medium">{{ nas.share }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Punto de montaje
                                </dt>
                                <dd>
                                    <code
                                        class="rounded bg-muted px-1.5 py-0.5 text-xs break-all"
                                        >{{ nas.mountPath }}</code
                                    >
                                </dd>
                            </div>
                        </dl>

                        <CapacityGauge
                            :space="synology.space"
                            :library-bytes="synology.filesBytes"
                            label="Capacidad del NAS"
                            :delay="400"
                        >
                            <template #caption>
                                <p class="mb-2 text-sm font-semibold">
                                    Capacidad del NAS
                                </p>
                            </template>
                            <template #empty>
                                <p class="text-sm font-medium text-destructive">
                                    Sin datos — NAS desconectado
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    La capacidad aparece cuando el servidor
                                    vuelve a ver el montaje.
                                </p>
                            </template>
                        </CapacityGauge>
                    </div>

                    <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                        <span
                            ><span class="font-semibold"
                                ><AnimatedNumber
                                    :value="synology.filesCount"
                                    :delay="350"
                            /></span>
                            <span class="text-muted-foreground"
                                >archivos guardados</span
                            ></span
                        >
                        <span
                            ><span class="font-semibold"
                                ><AnimatedNumber
                                    :value="synology.filesBytes"
                                    :format="formatBytes"
                                    :delay="350"
                            /></span>
                            <span class="text-muted-foreground"
                                >ocupa la biblioteca</span
                            ></span
                        >
                    </div>

                    <HealthChecks
                        :checks="normalizeChecks(synology.lastTestResult)"
                        :items="nasChecks"
                        :testing="testing === synology.id"
                        :revision="synology.lastTestResult?.checkedAt"
                    />

                    <Transition
                        mode="out-in"
                        enter-active-class="transition duration-300"
                        enter-from-class="opacity-0"
                        leave-active-class="transition duration-150"
                        leave-to-class="opacity-0"
                    >
                        <p
                            :key="synology.lastTestResult?.checkedAt ?? 'none'"
                            class="text-sm text-muted-foreground"
                            aria-live="polite"
                        >
                            {{
                                synology.lastTestResult?.message ??
                                STATUS[statusOf(synology)].hint
                            }}
                        </p>
                    </Transition>

                    <footer
                        class="mt-auto flex flex-col gap-3 border-t pt-4 sm:flex-row sm:flex-wrap sm:items-center"
                    >
                        <Button
                            variant="outline"
                            :class="cn(TOUCH, PRESS)"
                            :disabled="testing !== null"
                            :aria-busy="testing === synology.id"
                            @click="testProvider(synology)"
                        >
                            <Loader2
                                v-if="testing === synology.id"
                                class="size-4 motion-safe:animate-spin"
                            />
                            <PlugZap v-else class="size-4" />
                            {{
                                testing === synology.id
                                    ? 'Probando conexión…'
                                    : 'Probar conexión'
                            }}
                        </Button>

                        <template v-if="!synology.isActive">
                            <Button
                                v-if="synology.canActivate"
                                :class="cn(TOUCH, PRESS)"
                                :disabled="activating !== null"
                                @click="pendingActivation = synology"
                            >
                                <Power class="size-4" />
                                Activar proveedor
                            </Button>
                            <Tooltip v-else>
                                <TooltipTrigger as-child>
                                    <span
                                        tabindex="0"
                                        class="inline-flex rounded-md"
                                        :aria-describedby="`nas-blocker-${synology.id}`"
                                    >
                                        <Button
                                            :class="TOUCH"
                                            disabled
                                            tabindex="-1"
                                        >
                                            <Power class="size-4" />
                                            Activar proveedor
                                        </Button>
                                    </span>
                                </TooltipTrigger>
                                <TooltipContent class="max-w-64">{{
                                    activationBlocker(synology)
                                }}</TooltipContent>
                            </Tooltip>
                            <p
                                v-if="!synology.canActivate"
                                :id="`nas-blocker-${synology.id}`"
                                class="text-xs text-muted-foreground sm:max-w-64"
                            >
                                {{ activationBlocker(synology) }}
                            </p>
                        </template>
                        <span v-else class="text-xs text-muted-foreground"
                            >Las subidas nuevas se guardan en el NAS.</span
                        >
                    </footer>
                </article>
            </div>

            <p
                :class="
                    cn(
                        'flex items-start gap-2.5 rounded-lg border border-dashed px-4 py-3 text-sm text-muted-foreground',
                        ENTER,
                    )
                "
                :style="enter(3)"
            >
                <KeyRound
                    class="mt-0.5 size-4 shrink-0 text-brand"
                    aria-hidden="true"
                />
                Las credenciales SMB se administran desde el servidor Linux;
                este panel no las almacena.
            </p>
        </div>
    </TooltipProvider>

    <AlertDialog
        :open="pendingActivation !== null"
        @update:open="
            (v: boolean) =>
                !v && activating === null && (pendingActivation = null)
        "
    >
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle
                    >¿Activar {{ pendingActivation?.label }}?</AlertDialogTitle
                >
                <AlertDialogDescription
                    v-if="pendingActivation?.key === 'synology'"
                >
                    Las subidas nuevas se guardarán en el NAS {{ nas.name }}.
                    Los archivos que ya existen no se mueven: seguirán en el
                    almacenamiento local y se servirán desde ahí.
                </AlertDialogDescription>
                <AlertDialogDescription v-else>
                    Las subidas nuevas se guardarán en el disco del servidor.
                    Los archivos que ya están en el NAS no se mueven y seguirán
                    sirviéndose desde ahí.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel :disabled="activating !== null"
                    >Cancelar</AlertDialogCancel
                >
                <AlertDialogAction
                    :disabled="activating !== null"
                    @click.prevent="confirmActivation"
                >
                    <Loader2
                        v-if="activating !== null"
                        class="size-4 motion-safe:animate-spin"
                    />
                    Activar proveedor
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>

<style scoped>
/* Tarjeta de proveedor: se eleva al pasar el cursor; la activa lleva filo dorado. */
.provider-card {
    position: relative;
    transition:
        transform 350ms var(--ease-editorial),
        box-shadow 350ms var(--ease-editorial),
        border-color 350ms var(--ease-editorial);
}
@media (hover: hover) and (prefers-reduced-motion: no-preference) {
    .provider-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 18px 40px -24px
            color-mix(in srgb, var(--color-brand) 55%, transparent);
    }
}
.provider-card.is-active {
    border-color: color-mix(in srgb, var(--color-brand) 55%, transparent);
}
.provider-card.is-active::before {
    content: '';
    position: absolute;
    inset-inline: 1.25rem;
    top: -1px;
    height: 2px;
    border-radius: 999px;
    background: linear-gradient(
        90deg,
        transparent,
        var(--color-brand),
        transparent
    );
}

/* Distintivo "activo": borde con degradado dorado que se desplaza lento. */
.active-chip {
    border: 1px solid transparent;
    background:
        linear-gradient(var(--color-card), var(--color-card)) padding-box,
        linear-gradient(
                110deg,
                var(--color-brand),
                color-mix(in srgb, var(--color-brand) 15%, transparent),
                var(--color-brand)
            )
            border-box;
    background-size:
        100% 100%,
        220% 100%;
    animation: sheen 6s linear infinite;
}
@keyframes sheen {
    to {
        background-position:
            0 0,
            220% 0;
    }
}

/* Barra de reparto: crece desde la izquierda. */
.grow-bar {
    transform-origin: left center;
    animation: grow 1s var(--ease-editorial) both;
}
@keyframes grow {
    from {
        transform: scaleX(0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .active-chip,
    .grow-bar {
        animation: none;
    }
}
</style>
