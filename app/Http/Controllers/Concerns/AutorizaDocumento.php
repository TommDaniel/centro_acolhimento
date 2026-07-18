<?php

namespace App\Http\Controllers\Concerns;

/**
 * Regra de permissão da POC: admin pode tudo; servidor só altera
 * documentos do próprio setor (a visualização é livre para todos).
 */
trait AutorizaDocumento
{
    private function autorizarDocumento(object $documento): void
    {
        $user = request()->user();

        abort_unless(
            $user->is_admin || $documento->setor_id === $user->setor_id,
            403,
            'Você só pode alterar documentos do seu setor.'
        );
    }
}
