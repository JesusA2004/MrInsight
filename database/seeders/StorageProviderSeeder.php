<?php

namespace Database\Seeders;

use App\Models\StorageProvider;
use Illuminate\Database\Seeder;

class StorageProviderSeeder extends Seeder
{
    /**
     * `is_active` solo se fija al crear la fila: volver a sembrar en un
     * servidor existente nunca cambia el proveedor activo (ni activa
     * Synology ni regresa en silencio al disco local).
     */
    public function run(): void
    {
        $this->seed('local', 'Almacenamiento local', true, [
            'disk' => 'media_local',
            'root_path' => 'mr-insight',
        ]);

        // El proveedor Synology queda registrado pero inactivo: se activa a
        // mano desde /admin/almacenamiento y solo si la prueba completa del
        // montaje SMB pasa. Las credenciales SMB las administra Linux (fstab),
        // así que aquí solo se guarda el disco de Laravel.
        $this->seed('synology', 'Synology NAS (vía Tailscale)', false, [
            'disk' => 'media_synology',
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function seed(string $key, string $label, bool $activeOnCreate, array $config): void
    {
        $provider = StorageProvider::query()->firstOrNew(['key' => $key]);

        if (! $provider->exists) {
            $provider->is_active = $activeOnCreate;
        }

        $provider->fill(['label' => $label, 'config' => $config])->save();
    }
}
