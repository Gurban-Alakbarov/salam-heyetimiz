import { ChevronLeft, ChevronRight } from 'lucide-react'
import { Button } from '@/components/ui/button'

export function CursorPagination({
  pageIndex,
  canPrev,
  hasMore,
  onPrev,
  onNext,
  disabled,
}: {
  pageIndex: number
  canPrev: boolean
  hasMore: boolean
  onPrev: () => void
  onNext: () => void
  disabled?: boolean
}) {
  return (
    <div className="flex items-center justify-between border-t px-4 py-3">
      <span className="text-xs text-muted-foreground">Səhifə {pageIndex}</span>
      <div className="flex items-center gap-2">
        <Button variant="outline" size="sm" onClick={onPrev} disabled={!canPrev || disabled}>
          <ChevronLeft className="h-4 w-4" />
          Əvvəlki
        </Button>
        <Button variant="outline" size="sm" onClick={onNext} disabled={!hasMore || disabled}>
          Növbəti
          <ChevronRight className="h-4 w-4" />
        </Button>
      </div>
    </div>
  )
}
