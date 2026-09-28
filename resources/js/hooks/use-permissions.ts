import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import type { Auth } from '@/types';

export function usePermissions(): {
    can: (permission: string) => boolean;
    permissions: string[];
} {
    const { permissions } = usePage<{ auth: Auth }>().props.auth;

    const granted = useMemo(() => new Set(permissions), [permissions]);

    return {
        can: (permission: string) => granted.has(permission),
        permissions,
    };
}
