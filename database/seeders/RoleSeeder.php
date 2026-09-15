<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles are bundles of atomic permissions.
 *
 * Code must check permissions, never roles: `$user->can('article.publish')`, not
 * `$user->hasRole('editor')`. Editorial reshapes roles without a deploy, and a
 * role check hard-codes today's org chart into the application.
 */
class RoleSeeder extends Seeder
{
    /** @var array<int, string> */
    private const PERMISSIONS = [
        'article.view',
        'article.create',
        'article.update.own',
        'article.update.any',
        'article.delete',
        'article.transition',
        'article.publish',
        'article.factcheck',
        'article.seo',
        'media.manage',
        'navigation.manage',
        'homepage.manage',
        'settings.manage',
        'users.manage',
        'roles.manage',
        'intelligence.configure',
        'intelligence.triage',
        'audit.view',
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const ROLES = [
        // super_admin is granted everything explicitly below.
        'super_admin' => [],

        'editor_in_chief' => [
            'article.view', 'article.create', 'article.update.own', 'article.update.any',
            'article.delete', 'article.transition', 'article.publish', 'article.factcheck',
            'article.seo', 'media.manage', 'navigation.manage', 'homepage.manage',
            'settings.manage', 'users.manage', 'intelligence.configure',
            'intelligence.triage', 'audit.view',
        ],

        'editor' => [
            'article.view', 'article.create', 'article.update.own', 'article.update.any',
            'article.transition', 'article.publish', 'article.seo', 'media.manage',
            'homepage.manage', 'intelligence.triage', 'audit.view',
        ],

        'writer' => [
            'article.view', 'article.create', 'article.update.own', 'article.transition',
            'media.manage',
        ],

        'researcher' => [
            'article.view', 'article.create', 'article.update.own', 'article.transition',
            'intelligence.triage',
        ],

        'fact_checker' => [
            'article.view', 'article.transition', 'article.factcheck',
        ],

        'seo' => [
            'article.view', 'article.update.any', 'article.transition', 'article.seo',
        ],

        'social' => [
            'article.view', 'media.manage',
        ],

        'video' => [
            'article.view', 'media.manage',
        ],

        'designer' => [
            'article.view', 'media.manage',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (self::ROLES as $name => $permissions) {
            $role = Role::findOrCreate($name, 'web');

            $role->syncPermissions(
                $name === 'super_admin' ? self::PERMISSIONS : $permissions,
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
