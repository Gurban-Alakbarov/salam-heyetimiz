import { Link } from 'react-router-dom'
import { ShieldAlert } from 'lucide-react'
import { Button } from '@/components/ui/button'

/** Shown (HTTP-403 equivalent) when an admin opens a page their role/permissions do not allow. */
export function ForbiddenPage() {
  return (
    <div className="flex min-h-[60vh] flex-col items-center justify-center gap-4 text-center">
      <div className="flex h-14 w-14 items-center justify-center rounded-full bg-destructive/10 text-destructive">
        <ShieldAlert className="h-7 w-7" />
      </div>
      <div className="space-y-1">
        <h1 className="text-2xl font-semibold">403 — İcazə yoxdur</h1>
        <p className="text-sm text-muted-foreground">Bu səhifəyə baxmaq üçün icazəniz yoxdur.</p>
      </div>
      <Button asChild variant="outline">
        <Link to="/">İdarə panelinə qayıt</Link>
      </Button>
    </div>
  )
}
