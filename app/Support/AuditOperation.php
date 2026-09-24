<?php

namespace App\Support;

class AuditOperation
{
    /** @var array<string, string> */
    private const EXACT_OPERATIONS = [
        'dashboard' => 'dashboard.view',
        'busca' => 'search.view',
        'agenda.concluido' => 'agenda.complete',
        'criancas.documentos.store' => 'child_document.create',
        'criancas.familiares.store' => 'family_member.create',
        'documentos.destroy' => 'child_document.delete',
        'familiares.destroy' => 'family_member.delete',
        'pias.anexos.destroy' => 'pia_attachment.delete',
        'auditoria.index' => 'audit.list',
        'profile.edit' => 'profile.view',
        'profile.update' => 'profile.update',
        'profile.destroy' => 'profile.delete',
        'password.confirm' => 'password.confirm',
        'password.update' => 'password.update',
        'verification.notice' => 'email_verification.view',
        'verification.verify' => 'email_verification.verify',
        'verification.send' => 'email_verification.send',
        'logout' => 'session.logout',
    ];

    /** @var array<string, string> */
    private const RESOURCE_NAMES = [
        'agenda' => 'agenda',
        'criancas' => 'child',
        'pias' => 'pia',
        'visitas-tecnicas' => 'technical_visit',
        'reports' => 'report',
        'pertences' => 'belonging_receipt',
        'setores' => 'sector',
        'equipe' => 'account',
    ];

    /** @var array<string, string> */
    private const RESOURCE_ACTIONS = [
        'index' => 'list',
        'create' => 'create_form',
        'store' => 'create',
        'show' => 'view',
        'edit' => 'edit_form',
        'update' => 'update',
        'destroy' => 'delete',
        'pdf' => 'download_pdf',
    ];

    public static function denied(?string $routeName): string
    {
        if ($routeName !== null && isset(self::EXACT_OPERATIONS[$routeName])) {
            return 'access.denied.'.self::EXACT_OPERATIONS[$routeName];
        }

        if ($routeName !== null) {
            [$resource, $action] = array_pad(explode('.', $routeName, 2), 2, null);

            if (isset(self::RESOURCE_NAMES[$resource], self::RESOURCE_ACTIONS[$action])) {
                return 'access.denied.'.self::RESOURCE_NAMES[$resource].'.'.self::RESOURCE_ACTIONS[$action];
            }
        }

        return 'access.denied.protected_route';
    }
}
