import { Navigate } from 'react-router-dom'

// Security settings have moved into the unified Settings module (Settings → Security tab).
// Kept as a redirect so old /security links keep working.
export function SecurityPage() {
  return <Navigate to="/settings?tab=security" replace />
}
