<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * El proveedor de almacenamiento activo (hoy, el NAS Synology montado por
 * SMB) no está disponible para escribir o leer: el recurso compartido no
 * está montado, apunta a otro origen o no tiene permisos de escritura.
 *
 * El mensaje es siempre seguro para mostrarse al usuario: nunca incluye
 * credenciales, rutas internas ni el texto de la excepción original. Nunca
 * se hace "fallback" a otro proveedor: la subida falla de forma explícita.
 */
class StorageUnavailableException extends \RuntimeException
{
    public const DEFAULT_MESSAGE = 'El almacenamiento Synology no está disponible en este momento. No se guardó ningún archivo; inténtalo de nuevo más tarde o avisa al administrador.';

    public function __construct(string $message = self::DEFAULT_MESSAGE, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Subidas por API/fetch (JSON) reciben un 503 con el mensaje; formularios
     * Inertia regresan a la página anterior con el toast de error.
     */
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $this->getMessage()], 503, ['Retry-After' => '60']);
        }

        return back()->with('error', $this->getMessage());
    }

    /**
     * Ya se registra en bitácora (con contexto seguro) donde se lanza.
     */
    public function report(): bool
    {
        return true;
    }
}
