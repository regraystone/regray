<?php

namespace App\Enums;

enum ConversationMemberRole: string
{
    /** Reads and sends. */
    case Member = 'member';

    /** Reads and sends, and created the group. */
    case Admin = 'admin';

    /**
     * A pool member's row on an unassigned Regray Team thread. It only holds a
     * read pointer: access comes from the policy, and the row is deleted when
     * the thread is claimed.
     */
    case Viewer = 'viewer';
}
