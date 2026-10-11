import { ref } from 'vue';
import { apiFetch } from '@/lib/http';
import multimediaSesiones from '@/routes/admin/multimedia-sesiones';

export interface UploadedMediaResult {
    id: number;
    filename: string;
    type: string;
    url: string | null;
    status: string;
}

export interface ChunkedUploadProgress {
    file: File;
    sentBytes: number;
    totalBytes: number;
    percent: number;
    status: 'uploading' | 'completed' | 'error' | 'aborted';
}

/**
 * Sube un archivo grande en fragmentos sucesivos, cada uno su propia
 * petición HTTP corta (no depende de una única request PHP de larga
 * duración). El progreso reportado es real: bytes efectivamente
 * confirmados por el servidor en cada fragmento, no simulado.
 */
export function useChunkedUpload() {
    const progress = ref<ChunkedUploadProgress | null>(null);

    async function upload(
        file: File,
        collectionPath?: string,
    ): Promise<UploadedMediaResult> {
        progress.value = {
            file,
            sentBytes: 0,
            totalBytes: file.size,
            percent: 0,
            status: 'uploading',
        };

        const startResponse = await apiFetch(multimediaSesiones.store().url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                filename: file.name,
                mimeType: file.type || 'application/octet-stream',
                totalSize: file.size,
                collection: collectionPath,
            }),
        });

        if (!startResponse.ok) {
            progress.value.status = 'error';
            throw new Error('No se pudo iniciar la sesión de subida.');
        }

        const { uuid, chunkSize } = await startResponse.json();

        let offset = 0;
        while (offset < file.size) {
            const chunk = file.slice(offset, offset + chunkSize);
            const formData = new FormData();
            formData.append('chunk', chunk, file.name);

            const chunkResponse = await apiFetch(
                multimediaSesiones.fragmentos(uuid).url,
                {
                    method: 'POST',
                    body: formData,
                },
            );

            if (!chunkResponse.ok) {
                progress.value.status = 'error';
                throw new Error('Falló la subida de un fragmento.');
            }

            const result = await chunkResponse.json();
            offset += chunk.size;

            progress.value = {
                ...progress.value,
                sentBytes: result.receivedSize,
                percent: result.progress,
            };
        }

        const completeResponse = await apiFetch(
            multimediaSesiones.completar(uuid).url,
            { method: 'POST' },
        );
        if (!completeResponse.ok) {
            progress.value.status = 'error';
            throw new Error('No se pudo finalizar la subida.');
        }

        const { media } = await completeResponse.json();
        progress.value = {
            ...progress.value,
            percent: 100,
            status: 'completed',
        };

        return media;
    }

    return { progress, upload };
}
