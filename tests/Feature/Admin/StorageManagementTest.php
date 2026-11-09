<?php

use App\Enums\UserRole;
use App\Models\StorageProvider;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToWriteFile;

function storageAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

function storageEditor(): User
{
    return User::factory()->create(['role' => UserRole::Editor]);
}

function synologyProvider(): StorageProvider
{
    return StorageProvider::query()->where('key', 'synology')->firstOrFail();
}

function localProvider(): StorageProvider
{
    return StorageProvider::query()->where('key', 'local')->firstOrFail();
}

/**
 * @return array<string, mixed>
 */
function storagePanelProvider(object $test, string $key): array
{
    $props = $test->actingAs(storageAdmin())->get('/admin/almacenamiento')->assertOk()->original->getData()['page']['props'];

    return collect($props['providers'])->firstWhere('key', $key);
}

beforeEach(function () {
    StorageProvider::query()->create(['key' => 'local', 'label' => 'Almacenamiento local', 'is_active' => true, 'config' => ['disk' => 'media_local']]);
    // Fila heredada de la etapa SFTP: sus valores siguen en la base de datos
    // pero nunca se leen ni se envían al navegador.
    StorageProvider::query()->create(['key' => 'synology', 'label' => 'Synology NAS', 'is_active' => false, 'config' => [
        'disk' => 'media_synology',
        'host' => 'nas-heredado.tailnet.ts.net',
        'username' => 'usuario-heredado',
        'password' => 'super-secreto',
        'private_key_path' => '/home/deploy/.ssh/llave-secreta',
    ]]);
});

test('guests cannot access the storage panel', function () {
    $this->get('/admin/almacenamiento')->assertRedirect('/login');
});

test('an editor cannot access, test or activate providers', function () {
    $editor = storageEditor();
    $synology = synologyProvider();

    $this->actingAs($editor)->get('/admin/almacenamiento')->assertForbidden();
    $this->actingAs($editor)->post("/admin/almacenamiento/{$synology->id}/probar")->assertForbidden();
    $this->actingAs($editor)->post("/admin/almacenamiento/{$synology->id}/activar")->assertForbidden();

    expect($synology->refresh()->is_active)->toBeFalse();
    expect($synology->last_tested_at)->toBeNull();
});

test('a correctly mounted NAS is reported as available with its real capacity', function () {
    fakeSynologyMount();

    $synology = storagePanelProvider($this, 'synology');

    expect($synology['status'])->toBe('available');
    expect($synology['canActivate'])->toBeTrue();
    expect($synology['space'])->toHaveKeys(['totalBytes', 'freeBytes', 'usedBytes']);
    expect($synology['space']['usedBytes'])->toBe($synology['space']['totalBytes'] - $synology['space']['freeBytes']);
    expect($synology['details'])->toMatchArray([
        'name' => 'SynologyExtra',
        'address' => '100.67.89.20',
        'protocol' => 'SMB/CIFS 3.0',
        'share' => 'MrInsight',
    ]);
});

test('the panel props follow the contract and never expose credentials', function () {
    fakeSynologyMount();

    $response = $this->actingAs(storageAdmin())->get('/admin/almacenamiento');
    $providers = $response->original->getData()['page']['props']['providers'];

    foreach ($providers as $provider) {
        expect(array_keys($provider))->toEqualCanonicalizing([
            'id', 'key', 'label', 'isActive', 'lastTestedAt', 'lastTestResult',
            'filesCount', 'filesBytes', 'space', 'status', 'canActivate', 'details',
        ]);
    }

    $local = collect($providers)->firstWhere('key', 'local');
    expect($local['details'])->toBeNull();
    expect($local['status'])->toBe('available');
    expect($local['canActivate'])->toBeFalse(); // ya está activo

    $json = json_encode($providers);
    foreach (['super-secreto', 'usuario-heredado', 'nas-heredado', 'llave-secreta', 'password', 'username', 'privateKey', '"host"'] as $secret) {
        expect($json)->not->toContain($secret);
    }
});

test('an empty mountpoint directory without a mount entry is disconnected', function () {
    $root = fakeSynologyMount(mounted: false);

    $synology = storagePanelProvider($this, 'synology');
    expect($synology['status'])->toBe('disconnected');
    expect($synology['canActivate'])->toBeFalse();
    // Sin montaje no se reporta el disco del VPS como si fuera el NAS.
    expect($synology['space'])->toBeNull();

    $this->actingAs(storageAdmin())->post('/admin/almacenamiento/'.synologyProvider()->id.'/probar')->assertRedirect();

    $result = synologyProvider()->last_test_result;
    expect($result['status'])->toBe('disconnected');
    expect($result['checks']['mounted'])->toBeFalse();
    expect($result['checks']['write'])->toBeNull();
    expect(filesUnder($root))->toBe([]);
});

test('a missing mountpoint path is disconnected', function () {
    $root = fakeSynologyMount();
    rmdir($root);

    $this->actingAs(storageAdmin())->post('/admin/almacenamiento/'.synologyProvider()->id.'/probar');

    expect(synologyProvider()->last_test_result['status'])->toBe('disconnected');
    expect(is_dir($root))->toBeFalse();
});

test('a mount from a different SMB source is disconnected', function () {
    $root = fakeSynologyMount(source: '//100.64.0.99/OtroRecurso');

    expect(storagePanelProvider($this, 'synology')['status'])->toBe('disconnected');

    $this->actingAs(storageAdmin())->post('/admin/almacenamiento/'.synologyProvider()->id.'/probar');

    $result = synologyProvider()->last_test_result;
    expect($result['status'])->toBe('disconnected');
    expect($result['checks']['mounted'])->toBeTrue();
    expect($result['checks']['source'])->toBeFalse();
    expect(filesUnder($root))->toBe([]);
});

test('a non SMB filesystem at the mountpoint is disconnected', function () {
    fakeSynologyMount(fstype: 'ext4');

    expect(storagePanelProvider($this, 'synology')['status'])->toBe('disconnected');
});

test('the mount source comparison ignores case, trailing slashes and escaped spaces', function () {
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'mrinsight nas '.uniqid();
    fakeSynologyMount(source: '//100.67.89.20/mrinsight/', fstype: 'smb3', root: $root);

    expect(storagePanelProvider($this, 'synology')['status'])->toBe('available');
});

test('a full test of a mounted NAS writes, reads and deletes a probe file', function () {
    $root = fakeSynologyMount();

    $this->actingAs(storageAdmin())
        ->post('/admin/almacenamiento/'.synologyProvider()->id.'/probar')
        ->assertRedirect()
        ->assertSessionHas('success');

    $result = synologyProvider()->last_test_result;
    expect($result['status'])->toBe('available');
    expect($result['checks'])->toBe(['mounted' => true, 'source' => true, 'write' => true, 'read' => true, 'delete' => true]);
    expect($result['checkedAt'])->toBeString();
    expect(filesUnder($root))->toBe([]); // el archivo de prueba siempre se limpia
});

test('a read mismatch on the NAS is reported as write_error', function () {
    fakeSynologyMount();

    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('put')->andReturn(true);
    $disk->shouldReceive('get')->andReturn('otro-contenido');
    $disk->shouldReceive('exists')->andReturn(true);
    $disk->shouldReceive('delete')->atLeast()->once()->andReturn(true);
    Storage::set('media_synology', $disk);

    $this->actingAs(storageAdmin())->post('/admin/almacenamiento/'.synologyProvider()->id.'/probar')->assertSessionHas('error');

    $result = synologyProvider()->last_test_result;
    expect($result['status'])->toBe('write_error');
    expect($result['checks']['write'])->toBeTrue();
    expect($result['checks']['read'])->toBeFalse();
});

test('a permission denied write on the NAS is reported as permission_error without leaking details', function () {
    fakeSynologyMount();

    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('put')->andThrow(UnableToWriteFile::atLocation('.storage-check/x.tmp', 'fopen(): Permission denied password=super-secreto'));
    $disk->shouldReceive('exists')->andReturn(false);
    Storage::set('media_synology', $disk);

    $this->actingAs(storageAdmin())->post('/admin/almacenamiento/'.synologyProvider()->id.'/probar');

    $result = synologyProvider()->last_test_result;
    expect($result['status'])->toBe('permission_error');
    expect($result['checks']['write'])->toBeFalse();
    expect(json_encode($result))->not->toContain('super-secreto');
});

test('SMB credentials are managed by the OS: the credentials endpoint no longer exists', function () {
    $synology = synologyProvider();
    $before = $synology->config;

    $this->actingAs(storageAdmin())->put("/admin/almacenamiento/{$synology->id}", [
        'host' => 'nas.tailnet.ts.net',
        'username' => 'zuriel',
        'auth_method' => 'password',
        'password' => 'nuevo-secreto',
    ])->assertMethodNotAllowed();

    expect($synology->refresh()->config)->toBe($before);
});

test('activating synology is refused while the NAS is unavailable and nothing changes', function () {
    fakeSynologyMount(mounted: false);
    $synology = synologyProvider();

    $this->actingAs(storageAdmin())
        ->post("/admin/almacenamiento/{$synology->id}/activar")
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($synology->refresh()->is_active)->toBeFalse();
    expect(localProvider()->is_active)->toBeTrue();
    expect($synology->last_test_result['status'])->toBe('disconnected');
    expect($synology->last_tested_at)->not->toBeNull();
});

test('activating synology is allowed when the NAS passes the full check', function () {
    fakeSynologyMount();
    $synology = synologyProvider();

    $this->actingAs(storageAdmin())
        ->post("/admin/almacenamiento/{$synology->id}/activar")
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($synology->refresh()->is_active)->toBeTrue();
    expect($synology->last_test_result['status'])->toBe('available');
    expect(localProvider()->is_active)->toBeFalse();
});

test('the local provider can be reactivated even when the NAS is down', function () {
    fakeSynologyMount(mounted: false);
    StorageProvider::query()->update(['is_active' => false]);
    synologyProvider()->update(['is_active' => true]);

    $this->actingAs(storageAdmin())->post('/admin/almacenamiento/'.localProvider()->id.'/activar')->assertSessionHas('success');

    expect(localProvider()->is_active)->toBeTrue();
    expect(synologyProvider()->is_active)->toBeFalse();
});

test('testing the local provider writes and reads back a real probe file', function () {
    Storage::fake('media_local');
    $local = localProvider();

    $this->actingAs(storageAdmin())
        ->post("/admin/almacenamiento/{$local->id}/probar")
        ->assertRedirect();

    $result = $local->refresh()->last_test_result;
    expect($result['status'])->toBe('available');
    expect($result['checks'])->toBe(['mounted' => true, 'source' => true, 'write' => true, 'read' => true, 'delete' => true]);
    expect($local->last_tested_at)->not->toBeNull();
});

test('legacy test results are passed through untouched', function () {
    fakeSynologyMount();
    $legacy = ['status' => 'pending', 'message' => 'Synology pendiente de configuración.', 'connect' => false, 'read' => false, 'write' => false, 'checkedAt' => '2026-01-01T00:00:00+00:00'];
    synologyProvider()->update(['last_test_result' => $legacy]);

    expect(storagePanelProvider($this, 'synology')['lastTestResult'])->toBe($legacy);
});
