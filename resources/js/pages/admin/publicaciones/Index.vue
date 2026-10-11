<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, Pencil, Plus, Star, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import publicaciones from '@/routes/admin/publicaciones';
import type { BreadcrumbItem } from '@/types';

interface PostRow {
    id: number;
    title: string;
    slug: string;
    status: string;
    statusLabel: string;
    isFeatured: boolean;
    category: string | null;
    author: string | null;
    coverUrl: string | null;
    updatedAt: string | null;
}

const props = defineProps<{
    posts: {
        data: PostRow[];
        currentPage: number;
        lastPage: number;
        nextPageUrl: string | null;
        prevPageUrl: string | null;
        total: number;
    };
    filters: { status?: string; categoria?: string; buscar?: string };
    categories: { id: number; name: string; slug: string }[];
    can: { create: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Publicaciones', href: publicaciones.index() },
        ] as BreadcrumbItem[],
    },
});

const search = ref(props.filters.buscar ?? '');
const status = ref(props.filters.status ?? 'todos');
const category = ref(props.filters.categoria ?? 'todos');

function applyFilters() {
    router.get(
        publicaciones.index().url,
        {
            buscar: search.value || undefined,
            status: status.value === 'todos' ? undefined : status.value,
            categoria: category.value === 'todos' ? undefined : category.value,
        },
        { preserveState: true, replace: true },
    );
}

const statusVariant: Record<string, 'default' | 'secondary' | 'outline'> = {
    published: 'default',
    scheduled: 'secondary',
    draft: 'outline',
    archived: 'outline',
};

const pendingDelete = ref<number | null>(null);

function destroy() {
    if (pendingDelete.value === null) return;
    router.delete(publicaciones.destroy(pendingDelete.value).url, {
        onFinish: () => (pendingDelete.value = null),
    });
}
</script>

<template>
    <Head title="Publicaciones" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Publicaciones</h1>
            <Button v-if="can.create" as-child>
                <Link :href="publicaciones.create()">
                    <Plus class="size-4" />
                    Nueva publicación
                </Link>
            </Button>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <Input
                v-model="search"
                placeholder="Buscar por título…"
                class="max-w-xs"
                @keyup.enter="applyFilters"
            />
            <Select v-model="status" @update:model-value="applyFilters">
                <SelectTrigger class="w-40"><SelectValue /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="todos">Todos los estados</SelectItem>
                    <SelectItem value="draft">Borrador</SelectItem>
                    <SelectItem value="scheduled">Programado</SelectItem>
                    <SelectItem value="published">Publicado</SelectItem>
                    <SelectItem value="archived">Archivado</SelectItem>
                </SelectContent>
            </Select>
            <Select v-model="category" @update:model-value="applyFilters">
                <SelectTrigger class="w-48"><SelectValue /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="todos">Todas las categorías</SelectItem>
                    <SelectItem
                        v-for="c in categories"
                        :key="c.slug"
                        :value="c.slug"
                        >{{ c.name }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <Button variant="outline" size="sm" @click="applyFilters"
                >Filtrar</Button
            >
        </div>

        <div class="rounded-xl border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Publicación</TableHead>
                        <TableHead>Categoría</TableHead>
                        <TableHead>Estado</TableHead>
                        <TableHead>Autor</TableHead>
                        <TableHead class="text-right">Acciones</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="post in posts.data" :key="post.id">
                        <TableCell class="flex items-center gap-3">
                            <div
                                class="size-10 shrink-0 overflow-hidden rounded bg-muted"
                            >
                                <img
                                    v-if="post.coverUrl"
                                    :src="post.coverUrl"
                                    class="size-full object-cover"
                                    alt=""
                                />
                            </div>
                            <div class="min-w-0">
                                <Link
                                    :href="publicaciones.edit(post.id)"
                                    class="truncate font-medium hover:underline"
                                >
                                    {{ post.title }}
                                </Link>
                                <Star
                                    v-if="post.isFeatured"
                                    class="ml-1 inline size-3.5 text-amber-500"
                                />
                            </div>
                        </TableCell>
                        <TableCell>{{ post.category ?? '—' }}</TableCell>
                        <TableCell>
                            <Badge
                                :variant="
                                    statusVariant[post.status] ?? 'outline'
                                "
                                >{{ post.statusLabel }}</Badge
                            >
                        </TableCell>
                        <TableCell>{{ post.author }}</TableCell>
                        <TableCell class="text-right">
                            <Button as-child variant="ghost" size="icon-sm">
                                <a
                                    :href="publicaciones.preview(post.id).url"
                                    target="_blank"
                                >
                                    <Eye class="size-4" />
                                </a>
                            </Button>
                            <Button as-child variant="ghost" size="icon-sm">
                                <Link :href="publicaciones.edit(post.id)">
                                    <Pencil class="size-4" />
                                </Link>
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                @click="pendingDelete = post.id"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="posts.data.length === 0">
                        <TableCell
                            colspan="5"
                            class="py-10 text-center text-muted-foreground"
                        >
                            No se encontraron publicaciones con estos filtros.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <div
            v-if="posts.lastPage > 1"
            class="flex items-center justify-between"
        >
            <Link
                v-if="posts.prevPageUrl"
                :href="posts.prevPageUrl"
                class="text-sm hover:underline"
                >← Anteriores</Link
            >
            <span v-else />
            <span class="text-sm text-muted-foreground"
                >Página {{ posts.currentPage }} de {{ posts.lastPage }}</span
            >
            <Link
                v-if="posts.nextPageUrl"
                :href="posts.nextPageUrl"
                class="text-sm hover:underline"
                >Siguientes →</Link
            >
            <span v-else />
        </div>
    </div>

    <AlertDialog
        :open="pendingDelete !== null"
        @update:open="(v) => !v && (pendingDelete = null)"
    >
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>¿Eliminar esta publicación?</AlertDialogTitle>
                <AlertDialogDescription
                    >Esta acción no se puede deshacer.</AlertDialogDescription
                >
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancelar</AlertDialogCancel>
                <AlertDialogAction @click="destroy">Eliminar</AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
