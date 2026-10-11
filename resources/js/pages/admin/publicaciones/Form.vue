<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Eye,
    ImagePlus,
    Rocket,
    Archive,
    Star,
    CalendarClock,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import MediaPickerDialog, {
    type PickedMedia,
} from '@/components/admin/MediaPickerDialog.vue';
import PostEditor from '@/components/admin/PostEditor.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import publicaciones from '@/routes/admin/publicaciones';
import type { BreadcrumbItem, TiptapContent } from '@/types';

interface PostData {
    id: number;
    category_id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    body: TiptapContent | null;
    status: string;
    statusLabel: string;
    is_featured: boolean;
    seo_title: string | null;
    seo_description: string | null;
    tags: string[] | null;
    scheduled_at: string | null;
    published_at: string | null;
    cover: { id: number; url: string | null } | null;
    gallery: { id: number; url: string | null }[];
}

const props = defineProps<{
    post: PostData | null;
    categories: { id: number; name: string; slug: string }[];
}>();

const isEditing = props.post !== null;

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Publicaciones', href: publicaciones.index() },
        ] as BreadcrumbItem[],
    },
});

const form = useForm({
    category_id: props.post?.category_id ?? props.categories[0]?.id ?? null,
    title: props.post?.title ?? '',
    slug: props.post?.slug ?? '',
    excerpt: props.post?.excerpt ?? '',
    body: props.post?.body ?? null,
    cover_media_id: props.post?.cover?.id ?? null,
    gallery_media_ids: props.post?.gallery.map((g) => g.id) ?? [],
    is_featured: props.post?.is_featured ?? false,
    seo_title: props.post?.seo_title ?? '',
    seo_description: props.post?.seo_description ?? '',
    tags: props.post?.tags ?? [],
});

const coverUrl = ref(props.post?.cover?.url ?? null);
const coverPickerOpen = ref(false);
const tagsInput = ref((props.post?.tags ?? []).join(', '));
const isDirty = ref(false);

function syncTags() {
    form.tags = tagsInput.value
        .split(',')
        .map((t) => t.trim())
        .filter(Boolean);
}

function onCoverSelected(media: PickedMedia) {
    form.cover_media_id = media.id;
    coverUrl.value = media.url;
}

function save() {
    syncTags();
    if (isEditing && props.post) {
        form.put(publicaciones.update(props.post.id).url, {
            onSuccess: () => (isDirty.value = false),
        });
    } else {
        form.post(publicaciones.store().url);
    }
}

function publish() {
    if (!props.post) return;
    router.post(publicaciones.publicar(props.post.id).url);
}

function archive() {
    if (!props.post) return;
    router.post(publicaciones.archivar(props.post.id).url);
}

function toggleFeatured() {
    if (!props.post) return;
    router.post(publicaciones.destacar(props.post.id).url);
}

function preview() {
    if (!props.post) return;
    window.open(publicaciones.preview(props.post.id).url, '_blank');
}

const showScheduler = ref(false);
const scheduleAt = ref('');

function schedule() {
    if (!props.post || !scheduleAt.value) return;
    router.post(
        publicaciones.programar(props.post.id).url,
        { scheduled_at: scheduleAt.value },
        {
            onSuccess: () => (showScheduler.value = false),
        },
    );
}

let autosaveTimer: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
    if (!props.post) return;
    autosaveTimer = setInterval(() => {
        if (!isDirty.value) return;
        router.patch(
            publicaciones.autoguardar(props.post!.id).url,
            { title: form.title, excerpt: form.excerpt, body: form.body },
            { showProgress: false, onSuccess: () => (isDirty.value = false) },
        );
    }, 30000);
});
onBeforeUnmount(() => clearInterval(autosaveTimer));

function handleBeforeUnload(e: BeforeUnloadEvent) {
    if (isDirty.value) {
        e.preventDefault();
    }
}
onMounted(() => window.addEventListener('beforeunload', handleBeforeUnload));
onBeforeUnmount(() =>
    window.removeEventListener('beforeunload', handleBeforeUnload),
);

const statusBadgeVariant = computed(() => {
    switch (props.post?.status) {
        case 'published':
            return 'default';
        case 'scheduled':
            return 'secondary';
        case 'archived':
            return 'outline';
        default:
            return 'outline';
    }
});
</script>

<template>
    <Head :title="isEditing ? `Editar: ${post?.title}` : 'Nueva publicación'" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6" @input="isDirty = true">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <h1 class="text-xl font-semibold">
                    {{ isEditing ? 'Editar publicación' : 'Nueva publicación' }}
                </h1>
                <Badge v-if="post" :variant="statusBadgeVariant">{{
                    post.statusLabel
                }}</Badge>
                <Badge v-if="post?.is_featured" variant="secondary"
                    >Destacada</Badge
                >
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-if="post"
                    variant="outline"
                    size="sm"
                    @click="preview"
                >
                    <Eye class="size-4" />
                    Vista previa
                </Button>
                <Button
                    v-if="post"
                    variant="outline"
                    size="sm"
                    @click="toggleFeatured"
                >
                    <Star class="size-4" />
                    {{ post.is_featured ? 'Quitar destacado' : 'Destacar' }}
                </Button>
                <Button
                    v-if="post && post.status !== 'archived'"
                    variant="outline"
                    size="sm"
                    @click="archive"
                >
                    <Archive class="size-4" />
                    Archivar
                </Button>
                <Button
                    v-if="post && post.status !== 'published'"
                    size="sm"
                    @click="publish"
                >
                    <Rocket class="size-4" />
                    Publicar
                </Button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="flex flex-col gap-5 lg:col-span-2">
                <div class="grid gap-2">
                    <Label for="title">Título</Label>
                    <Input
                        id="title"
                        v-model="form.title"
                        required
                        class="text-lg"
                    />
                    <p
                        v-if="form.errors.title"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.title }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="excerpt">Resumen</Label>
                    <Textarea
                        id="excerpt"
                        v-model="form.excerpt"
                        rows="3"
                        maxlength="500"
                    />
                </div>

                <div class="grid gap-2">
                    <Label>Contenido</Label>
                    <PostEditor v-model:content="form.body" />
                </div>

                <details class="rounded-lg border p-4">
                    <summary class="cursor-pointer text-sm font-medium">
                        SEO
                    </summary>
                    <div class="mt-4 grid gap-4">
                        <div class="grid gap-2">
                            <Label for="seo_title">Título SEO</Label>
                            <Input id="seo_title" v-model="form.seo_title" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="seo_description"
                                >Meta descripción</Label
                            >
                            <Textarea
                                id="seo_description"
                                v-model="form.seo_description"
                                rows="2"
                            />
                        </div>
                    </div>
                </details>
            </div>

            <div class="flex flex-col gap-5">
                <div class="grid gap-2">
                    <Label>Categoría</Label>
                    <Select v-model="form.category_id">
                        <SelectTrigger class="w-full">
                            <SelectValue
                                placeholder="Selecciona una categoría"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="category in categories"
                                :key="category.id"
                                :value="category.id"
                            >
                                {{ category.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p
                        v-if="form.errors.category_id"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.category_id }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label>Portada</Label>
                    <button
                        type="button"
                        class="flex aspect-video w-full items-center justify-center overflow-hidden rounded-lg border border-dashed bg-muted/40 hover:border-primary"
                        @click="coverPickerOpen = true"
                    >
                        <img
                            v-if="coverUrl"
                            :src="coverUrl"
                            alt="Portada"
                            class="size-full object-cover"
                        />
                        <span
                            v-else
                            class="flex flex-col items-center gap-2 text-sm text-muted-foreground"
                        >
                            <ImagePlus class="size-6" />
                            Elegir portada
                        </span>
                    </button>
                </div>

                <div class="grid gap-2">
                    <Label for="tags">Etiquetas (separadas por coma)</Label>
                    <Input
                        id="tags"
                        v-model="tagsInput"
                        placeholder="hábitos, enfoque, disciplina"
                    />
                </div>

                <div v-if="post" class="grid gap-2">
                    <button
                        type="button"
                        class="flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                        @click="showScheduler = !showScheduler"
                    >
                        <CalendarClock class="size-4" />
                        {{
                            post.scheduled_at
                                ? 'Reprogramar publicación'
                                : 'Programar publicación'
                        }}
                    </button>
                    <div v-if="showScheduler" class="flex items-center gap-2">
                        <Input
                            v-model="scheduleAt"
                            type="datetime-local"
                            class="flex-1"
                        />
                        <Button type="button" size="sm" @click="schedule"
                            >Programar</Button
                        >
                    </div>
                </div>

                <Button type="button" :disabled="form.processing" @click="save">
                    {{ isEditing ? 'Guardar cambios' : 'Guardar borrador' }}
                </Button>
                <Link
                    :href="publicaciones.index()"
                    class="text-center text-sm text-muted-foreground hover:underline"
                >
                    Cancelar
                </Link>
            </div>
        </div>
    </div>

    <MediaPickerDialog
        v-model:open="coverPickerOpen"
        type="image"
        @select="onCoverSelected"
    />
</template>
