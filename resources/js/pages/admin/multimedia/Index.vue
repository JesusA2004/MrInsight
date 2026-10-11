<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Grid3x3,
    List,
    Loader2,
    Search,
    Trash2,
    Upload,
    Video,
} from '@lucide/vue';
import { ref } from 'vue';
import DestinationPicker, {
    type CollectionNode,
} from '@/components/admin/DestinationPicker.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useChunkedUpload } from '@/composables/useChunkedUpload';
import { apiFetch } from '@/lib/http';
import multimedia from '@/routes/admin/multimedia';
import type { BreadcrumbItem } from '@/types';

interface MediaRow {
    id: number;
    filename: string;
    type: string;
    status: string;
    mimeType: string;
    size: number;
    width: number | null;
    height: number | null;
    durationSeconds: number | null;
    url: string | null;
    posterUrl: string | null;
    altText: string | null;
    destinationPath: string;
    disk: string;
    createdAt: string | null;
}

const props = defineProps<{
    media: {
        data: MediaRow[];
        currentPage: number;
        lastPage: number;
        total: number;
    };
    filters: { tipo?: string; coleccion?: string; q?: string; orden?: string };
    collections: CollectionNode[];
    storageBytes: number;
    activeProvider: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Multimedia', href: multimedia.index() },
        ] as BreadcrumbItem[],
    },
});

const items = ref<MediaRow[]>(props.media.data);
const view = ref<'grid' | 'list'>('grid');
const search = ref(props.filters.q ?? '');
const tipo = ref(props.filters.tipo ?? 'todos');
const orden = ref(props.filters.orden ?? 'reciente');

function applyFilters() {
    router.get(
        multimedia.index().url,
        {
            q: search.value || undefined,
            tipo: tipo.value === 'todos' ? undefined : tipo.value,
            orden: orden.value,
        },
        { preserveState: true, replace: true },
    );
}

function formatBytes(bytes: number): string {
    if (bytes <= 0) return '0 KB';
    const mb = bytes / (1024 * 1024);

    return mb >= 1 ? `${mb.toFixed(1)} MB` : `${(bytes / 1024).toFixed(0)} KB`;
}

function formatDuration(seconds: number | null): string {
    if (!seconds) return '';
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;

    return `${m}:${s.toString().padStart(2, '0')}`;
}

// --- Subida ---
const uploadPanelOpen = ref(false);
const destinationPath = ref('mr-insight/general');
const fileInput = ref<HTMLInputElement | null>(null);
const { progress, upload } = useChunkedUpload();
const uploading = ref(false);

function triggerUpload() {
    fileInput.value?.click();
}

async function handleFiles(event: Event) {
    const files = (event.target as HTMLInputElement).files;
    if (!files) return;

    uploading.value = true;
    for (const file of Array.from(files)) {
        try {
            const media = await upload(file, destinationPath.value);
            router.reload({ only: ['media', 'storageBytes'] });
            void media;
        } catch {
            // El progreso ya refleja el error; seguimos con el resto de archivos.
        }
    }
    uploading.value = false;
    if (fileInput.value) fileInput.value.value = '';
}

// --- Selección y detalle ---
const selected = ref<Set<number>>(new Set());
const activeItem = ref<MediaRow | null>(null);

function toggleSelect(id: number) {
    if (selected.value.has(id)) selected.value.delete(id);
    else selected.value.add(id);
}

async function destroySelected() {
    for (const id of selected.value) {
        await apiFetch(multimedia.destroy(id).url, { method: 'DELETE' });
    }
    items.value = items.value.filter((m) => !selected.value.has(m.id));
    selected.value = new Set();
}

async function destroyOne(id: number) {
    const response = await apiFetch(multimedia.destroy(id).url, {
        method: 'DELETE',
    });
    if (response.ok) {
        items.value = items.value.filter((m) => m.id !== id);
        if (activeItem.value?.id === id) activeItem.value = null;
    }
}

const altTextDraft = ref('');
function openDetail(item: MediaRow) {
    activeItem.value = item;
    altTextDraft.value = item.altText ?? '';
}

async function saveAltText() {
    if (!activeItem.value) return;
    await apiFetch(multimedia.update(activeItem.value.id).url, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ alt_text: altTextDraft.value }),
    });
    const item = items.value.find((m) => m.id === activeItem.value?.id);
    if (item) item.altText = altTextDraft.value;
}
</script>

<template>
    <Head title="Multimedia" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Biblioteca multimedia</h1>
                <p class="text-sm text-muted-foreground">
                    {{ formatBytes(storageBytes) }} utilizados ·
                    {{ media.total }} archivos · {{ activeProvider }}
                </p>
            </div>
            <Button @click="uploadPanelOpen = !uploadPanelOpen">
                <Upload class="size-4" />
                Subir archivo
            </Button>
        </div>

        <div v-if="uploadPanelOpen" class="rounded-xl border bg-muted/30 p-4">
            <DestinationPicker
                v-model:path="destinationPath"
                :collections="collections"
                :provider-label="activeProvider"
            />
            <div class="mt-4 flex items-center gap-3">
                <Button :disabled="uploading" @click="triggerUpload">
                    <Loader2 v-if="uploading" class="size-4 animate-spin" />
                    <Upload v-else class="size-4" />
                    Elegir archivos
                </Button>
                <input
                    ref="fileInput"
                    type="file"
                    multiple
                    class="hidden"
                    accept="image/*,video/*"
                    @change="handleFiles"
                />
                <div v-if="progress" class="flex-1">
                    <div class="h-2 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full bg-primary transition-all"
                            :style="{ width: `${progress.percent}%` }"
                        />
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ progress.file.name }} — {{ progress.percent }}%
                        <span
                            v-if="progress.status === 'error'"
                            class="text-destructive"
                            >(error)</span
                        >
                    </p>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <div class="relative max-w-xs flex-1">
                <Search
                    class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    placeholder="Buscar archivo…"
                    class="pl-8"
                    @keyup.enter="applyFilters"
                />
            </div>
            <Select v-model="tipo" @update:model-value="applyFilters">
                <SelectTrigger class="w-36"><SelectValue /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="todos">Todos los tipos</SelectItem>
                    <SelectItem value="image">Imágenes</SelectItem>
                    <SelectItem value="video">Videos</SelectItem>
                </SelectContent>
            </Select>
            <Select v-model="orden" @update:model-value="applyFilters">
                <SelectTrigger class="w-40"><SelectValue /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="reciente">Más recientes</SelectItem>
                    <SelectItem value="tamano">Mayor tamaño</SelectItem>
                    <SelectItem value="nombre">Nombre (A-Z)</SelectItem>
                </SelectContent>
            </Select>
            <div class="ml-auto flex items-center gap-1">
                <Button
                    :variant="view === 'grid' ? 'secondary' : 'ghost'"
                    size="icon-sm"
                    @click="view = 'grid'"
                >
                    <Grid3x3 class="size-4" />
                </Button>
                <Button
                    :variant="view === 'list' ? 'secondary' : 'ghost'"
                    size="icon-sm"
                    @click="view = 'list'"
                >
                    <List class="size-4" />
                </Button>
            </div>
            <Button
                v-if="selected.size > 0"
                variant="destructive"
                size="sm"
                @click="destroySelected"
            >
                <Trash2 class="size-4" />
                Eliminar ({{ selected.size }})
            </Button>
        </div>

        <div
            v-if="items.length === 0"
            class="rounded-xl border border-dashed p-16 text-center text-muted-foreground"
        >
            Aún no has subido ningún archivo.
        </div>

        <div
            v-else-if="view === 'grid'"
            class="grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-6"
        >
            <div
                v-for="item in items"
                :key="item.id"
                class="group relative overflow-hidden rounded-lg border bg-muted"
            >
                <Checkbox
                    :checked="selected.has(item.id)"
                    class="absolute top-2 left-2 z-10 bg-white"
                    @update:checked="() => toggleSelect(item.id)"
                />
                <button
                    type="button"
                    class="block aspect-square w-full"
                    @click="openDetail(item)"
                >
                    <img
                        v-if="item.type === 'image' && item.url"
                        :src="item.url"
                        class="size-full object-cover"
                        :alt="item.filename"
                    />
                    <img
                        v-else-if="item.type === 'video' && item.posterUrl"
                        :src="item.posterUrl"
                        class="size-full object-cover"
                        alt=""
                    />
                    <div
                        v-else-if="item.type === 'video'"
                        class="flex size-full items-center justify-center bg-ink-soft"
                    >
                        <Video class="size-8 text-muted-foreground" />
                    </div>
                    <div
                        v-if="item.status === 'processing'"
                        class="absolute inset-0 flex items-center justify-center bg-black/40"
                    >
                        <Loader2 class="size-5 animate-spin text-white" />
                    </div>
                </button>
                <div class="p-2">
                    <p class="truncate text-xs font-medium">
                        {{ item.filename }}
                    </p>
                    <p class="text-[11px] text-muted-foreground">
                        {{ formatBytes(item.size) }}
                    </p>
                </div>
            </div>
        </div>

        <div v-else class="overflow-hidden rounded-xl border">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-left text-xs text-muted-foreground"
                >
                    <tr>
                        <th class="w-10 p-2"></th>
                        <th class="p-2">Archivo</th>
                        <th class="p-2">Tipo</th>
                        <th class="p-2">Tamaño</th>
                        <th class="p-2">Destino</th>
                        <th class="p-2 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in items"
                        :key="item.id"
                        class="border-t hover:bg-muted/30"
                    >
                        <td class="p-2">
                            <Checkbox
                                :checked="selected.has(item.id)"
                                @update:checked="() => toggleSelect(item.id)"
                            />
                        </td>
                        <td
                            class="cursor-pointer p-2"
                            @click="openDetail(item)"
                        >
                            <span class="font-medium">{{ item.filename }}</span>
                            <span
                                v-if="item.durationSeconds"
                                class="ml-2 text-xs text-muted-foreground"
                            >
                                {{ formatDuration(item.durationSeconds) }}
                            </span>
                        </td>
                        <td class="p-2 capitalize">{{ item.type }}</td>
                        <td class="p-2">{{ formatBytes(item.size) }}</td>
                        <td class="p-2 font-mono text-xs text-muted-foreground">
                            {{ item.destinationPath }}
                        </td>
                        <td class="p-2 text-right">
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                @click="destroyOne(item.id)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <Sheet
        :open="activeItem !== null"
        @update:open="(v) => !v && (activeItem = null)"
    >
        <SheetContent v-if="activeItem" class="overflow-y-auto sm:max-w-md">
            <SheetHeader>
                <SheetTitle>{{ activeItem.filename }}</SheetTitle>
            </SheetHeader>
            <div class="flex flex-col gap-4 px-4 pb-6">
                <div class="aspect-video overflow-hidden rounded-lg bg-muted">
                    <img
                        v-if="activeItem.type === 'image' && activeItem.url"
                        :src="activeItem.url"
                        class="size-full object-cover"
                        :alt="activeItem.filename"
                    />
                    <video
                        v-else-if="
                            activeItem.type === 'video' && activeItem.url
                        "
                        :src="activeItem.url"
                        controls
                        class="size-full"
                    />
                </div>

                <dl class="grid grid-cols-2 gap-2 text-sm">
                    <dt class="text-muted-foreground">Tipo</dt>
                    <dd class="capitalize">{{ activeItem.type }}</dd>
                    <dt class="text-muted-foreground">Tamaño</dt>
                    <dd>{{ formatBytes(activeItem.size) }}</dd>
                    <dt v-if="activeItem.width" class="text-muted-foreground">
                        Dimensiones
                    </dt>
                    <dd v-if="activeItem.width">
                        {{ activeItem.width }} × {{ activeItem.height }}
                    </dd>
                    <dt
                        v-if="activeItem.durationSeconds"
                        class="text-muted-foreground"
                    >
                        Duración
                    </dt>
                    <dd v-if="activeItem.durationSeconds">
                        {{ formatDuration(activeItem.durationSeconds) }}
                    </dd>
                    <dt class="text-muted-foreground">Estado</dt>
                    <dd class="capitalize">{{ activeItem.status }}</dd>
                    <dt class="text-muted-foreground">Proveedor</dt>
                    <dd>{{ activeProvider }}</dd>
                    <dt class="text-muted-foreground">Ruta</dt>
                    <dd class="col-span-2 font-mono text-xs break-all">
                        {{ activeItem.destinationPath }}
                    </dd>
                    <dt v-if="activeItem.url" class="text-muted-foreground">
                        URL pública
                    </dt>
                    <dd
                        v-if="activeItem.url"
                        class="col-span-2 truncate text-xs"
                    >
                        <a
                            :href="activeItem.url"
                            target="_blank"
                            class="text-primary hover:underline"
                            >{{ activeItem.url }}</a
                        >
                    </dd>
                </dl>

                <div class="grid gap-2">
                    <Label for="alt-text">Texto alternativo</Label>
                    <Input
                        id="alt-text"
                        v-model="altTextDraft"
                        @blur="saveAltText"
                    />
                </div>

                <Button
                    variant="destructive"
                    @click="destroyOne(activeItem.id)"
                >
                    <Trash2 class="size-4" />
                    Eliminar archivo
                </Button>
            </div>
        </SheetContent>
    </Sheet>
</template>
