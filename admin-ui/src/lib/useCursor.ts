import { useCallback, useState } from 'react'

/** Forward/back navigation over an opaque cursor-paginated list. */
export function useCursor() {
  const [stack, setStack] = useState<(string | null)[]>([null])
  const cursor = stack[stack.length - 1]

  const next = useCallback((nextCursor: string | null) => {
    if (nextCursor) setStack((s) => [...s, nextCursor])
  }, [])

  const prev = useCallback(() => {
    setStack((s) => (s.length > 1 ? s.slice(0, -1) : s))
  }, [])

  const reset = useCallback(() => setStack([null]), [])

  return { cursor, next, prev, reset, canPrev: stack.length > 1, pageIndex: stack.length }
}
