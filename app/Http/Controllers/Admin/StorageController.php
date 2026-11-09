<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StorageProviderKey;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\StorageProvider;
use App\Services\ActivityLogger;
use App\Services\Storage\StorageHealth;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel /admin/almacenamiento: muestra el estado real de cada proveedor
 * (local y Synology) y permite probarlo y activarlo.
 *
 * El NAS Synology se monta en el VPS por SMB/CIFS y sus credenciales las
 * administra Linux (fstab), así que aquí no se edita ninguna credencial.
 * Los valores SFTP heredados que pudieran quedar en `config` nunca se leen
 * ni se envían al navegador.
 */
class StorageController extends Controller
{
    public function __construct(private readonly StorageHealth $health) {}

    public function index(): Response
    {
        abort_unless(request()->user()?->isAdmin(), 403);

        $providers = StorageProvider::query()->orderBy('key')->get();

        return Inertia::render('admin/almacenamiento/Index', [
            'providers' => $providers->map(fn (StorageProvider $provider) => $this->present($provider))->values(),
        ]);
    }

    /**
     * Activar Synology exige una prueba completa en ese momento (montaje,
     * origen, escritura, lectura y borrado). Si no está disponible, el
     * proveedor activo no cambia: nunca se cambia de proveedor a medias.
     */
    public function activate(StorageProvider $provider, ActivityLogger $activity): RedirectResponse
    {
        abort_unless(request()->user()?->isAdmin(), 403);

        if ($provider->key === StorageProviderKey::Synology) {
            $result = $this->health->checkSynology();

            $provider->update([
                'last_tested_at' => now(),
                'last_test_result' => $result,
            ]);

            if ($result['status'] !== StorageHealth::AVAILABLE) {
                $activity->log('almacenamiento.activacion_rechazada', $provider, [
                    'provider' => $provider->key->value,
                    'result' => $result['status'],
                ]);

                return back()->with('error', 'No se activó SynologyExtra: '.$result['message']);
            }
        }

        StorageProvider::query()->whereKeyNot($provider->id)->update(['is_active' => false]);
        $provider->update(['is_active' => true]);

        $activity->log('almacenamiento.proveedor_activado', $provider, ['provider' => $provider->key->value]);

        return back()->with('success', "«{$provider->displayName()}» es ahora el proveedor activo.");
    }

    public function test(StorageProvider $provider, ActivityLogger $activity): RedirectResponse
    {
        abort_unless(request()->user()?->isAdmin(), 403);

        $result = $provider->key === StorageProviderKey::Local
            ? $this->health->checkLocal()
            : $this->health->checkSynology();

        $provider->update([
            'last_tested_at' => now(),
            'last_test_result' => $result,
        ]);

        $activity->log('almacenamiento.probado', $provider, [
            'provider' => $provider->key->value,
            'result' => $result['status'],
        ]);

        return back()->with(
            $result['status'] === StorageHealth::AVAILABLE ? 'success' : 'error',
            $result['message'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function present(StorageProvider $provider): array
    {
        $disk = $provider->diskName();
        $status = $this->health->liveStatus($provider->key);

        return [
            'id' => $provider->id,
            'key' => $provider->key->value,
            'label' => $provider->displayName(),
            'isActive' => $provider->is_active,
            'lastTestedAt' => $provider->last_tested_at?->toIso8601String(),
            'lastTestResult' => $provider->last_test_result,
            'filesCount' => Media::query()->where('disk', $disk)->count(),
            'filesBytes' => (int) Media::query()->where('disk', $disk)->sum('size'),
            'space' => $this->health->space($provider->key),
            'status' => $status,
            'canActivate' => ! $provider->is_active && $status === StorageHealth::AVAILABLE,
            'details' => $provider->key === StorageProviderKey::Synology ? $this->health->synologyDetails() : null,
        ];
    }
}
