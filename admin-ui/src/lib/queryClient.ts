import { QueryClient } from '@tanstack/react-query'
import { ApiError } from './api'

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: (count, err) => {
        // never retry 4xx (validation/auth/forbidden/not-found); retry transient errors twice
        if (err instanceof ApiError && err.status >= 400 && err.status < 500) return false
        return count < 2
      },
      staleTime: 30_000,
      refetchOnWindowFocus: false,
    },
    mutations: {
      retry: false,
    },
  },
})
