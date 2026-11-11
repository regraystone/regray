<?php

namespace App\Enums;

/**
 * What a workspace notification is about, which says what its `target_id`
 * points to: a conversation, an inquiry or an appointment (schedule).
 * Mirrors `NotificationType` in lib/notification-api.ts and
 * chk_notifications_type.
 */
enum NotificationType: string
{
    case Message = 'message';
    case Inquiry = 'inquiry';
    case Schedule = 'schedule';
}
