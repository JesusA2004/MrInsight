<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export interface CollectionNode {
    id: number;
    parent_id: number | null;
    name: string;
    path: string;
}

const { collections, providerLabel } = defineProps<{
    collections: CollectionNode[];
    providerLabel?: string;
}>();

const path = defineModel<string>('path', { default: 'mr-insight/general' });

const root = computed(() =>
    collections.find((c) => c.parent_id === null && c.path === 'mr-insight'),
);

const colecciones = computed(() =>
    collections
        .filter((c) => c.parent_id === root.value?.id)
        .sort((a, b) => a.name.localeCompare(b.name)),
);

function carpetasDe(coleccionId: number | null) {
    return collections
        .filter((c) => c.parent_id === coleccionId)
        .sort((a, b) => a.name.localeCompare(b.name));
}

const segments = path.value.split('/');
const coleccionSlug = ref(
    segments[1] ?? colecciones.value[0]?.path.split('/').pop() ?? '',
);
const carpetaSlug = ref(segments[2] ?? '');

const coleccionActual = computed(() =>
    colecciones.value.find(
        (c) => c.path === `mr-insight/${coleccionSlug.value}`,
    ),
);
const carpetas = computed(() =>
    coleccionActual.value ? carpetasDe(coleccionActual.value.id) : [],
);

watch([coleccionSlug, carpetaSlug], ([coleccion, carpeta]) => {
    path.value = carpeta
        ? `mr-insight/${coleccion}/${carpeta}`
        : `mr-insight/${coleccion}`;
});

watch(coleccionSlug, () => {
    if (!carpetas.value.some((f) => f.path.endsWith(`/${carpetaSlug.value}`))) {
        carpetaSlug.value = carpetas.value[0]?.path.split('/').pop() ?? '';
    }
});
</script>

<template>
    <div class="grid gap-3 sm:grid-cols-2">
        <div class="grid gap-1.5">
            <span class="text-xs text-muted-foreground">Colección</span>
            <Select v-model="coleccionSlug">
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="coleccion in colecciones"
                        :key="coleccion.id"
                        :value="coleccion.path.split('/').pop() ?? ''"
                    >
                        {{ coleccion.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>
        <div class="grid gap-1.5">
            <span class="text-xs text-muted-foreground">Carpeta</span>
            <Select v-model="carpetaSlug">
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="carpeta in carpetas"
                        :key="carpeta.id"
                        :value="carpeta.path.split('/').pop() ?? ''"
                    >
                        {{ carpeta.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>
        <p class="col-span-full text-xs text-muted-foreground">
            Almacenamiento: {{ providerLabel ?? 'Almacenamiento local' }} ·
            Biblioteca: MR. INSIGHT · Destino:
            <span class="font-mono text-foreground">{{ path }}/</span>
        </p>
    </div>
</template>
