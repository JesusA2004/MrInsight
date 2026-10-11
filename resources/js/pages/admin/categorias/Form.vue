<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
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
import categorias from '@/routes/admin/categorias';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    category: {
        id: number;
        name: string;
        slug: string;
        description: string | null;
        color: string;
        icon: string | null;
        status: string;
        order: number;
    } | null;
}>();

const isEditing = props.category !== null;

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Categorías', href: categorias.index() },
        ] as BreadcrumbItem[],
    },
});

const form = useForm({
    name: props.category?.name ?? '',
    slug: props.category?.slug ?? '',
    description: props.category?.description ?? '',
    color: props.category?.color ?? '#C7AA7D',
    icon: props.category?.icon ?? '',
    status: props.category?.status ?? 'active',
    order: props.category?.order ?? 0,
});

function submit() {
    if (isEditing && props.category) {
        form.put(categorias.update(props.category.id).url);
    } else {
        form.post(categorias.store().url);
    }
}
</script>

<template>
    <Head :title="isEditing ? 'Editar categoría' : 'Nueva categoría'" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:max-w-2xl md:p-6">
        <h1 class="text-xl font-semibold">
            {{ isEditing ? 'Editar categoría' : 'Nueva categoría' }}
        </h1>

        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="name">Nombre</Label>
                <Input id="name" v-model="form.name" required />
                <p v-if="form.errors.name" class="text-sm text-destructive">
                    {{ form.errors.name }}
                </p>
            </div>

            <div class="grid gap-2">
                <Label for="slug"
                    >Slug (opcional, se genera automáticamente)</Label
                >
                <Input id="slug" v-model="form.slug" placeholder="negocios" />
                <p v-if="form.errors.slug" class="text-sm text-destructive">
                    {{ form.errors.slug }}
                </p>
            </div>

            <div class="grid gap-2">
                <Label for="description">Descripción</Label>
                <Textarea
                    id="description"
                    v-model="form.description"
                    rows="3"
                />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-2">
                    <Label for="color">Color editorial</Label>
                    <div class="flex items-center gap-2">
                        <input
                            id="color"
                            v-model="form.color"
                            type="color"
                            class="h-9 w-12 rounded border"
                        />
                        <Input v-model="form.color" class="font-mono" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="order">Orden</Label>
                    <Input
                        id="order"
                        v-model.number="form.order"
                        type="number"
                        min="0"
                    />
                </div>
            </div>

            <div class="grid gap-2">
                <Label>Estado</Label>
                <Select v-model="form.status">
                    <SelectTrigger class="w-48">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="active">Activa</SelectItem>
                        <SelectItem value="inactive">Inactiva</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    {{ isEditing ? 'Guardar cambios' : 'Crear categoría' }}
                </Button>
                <Button as-child variant="ghost">
                    <Link :href="categorias.index()">Cancelar</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
