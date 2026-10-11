<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ImagePlus, Repeat } from '@lucide/vue';
import { ref } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import MediaPickerDialog, { type PickedMedia } from '@/components/admin/MediaPickerDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import inicio from '@/routes/admin/inicio';
import type { BreadcrumbItem, PostSummary } from '@/types';

interface SectionRow {
    section_key: string;
    is_visible: boolean;
    order: number;
}

interface FeaturedCandidate extends PostSummary {
    isFeatured: boolean;
}

interface CategoryPreview {
    category_id: number;
    category: string;
    post: PostSummary | null;
    candidates: FeaturedCandidate[];
}

const props = defineProps<{
    hero: {
        eyebrow: string;
        title_lines: string[];
        primary_cta_label: string;
        secondary_cta_label: string;
        tertiary_link_label: string;
        photo: { id: number; url: string | null } | null;
    };
    empiezaAqui: { title: string };
    tipsSection: { title: string; posts_per_page: number };
    historiaPreview: { title: string; photo: { id: number; url: string | null } | null };
    procesoPreview: { title: string };
    footer: { tagline: string };
    sections: SectionRow[];
    empiezaAquiPreview: CategoryPreview[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Inicio', href: inicio.edit() },
        ] as BreadcrumbItem[],
    },
});

const sectionLabels: Record<string, string> = {
    hero: 'Portada',
    empieza_aqui: 'Empieza aquí',
    tips: 'Explora los tips',
    mi_historia: 'Mi historia',
    mi_proceso: 'Mi proceso',
    footer: 'Pie de página',
};

const form = useForm({
    hero: { ...props.hero, photo_media_id: props.hero.photo?.id ?? null },
    empieza_aqui: { ...props.empiezaAqui },
    tips_section: { ...props.tipsSection },
    historia_preview: { ...props.historiaPreview, photo_media_id: props.historiaPreview.photo?.id ?? null },
    proceso_preview: { ...props.procesoPreview },
    footer: { ...props.footer },
    sections: props.sections.map((s) => ({ ...s })),
});

const heroPhotoUrl = ref(props.hero.photo?.url ?? null);
const historiaPhotoUrl = ref(props.historiaPreview.photo?.url ?? null);
const heroPickerOpen = ref(false);
const historiaPickerOpen = ref(false);

function onHeroPhoto(media: PickedMedia) {
    form.hero.photo_media_id = media.id;
    heroPhotoUrl.value = media.url;
}

function onHistoriaPhoto(media: PickedMedia) {
    form.historia_preview.photo_media_id = media.id;
    historiaPhotoUrl.value = media.url;
}

function updateTitleLine(index: number, value: string) {
    form.hero.title_lines[index] = value;
}

function save() {
    form.put(inicio.update().url);
}

function onSectionsReordered() {
    form.sections.forEach((section, index) => {
        section.order = index;
    });
}

// --- Selector de publicación destacada por categoría ---
const featuredPickerCategory = ref<CategoryPreview | null>(null);

function chooseFeatured(post: FeaturedCandidate) {
    if (!featuredPickerCategory.value) return;

    router.post(
        inicio.destacados().url,
        { category_id: featuredPickerCategory.value.category_id, post_id: post.id },
        { preserveScroll: true, onSuccess: () => (featuredPickerCategory.value = null) },
    );
}
</script>

<template>
    <Head title="Inicio" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:max-w-3xl md:p-6">
        <h1 class="text-xl font-semibold">Editar inicio</h1>

        <Card>
            <CardHeader><CardTitle>Portada (hero)</CardTitle></CardHeader>
            <CardContent class="grid gap-4">
                <div class="grid gap-2">
                    <Label>Etiqueta superior</Label>
                    <Input v-model="form.hero.eyebrow" placeholder="SIN TAPUJOS." />
                </div>
                <div class="grid gap-2">
                    <Label>Líneas del titular</Label>
                    <Input
                        v-for="(line, i) in form.hero.title_lines"
                        :key="i"
                        :model-value="line"
                        class="mb-1"
                        @update:model-value="(v) => updateTitleLine(i, String(v))"
                    />
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label>Botón principal</Label>
                        <Input v-model="form.hero.primary_cta_label" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Botón secundario</Label>
                        <Input v-model="form.hero.secondary_cta_label" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Enlace terciario</Label>
                        <Input v-model="form.hero.tertiary_link_label" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label>Fotografía</Label>
                    <button
                        type="button"
                        class="flex aspect-video w-full max-w-sm items-center justify-center overflow-hidden rounded-lg border border-dashed bg-muted/40 hover:border-primary"
                        @click="heroPickerOpen = true"
                    >
                        <img v-if="heroPhotoUrl" :src="heroPhotoUrl" alt="" class="size-full object-cover" />
                        <span v-else class="flex flex-col items-center gap-2 text-sm text-muted-foreground">
                            <ImagePlus class="size-6" />
                            Elegir fotografía
                        </span>
                    </button>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Empieza aquí</CardTitle></CardHeader>
            <CardContent class="grid gap-4">
                <div class="grid gap-2">
                    <Label>Título de la sección</Label>
                    <Input v-model="form.empieza_aqui.title" />
                </div>
                <div class="grid gap-2">
                    <Label class="text-muted-foreground">Publicación destacada por categoría</Label>
                    <div class="flex flex-col gap-2">
                        <div v-for="item in empiezaAquiPreview" :key="item.category_id" class="flex items-center gap-3 rounded-lg border p-2">
                            <div class="size-10 shrink-0 overflow-hidden rounded bg-muted">
                                <img v-if="item.post?.coverUrl" :src="item.post.coverUrl" class="size-full object-cover" alt="" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs text-muted-foreground">{{ item.category }}</p>
                                <p class="truncate text-sm font-medium">{{ item.post?.title ?? 'Sin publicaciones todavía' }}</p>
                                <p v-if="item.post?.publishedAt" class="text-xs text-muted-foreground">
                                    {{ new Date(item.post.publishedAt).toLocaleDateString('es-MX') }}
                                </p>
                            </div>
                            <Badge v-if="item.post" variant="outline">Publicado</Badge>
                            <Button
                                size="sm"
                                variant="outline"
                                :disabled="item.candidates.length === 0"
                                @click="featuredPickerCategory = item"
                            >
                                <Repeat class="size-3.5" />
                                Cambiar
                            </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Explora los tips</CardTitle></CardHeader>
            <CardContent class="grid grid-cols-2 gap-4">
                <div class="grid gap-2">
                    <Label>Título de la sección</Label>
                    <Input v-model="form.tips_section.title" />
                </div>
                <div class="grid gap-2">
                    <Label>Publicaciones a mostrar</Label>
                    <Input v-model.number="form.tips_section.posts_per_page" type="number" min="3" max="24" />
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Mi historia</CardTitle></CardHeader>
            <CardContent class="grid gap-4">
                <div class="grid gap-2">
                    <Label>Título</Label>
                    <Input v-model="form.historia_preview.title" />
                </div>
                <div class="grid gap-2">
                    <Label>Fotografía</Label>
                    <button
                        type="button"
                        class="flex aspect-video w-full max-w-sm items-center justify-center overflow-hidden rounded-lg border border-dashed bg-muted/40 hover:border-primary"
                        @click="historiaPickerOpen = true"
                    >
                        <img v-if="historiaPhotoUrl" :src="historiaPhotoUrl" alt="" class="size-full object-cover" />
                        <span v-else class="flex flex-col items-center gap-2 text-sm text-muted-foreground">
                            <ImagePlus class="size-6" />
                            Elegir fotografía
                        </span>
                    </button>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Mi proceso</CardTitle></CardHeader>
            <CardContent class="grid gap-2">
                <Label>Título</Label>
                <Input v-model="form.proceso_preview.title" />
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Pie de página</CardTitle></CardHeader>
            <CardContent class="grid gap-2">
                <Label>Frase editorial</Label>
                <Input v-model="form.footer.tagline" />
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Secciones visibles</CardTitle>
                <p class="text-xs text-muted-foreground">Arrastra para cambiar el orden en que aparecen en el inicio.</p>
            </CardHeader>
            <CardContent class="p-0">
                <VueDraggable v-model="form.sections" class="flex flex-col divide-y" handle=".drag-handle" @end="onSectionsReordered">
                    <div v-for="section in form.sections" :key="section.section_key" class="flex items-center justify-between gap-3 px-4 py-2">
                        <div class="flex items-center gap-2">
                            <span class="drag-handle cursor-grab text-muted-foreground select-none">⠿</span>
                            <span class="text-sm">{{ sectionLabels[section.section_key] ?? section.section_key }}</span>
                        </div>
                        <Switch v-model:checked="section.is_visible" />
                    </div>
                </VueDraggable>
            </CardContent>
        </Card>

        <Button :disabled="form.processing" @click="save">Guardar cambios</Button>
    </div>

    <MediaPickerDialog v-model:open="heroPickerOpen" type="image" @select="onHeroPhoto" />
    <MediaPickerDialog v-model:open="historiaPickerOpen" type="image" @select="onHistoriaPhoto" />

    <Dialog :open="featuredPickerCategory !== null" @update:open="(v) => !v && (featuredPickerCategory = null)">
        <DialogContent class="max-h-[80vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Elegir destacada — {{ featuredPickerCategory?.category }}</DialogTitle>
            </DialogHeader>
            <div class="flex flex-col gap-2">
                <button
                    v-for="candidate in featuredPickerCategory?.candidates ?? []"
                    :key="candidate.id"
                    type="button"
                    class="flex items-center gap-3 rounded-lg border p-2 text-left hover:border-primary"
                    @click="chooseFeatured(candidate)"
                >
                    <div class="size-12 shrink-0 overflow-hidden rounded bg-muted">
                        <img v-if="candidate.coverUrl" :src="candidate.coverUrl" class="size-full object-cover" alt="" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ candidate.title }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ candidate.publishedAt ? new Date(candidate.publishedAt).toLocaleDateString('es-MX') : '' }}
                        </p>
                    </div>
                    <Badge v-if="candidate.isFeatured" variant="default">Destacada</Badge>
                </button>
                <p v-if="!featuredPickerCategory?.candidates.length" class="py-6 text-center text-sm text-muted-foreground">
                    No hay publicaciones publicadas en esta categoría todavía.
                </p>
            </div>
        </DialogContent>
    </Dialog>
</template>
