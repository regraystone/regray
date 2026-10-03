import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import type { Permission } from '@/layouts/workspace-layout';
import type { Auth } from '@/types';

export function usePermissions(): {
    can: (permission: Permission) => boolean;
    permissions: string[];
} {
    const { permissions } = usePage<{ auth: Auth }>().props.auth;

    const granted = useMemo(() => new Set(permissions), [permissions]);

    return {
        can: (permission) => granted.has(permission),
        permissions,
    };
}
