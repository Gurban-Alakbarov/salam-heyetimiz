<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Enums\CampaignAudienceScope;
use App\Domain\Roster\Queries\ResidentQuery;

/**
 * Resolves an admin-campaign AudienceSpec to its distinct recipient user_ids (ADMIN_SPEC D6). Every scope
 * routes through the residents directory read model, so recipients dedupe to distinct user_id and the
 * complex_manager scope is enforced identically to the directory (the caller passes its complexScopeId; a
 * scoped caller can never reach outside its complex). No new audience rule — only the four LOCKED scopes.
 */
final class CampaignAudienceResolver
{
    public function __construct(private readonly ResidentQuery $residents) {}

    /**
     * @param  array<string, mixed>  $audience  validated AudienceSpec (scope + optional user_ids/filter/complex_id)
     * @return array<int, int> distinct recipient user_ids (scoped)
     */
    public function resolve(array $audience, ?int $scopeComplexId): array
    {
        $scope = CampaignAudienceScope::from((string) $audience['scope']);
        $filter = is_array($audience['filter'] ?? null) ? $audience['filter'] : [];

        return match ($scope) {
            CampaignAudienceScope::AllUsers => $this->residents->resolveAudienceUserIds(
                null, null, null, null, null, $scopeComplexId,
            ),
            CampaignAudienceScope::UserIds => $this->residents->resolveAudienceUserIds(
                null, null, null, null, $this->intIds($audience['user_ids'] ?? []), $scopeComplexId,
            ),
            CampaignAudienceScope::Complex => $this->residents->resolveAudienceUserIds(
                isset($audience['complex_id']) ? (int) $audience['complex_id'] : null,
                null, null, null, null, $scopeComplexId,
            ),
            CampaignAudienceScope::Filter => $this->residents->resolveAudienceUserIds(
                isset($filter['complex_id']) ? (int) $filter['complex_id'] : null,
                isset($filter['q']) ? (string) $filter['q'] : null,
                isset($filter['role']) ? (string) $filter['role'] : null,
                isset($filter['subscription_status']) ? (string) $filter['subscription_status'] : null,
                null,
                $scopeComplexId,
            ),
        };
    }

    /** @return array<int, int> */
    private function intIds(mixed $ids): array
    {
        return is_array($ids) ? array_values(array_map('intval', $ids)) : [];
    }
}
