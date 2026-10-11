<script setup lang="ts">
import { Loader2, Search, Upload } from '@lucide/vue';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { apiFetch } from '@/lib/http';
import multimedia from '@/routes/admin/multimedia';

export interface PickedMedia {
    id: number;
    url: string | null;
    filename: string;
    type: string;
    altText: string | null;
}

const open = defineModel<boolean>('open', { default: false });

const { type } = defineProps<{ type?: 'image' | 'video' }>();

const emit = defineEmits<{ select: [media: PickedMedia] }>();

const query = ref('');
const results = ref<PickedMedia[]>([]);
const loading = ref(false);
const uploading = ref(false);
const uploadProgress = ref(0);
const fileInput = ref<HTMLInputElement | null>(null);

async function search() {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        if (query.value) params.set('q', query.value);
        if (type) params.set('tipo', type);

        const response = await apiFetch(
            `${multimedia.buscar().url}?${params.toString()}`,
        );
        const payload = await response.json();
        results.value = payload.data;
    } finally {
        loading.value = false;
    }
}

watch(open, (value) => {
    if (value) search();
});

let debounceTimer: ReturnType<typeof setTimeout> | undefined;
watch(query, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(search, 300);
});

function pick(media: PickedMedia) {
    emit('select', media);
    open.value = false;
}

function triggerUpload() {
    fileInput.value?.click();
}

function handleFileChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;

    uploading.value = true;
    uploadProgress.value = 0;

    const formData = new FormData();
    formData.append('file', file);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', multimedia.store().url);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.setRequestHeader('Accept', 'application/json');
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    if (match)
        xhr.setRequestHeader('X-XSRF-TOKEN', decodeURIComponent(match[1]));

    xhr.upload.onprogress = (e) => {
        if (e.lengthComputable) {
            uploadProgress.value = Math.round((e.loaded / e.total) * 100);
        }
    };

    xhr.onload = () => {
        uploading.value = false;
        if (xhr.status === 201) {
            const payload = JSON.parse(xhr.responseText);
            results.value = [payload.media, ...results.value];
            pick(payload.media);
        }
        if (fileInput.value) fileInput.value.value = '';
    };

    xhr.onerror = () => {
        uploading.value = false;
    };

    xhr.send(formData);
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[85vh] max-w-3xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Biblioteca multimedia</DialogTitle>
            </DialogHeader>

            <div class="flex items-center gap-2">
                <div class="relative flex-1">
                    <Search
                        class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="query"
                        placeholder="Buscar por nombre de archivo…"
                        class="pl-8"
                    />
                </div>
                <Button
                    variant="outline"
                    :disabled="uploading"
                    @click="triggerUpload"
                >
                    <Loader2 v-if="uploading" class="size-4 animate-spin" />
                    <Upload v-else class="size-4" />
                    Subir {{ type === 'video' ? 'video' : 'archivo' }}
                </Button>
                <input
                    ref="fileInput"
                    type="file"
                    class="hidden"
                    :accept="type === 'video' ? 'video/*' : 'image/*'"
                    @change="handleFileChange"
                />
            </div>

            <p v-if="uploading" class="text-sm text-muted-foreground">
                Subiendo… {{ uploadProgress }}%
            </p>

            <div class="grid grid-cols-3 gap-3 sm:grid-cols-4">
                <button
                    v-for="media in results"
                    :key="media.id"
                    type="button"
                    class="group relative aspect-square overflow-hidden rounded-lg border bg-muted focus-visible:outline-2 focus-visible:outline-primary"
                    @click="pick(media)"
                >
                    <img
                        v-if="media.type === 'image' && media.url"
                        :src="media.url"
                        :alt="media.altText ?? media.filename"
                        class="size-full object-cover transition-transform group-hover:scale-105"
                    />
                    <video
                        v-else-if="media.type === 'video' && media.url"
                        :src="media.url"
                        class="size-full object-cover"
                        muted
                    />
                    <span
                        class="absolute inset-x-0 bottom-0 truncate bg-black/60 px-1.5 py-1 text-[10px] text-white"
                    >
                        {{ media.filename }}
                    </span>
                </button>
            </div>

            <p
                v-if="!loading && results.length === 0"
                class="py-8 text-center text-sm text-muted-foreground"
            >
                No se encontraron archivos. Sube uno nuevo para empezar.
            </p>
        </DialogContent>
    </Dialog>
</template>
