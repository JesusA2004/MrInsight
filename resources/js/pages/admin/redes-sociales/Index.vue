<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import SocialIcon from '@/components/public/SocialIcon.vue';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import redesSociales from '@/routes/admin/redes-sociales';
import type { BreadcrumbItem } from '@/types';

interface SocialLinkRow {
    id: number;
    platform: string;
    username: string | null;
    url: string;
    is_visible: boolean;
    open_new_tab: boolean;
    order: number;
}

defineProps<{
    links: SocialLinkRow[];
    can: { create: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Redes sociales', href: redesSociales.index() },
        ] as BreadcrumbItem[],
    },
});

const platforms = [
    { value: 'instagram', label: 'Instagram' },
    { value: 'tiktok', label: 'TikTok' },
    { value: 'facebook', label: 'Facebook' },
    { value: 'youtube', label: 'YouTube' },
    { value: 'linkedin', label: 'LinkedIn' },
    { value: 'otro', label: 'Otra' },
];

const dialogOpen = ref(false);
const editingId = ref<number | null>(null);

const form = useForm({
    platform: 'instagram',
    username: '',
    url: '',
    is_visible: true,
    open_new_tab: true,
    order: 0,
});

function openCreate() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    dialogOpen.value = true;
}

function openEdit(link: SocialLinkRow) {
    editingId.value = link.id;
    form.platform = link.platform;
    form.username = link.username ?? '';
    form.url = link.url;
    form.is_visible = link.is_visible;
    form.open_new_tab = link.open_new_tab;
    form.order = link.order;
    form.clearErrors();
    dialogOpen.value = true;
}

function submit() {
    if (editingId.value) {
        form.put(redesSociales.update(editingId.value).url, { onSuccess: () => (dialogOpen.value = false) });
    } else {
        form.post(redesSociales.store().url, { onSuccess: () => (dialogOpen.value = false) });
    }
}

function destroy(id: number) {
    router.delete(redesSociales.destroy(id).url);
}

function toggleVisible(link: SocialLinkRow) {
    router.put(redesSociales.update(link.id).url, {
        platform: link.platform,
        username: link.username,
        url: link.url,
        is_visible: !link.is_visible,
        open_new_tab: link.open_new_tab,
        order: link.order,
    }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Redes sociales" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:max-w-2xl md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Redes sociales</h1>
                <p class="text-sm text-muted-foreground">
                    Solo las redes activas aparecen en el sitio público. No inventes enlaces: usa las cuentas reales.
                </p>
            </div>
            <Button v-if="can.create" @click="openCreate">
                <Plus class="size-4" />
                Agregar
            </Button>
        </div>

        <div v-if="links.length === 0" class="rounded-xl border border-dashed p-10 text-center text-muted-foreground">
            Aún no hay redes sociales configuradas.
        </div>

        <div v-else class="flex flex-col divide-y rounded-xl border">
            <div v-for="link in links" :key="link.id" class="flex items-center gap-3 p-4">
                <span class="flex size-9 items-center justify-center rounded-full bg-muted">
                    <SocialIcon :platform="link.platform" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="font-medium capitalize">{{ link.platform }}</p>
                    <p class="truncate text-xs text-muted-foreground">{{ link.url }}</p>
                </div>
                <Switch :checked="link.is_visible" @update:checked="() => toggleVisible(link)" />
                <Button variant="ghost" size="icon-sm" @click="openEdit(link)">
                    <Pencil class="size-4" />
                </Button>
                <Button variant="ghost" size="icon-sm" @click="destroy(link.id)">
                    <Trash2 class="size-4" />
                </Button>
            </div>
        </div>
    </div>

    <Dialog v-model:open="dialogOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ editingId ? 'Editar red social' : 'Agregar red social' }}</DialogTitle>
            </DialogHeader>
            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label>Plataforma</Label>
                    <Select v-model="form.platform">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="p in platforms" :key="p.value" :value="p.value">{{ p.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-2">
                    <Label for="username">Usuario (opcional)</Label>
                    <Input id="username" v-model="form.username" placeholder="@mrinsight" />
                </div>
                <div class="grid gap-2">
                    <Label for="url">URL completa</Label>
                    <Input id="url" v-model="form.url" placeholder="https://instagram.com/..." />
                    <p v-if="form.errors.url" class="text-sm text-destructive">{{ form.errors.url }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <Switch :checked="form.is_visible" @update:checked="(v: boolean) => (form.is_visible = v)" />
                    <Label>Visible en el sitio público</Label>
                </div>
                <DialogFooter>
                    <Button type="submit" :disabled="form.processing">Guardar</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
