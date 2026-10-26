<?php

namespace App\Domain\Notifications\Enums;

/**
 * Business notification types carried in the push `data.type` for client deep-link routing
 * (INVENTORY §1–§2; MOBILE_FLOW §3). Exactly the in-scope MVP + subscription types plus the admin
 * campaign type. Every value is backed by a real, already-emitted event (or the admin campaign path) —
 * no invented events (R-NOT-21). The template key is separate (INVENTORY): e.g. `visitor_link_used`
 * renders from `device.opened`, `system_announcement` from `system.admin_campaign`.
 */
enum NotificationType: string
{
    case VisitorLinkUsed = 'visitor_link_used';
    case SubscriptionExpiring = 'subscription_expiring';
    case SubscriptionExpired = 'subscription_expired';
    case SubscriptionActivated = 'subscription_activated';
    case SubscriptionRenewed = 'subscription_renewed';
    case SystemAnnouncement = 'system_announcement';
    /** O7 — the Komendant removed a family member from the complex (to the member and the head). */
    case FamilyMemberRemoved = 'family_member_removed';
    /** O7 — the Komendant handed a family to another member (to the whole family). */
    case FamilyHeadChanged = 'family_head_changed';
}
