<?php

use App\Enums\Permission;

/*
 * The front end's permission union (resources/js/layouts/workspace-layout.tsx)
 * mirrors App\Enums\Permission, so `usePermissions().can()` and the sidebar
 * are typed against every permission the server grants, and none it doesn't.
 */

it('lists every permission the server grants in the front end\'s union, and no other', function (): void {
    $layout = file_get_contents(dirname(__DIR__, 2).'/resources/js/layouts/workspace-layout.tsx');
    $start = strpos($layout, 'export const permissions = {');
    $end = strpos($layout, '} as const;', $start);
    preg_match_all("/:\\s*'([^']+)',/", substr($layout, $start, $end - $start), $matches);

    $mirrored = $matches[1];
    sort($mirrored);
    $granted = array_map(fn (Permission $permission): string => $permission->value, Permission::cases());
    sort($granted);

    expect($mirrored)->toBe($granted);
});
