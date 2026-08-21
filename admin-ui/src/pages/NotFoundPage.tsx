import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/button'

export function NotFoundPage() {
  return (
    <div className="flex min-h-[60vh] flex-col items-center justify-center gap-4 text-center">
      <p className="text-5xl font-bold text-muted-foreground">404</p>
      <p className="text-lg font-medium">Səhifə tapılmadı</p>
      <Button asChild>
        <Link to="/">İdarə panelinə qayıt</Link>
      </Button>
    </div>
  )
}
