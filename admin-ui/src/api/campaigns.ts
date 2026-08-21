import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { cleanParams } from '@/lib/params'
import type {
  AudiencePreview,
  AudienceSpec,
  NotificationCampaign,
  NotificationCampaignCreate,
  Paginated,
} from '@/types/api'

export interface CampaignListParams {
  status?: string
  limit?: number
  cursor?: string | null
}

/** GET /admin/v1/notifications — adminListNotificationCampaigns (notifications.view; complex_manager scoped server-side). */
export function useCampaigns(params: CampaignListParams) {
  return useQuery({
    queryKey: ['campaigns', params],
    queryFn: async () =>
      (await api.get<Paginated<NotificationCampaign>>('/admin/v1/notifications', { params: cleanParams({ ...params }) })).data,
    placeholderData: keepPreviousData,
  })
}

/** GET /admin/v1/notifications/{id} — adminGetNotificationCampaign (notifications.view). */
export function useCampaign(id: number) {
  return useQuery({
    queryKey: ['campaign', id],
    queryFn: async () => (await api.get<NotificationCampaign>(`/admin/v1/notifications/${id}`)).data,
    enabled: Number.isFinite(id) && id > 0,
  })
}

/** POST /admin/v1/notifications/audience/preview — adminPreviewNotificationAudience (notifications.view; no side effects). */
export function usePreviewAudience() {
  return useMutation({
    mutationFn: async (audience: AudienceSpec) =>
      (await api.post<AudiencePreview>('/admin/v1/notifications/audience/preview', { audience })).data,
  })
}

/**
 * POST /admin/v1/notifications — adminSendNotification (notifications.send). Requires confirmed=true and a
 * per-send Idempotency-Key; the same key + body replays the original campaign server-side (no duplicate).
 */
export function useSendCampaign() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ input, idempotencyKey }: { input: NotificationCampaignCreate; idempotencyKey: string }) =>
      (
        await api.post<NotificationCampaign>('/admin/v1/notifications', input, {
          headers: { 'Idempotency-Key': idempotencyKey },
        })
      ).data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['campaigns'] })
    },
  })
}
