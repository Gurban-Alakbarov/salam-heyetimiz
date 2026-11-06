import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'

export interface InboxCounts {
  applications: number
  site_leads: number
}

/** The red numbers in the sidebar. Refreshes every minute, so a new request shows up on any page. */
export function useInboxCounts(enabled: boolean) {
  return useQuery({
    queryKey: ['inbox-counts'],
    queryFn: async () => (await api.get<{ data: InboxCounts }>('/admin/v1/inbox-counts')).data.data,
    enabled,
    refetchInterval: 60_000,
  })
}
