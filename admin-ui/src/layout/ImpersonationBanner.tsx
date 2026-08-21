import { useState } from 'react'
import { Loader2, UserCheck } from 'lucide-react'
import { useAuth } from '@/auth/useAuth'
import { ApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { useToast } from '@/components/ui/toast'

/** Sticky banner shown while a super-admin is impersonating another admin, with one-click return. */
export function ImpersonationBanner() {
  const { admin, isImpersonating, stopImpersonating } = useAuth()
  const { toast } = useToast()
  const [loading, setLoading] = useState(false)

  if (!isImpersonating || !admin) return null

  const onStop = async () => {
    setLoading(true)
    try {
      await stopImpersonating()
      toast({ variant: 'success', title: 'Öz hesabınıza qayıtdınız' })
    } catch (err) {
      toast({ variant: 'destructive', title: 'Xəta', description: err instanceof ApiError ? err.message : undefined })
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="flex items-center justify-between gap-3 bg-amber-500/15 px-4 py-2 text-sm text-amber-900 dark:text-amber-200">
      <span className="flex items-center gap-2">
        <UserCheck className="h-4 w-4" />
        <strong>{admin.name}</strong> ({admin.email}) kimi baxırsınız.
      </span>
      <Button size="sm" variant="outline" disabled={loading} onClick={onStop}>
        {loading && <Loader2 className="h-4 w-4 animate-spin" />}
        Öz hesabıma qayıt
      </Button>
    </div>
  )
}
