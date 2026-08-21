/** Drop undefined/null/empty values so they are not sent as query params. */
export function cleanParams(params: Record<string, unknown>): Record<string, string | number> {
  const out: Record<string, string | number> = {}
  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === '') continue
    out[key] = value as string | number
  }
  return out
}
