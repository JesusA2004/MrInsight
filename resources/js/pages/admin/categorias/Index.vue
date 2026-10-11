<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import categorias from '@/routes/admin/categorias';
import type { BreadcrumbItem } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Categorías', href: categorias.index() },
        ] as BreadcrumbItem[],
    },
});

defineProps<{
    categories: {
        id: number;
        name: string;
        slug: string;
        color: string;
        icon: string | null;
        status: string;
        order: number;
        postsCount: number;
    }[];
    can: { create: boolean };
}>();

const pendingDelete = ref<number | null>(null);

function confirmDelete(id: number) {
    pendingDelete.value = id;
}

function destroy() {
    if (pendingDelete.value === null) return;
    router.delete(categorias.destroy(pendingDelete.value).url, {
        onFinish: () => (pendingDelete.value = null),
    });
}
</script>

<template>
    <Head title="Categorías" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Categorías</h1>
            <Button v-if="can.create" as-child>
                <Link :href="categorias.create()">
                    <Plus class="size-4" />
                    Nueva categoría
                </Link>
            </Button>
        </div>

        <div class="rounded-xl border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Nombre</TableHead>
                        <TableHead>Estado</TableHead>
                        <TableHead>Publicaciones</TableHead>
                        <TableHead>Orden</TableHead>
                        <TableHead class="text-right">Acciones</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="category in categories" :key="category.id">
                        <TableCell class="flex items-center gap-2 font-medium">
                            <span
                                class="size-3 rounded-full"
                                :style="{ backgroundColor: category.color }"
                            />
                            {{ category.name }}
                        </TableCell>
                        <TableCell>
                            <Badge
                                :variant="
                                    category.status === 'active'
                                        ? 'default'
                                        : 'outline'
                                "
                            >
                                {{
                                    category.status === 'active'
                                        ? 'Activa'
                                        : 'Inactiva'
                                }}
                            </Badge>
                        </TableCell>
                        <TableCell>{{ category.postsCount }}</TableCell>
                        <TableCell>{{ category.order }}</TableCell>
                        <TableCell class="text-right">
                            <Button as-child variant="ghost" size="icon-sm">
                                <Link :href="categorias.edit(category.id)">
                                    <Pencil class="size-4" />
                                </Link>
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                :disabled="category.postsCount > 0"
                                @click="confirmDelete(category.id)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="categories.length === 0">
                        <TableCell
                            colspan="5"
                            class="py-10 text-center text-muted-foreground"
                        >
                            No hay categorías todavía.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>

    <AlertDialog
        :open="pendingDelete !== null"
        @update:open="(v) => !v && (pendingDelete = null)"
    >
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>¿Eliminar esta categoría?</AlertDialogTitle>
                <AlertDialogDescription>
                    Esta acción no se puede deshacer. Solo puedes eliminar
                    categorías sin publicaciones asociadas.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancelar</AlertDialogCancel>
                <AlertDialogAction @click="destroy">Eliminar</AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
