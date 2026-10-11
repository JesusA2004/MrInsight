<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ImagePlus, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import MediaPickerDialog, { type PickedMedia } from '@/components/admin/MediaPickerDialog.vue';
import PostEditor from '@/components/admin/PostEditor.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import paginas from '@/routes/admin/paginas';
import paginasEntradas from '@/routes/admin/paginas-entradas';
import type { BreadcrumbItem, TiptapContent } from '@/types';

interface PageEntryRow {
    id: number;
    title: string;
    entry_date: string | null;
    body: TiptapContent | null;
    is_visible: boolean;
    order: number;
    cover: { id: number; url: string | null } | null;
}

const props = defineProps<{
    page: {
        key: string;
        title: string;
        intro: string | null;
        body: TiptapContent | null;
        cover: { id: number; url: string | null } | null;
        seo_title: string | null;
        seo_description: string | null;
    };
    label: string;
    entries?: PageEntryRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Páginas', href: paginas.index() },
        ] as BreadcrumbItem[],
    },
});

const form = useForm({
    title: props.page.title,
    intro: props.page.intro ?? '',
    body: props.page.body,
    cover_media_id: props.page.cover?.id ?? null,
    seo_title: props.page.seo_title ?? '',
    seo_description: props.page.seo_description ?? '',
});

const coverUrl = ref(props.page.cover?.url ?? null);
const coverPickerOpen = ref(false);

function onCoverSelected(media: PickedMedia) {
    form.cover_media_id = media.id;
    coverUrl.value = media.url;
}

function save() {
    form.put(paginas.update(props.page.key).url);
}

// --- Entradas de Mi Proceso ---
const entryForm = useForm({ title: '', entry_date: '', is_visible: true, order: 0 });
const addingEntry = ref(false);

function addEntry() {
    entryForm.post(paginasEntradas.store().url, {
        onSuccess: () => {
            entryForm.reset();
            addingEntry.value = false;
        },
    });
}

function toggleEntryVisible(entry: PageEntryRow) {
    router.put(paginasEntradas.update(entry.id).url, {
        title: entry.title,
        entry_date: entry.entry_date,
        body: entry.body,
        is_visible: !entry.is_visible,
        order: entry.order,
    }, { preserveScroll: true });
}

function destroyEntry(id: number) {
    router.delete(paginasEntradas.destroy(id).url, { preserveScroll: true });
}

// --- Edición completa de una entrada (título, fecha, portada, contenido) ---
const editingEntry = ref<PageEntryRow | null>(null);
const entryEditForm = useForm<{ title: string; entry_date: string; body: TiptapContent | null; cover_media_id: number | null; is_visible: boolean; order: number }>({
    title: '',
    entry_date: '',
    body: null,
    cover_media_id: null,
    is_visible: true,
    order: 0,
});
const entryCoverUrl = ref<string | null>(null);
const entryCoverPickerOpen = ref(false);

function openEntryEditor(entry: PageEntryRow) {
    editingEntry.value = entry;
    entryEditForm.title = entry.title;
    entryEditForm.entry_date = entry.entry_date ?? '';
    entryEditForm.body = entry.body;
    entryEditForm.cover_media_id = entry.cover?.id ?? null;
    entryEditForm.is_visible = entry.is_visible;
    entryEditForm.order = entry.order;
    entryCoverUrl.value = entry.cover?.url ?? null;
}

function onEntryCoverSelected(media: PickedMedia) {
    entryEditForm.cover_media_id = media.id;
    entryCoverUrl.value = media.url;
}

function saveEntryEdit() {
    if (!editingEntry.value) return;
    entryEditForm.put(paginasEntradas.update(editingEntry.value.id).url, {
        preserveScroll: true,
        onSuccess: () => (editingEntry.value = null),
    });
}
</script>

<template>
    <Head :title="label" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:max-w-2xl md:p-6">
        <h1 class="text-xl font-semibold">{{ label }}</h1>

        <form class="flex flex-col gap-5" @submit.prevent="save">
            <div class="grid gap-2">
                <Label for="title">Título</Label>
                <Input id="title" v-model="form.title" />
            </div>

            <div class="grid gap-2">
                <Label>Fotografía principal</Label>
                <button
                    type="button"
                    class="flex aspect-video w-full items-center justify-center overflow-hidden rounded-lg border border-dashed bg-muted/40 hover:border-primary"
                    @click="coverPickerOpen = true"
                >
                    <img v-if="coverUrl" :src="coverUrl" alt="" class="size-full object-cover" />
                    <span v-else class="flex flex-col items-center gap-2 text-sm text-muted-foreground">
                        <ImagePlus class="size-6" />
                        Elegir fotografía
                    </span>
                </button>
            </div>

            <div class="grid gap-2">
                <Label for="intro">Introducción</Label>
                <Textarea id="intro" v-model="form.intro" rows="3" />
            </div>

            <div class="grid gap-2">
                <Label>Contenido completo</Label>
                <PostEditor v-model:content="form.body" />
            </div>

            <details class="rounded-lg border p-4">
                <summary class="cursor-pointer text-sm font-medium">SEO</summary>
                <div class="mt-4 grid gap-4">
                    <div class="grid gap-2">
                        <Label for="seo_title">Título SEO</Label>
                        <Input id="seo_title" v-model="form.seo_title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="seo_description">Meta descripción</Label>
                        <Textarea id="seo_description" v-model="form.seo_description" rows="2" />
                    </div>
                </div>
            </details>

            <Button type="submit" :disabled="form.processing">Guardar cambios</Button>
        </form>

        <div v-if="entries" class="flex flex-col gap-4 border-t pt-6">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Entradas del proceso</h2>
                <Button variant="outline" size="sm" @click="addingEntry = !addingEntry">
                    <Plus class="size-4" />
                    Agregar entrada
                </Button>
            </div>

            <div v-if="addingEntry" class="flex flex-col gap-3 rounded-lg border p-4">
                <Input v-model="entryForm.title" placeholder="Título de la entrada" />
                <Input v-model="entryForm.entry_date" type="date" />
                <Button size="sm" :disabled="entryForm.processing" @click="addEntry">Guardar entrada</Button>
            </div>

            <div v-if="entries.length === 0" class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                Sin entradas todavía.
            </div>
            <div v-else class="flex flex-col divide-y rounded-lg border">
                <div v-for="entry in entries" :key="entry.id" class="flex items-center gap-3 p-3">
                    <div class="size-10 shrink-0 overflow-hidden rounded bg-muted">
                        <img v-if="entry.cover?.url" :src="entry.cover.url" class="size-full object-cover" alt="" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ entry.title }}</p>
                        <p class="text-xs text-muted-foreground">{{ entry.entry_date }}</p>
                    </div>
                    <Switch :checked="entry.is_visible" @update:checked="() => toggleEntryVisible(entry)" />
                    <Button variant="ghost" size="icon-sm" @click="openEntryEditor(entry)">
                        <Pencil class="size-4" />
                    </Button>
                    <Button variant="ghost" size="icon-sm" @click="destroyEntry(entry.id)">
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </div>
        </div>
    </div>

    <MediaPickerDialog v-model:open="coverPickerOpen" type="image" @select="onCoverSelected" />

    <Dialog :open="editingEntry !== null" @update:open="(v) => !v && (editingEntry = null)">
        <DialogContent class="max-h-[85vh] max-w-2xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Editar entrada — {{ editingEntry?.title }}</DialogTitle>
            </DialogHeader>
            <div class="flex flex-col gap-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label>Título</Label>
                        <Input v-model="entryEditForm.title" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Fecha</Label>
                        <Input v-model="entryEditForm.entry_date" type="date" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label>Fotografía</Label>
                    <button
                        type="button"
                        class="flex aspect-video w-full max-w-xs items-center justify-center overflow-hidden rounded-lg border border-dashed bg-muted/40 hover:border-primary"
                        @click="entryCoverPickerOpen = true"
                    >
                        <img v-if="entryCoverUrl" :src="entryCoverUrl" alt="" class="size-full object-cover" />
                        <span v-else class="flex flex-col items-center gap-2 text-sm text-muted-foreground">
                            <ImagePlus class="size-6" />
                            Elegir fotografía
                        </span>
                    </button>
                </div>
                <div class="grid gap-2">
                    <Label>Contenido (encabezados, párrafos, imágenes, videos, citas)</Label>
                    <PostEditor v-model:content="entryEditForm.body" />
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="editingEntry = null">Cancelar</Button>
                <Button :disabled="entryEditForm.processing" @click="saveEntryEdit">Guardar entrada</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <MediaPickerDialog v-model:open="entryCoverPickerOpen" type="image" @select="onEntryCoverSelected" />
</template>
