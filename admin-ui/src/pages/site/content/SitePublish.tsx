import { useState } from 'react'
import { Loader2, Rocket, Undo2 } from 'lucide-react'
import { type SiteRelease, usePublishSite, useRollbackSite, useSitePublishStatus } from '@/api/siteContent'
import { ConfirmDialog } from '@/components/ConfirmDialog'
import { ErrorState, LoadingState } from '@/components/states'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { useToast } from '@/components/ui/toast'
import { ApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/format'

export function PublishButton() {
  const [open, setOpen] = useState(false)
  const status = useSitePublishStatus()
  const publish = usePublishSite()
  const { toast } = useToast()
  const changed = status.data?.changed ?? []
  const missing = status.data?.translations_missing ?? 0
  const stale = status.data?.translations_stale ?? 0
  const unfinished = status.data?.unfinished ?? []

  const confirm = () => {
    publish.mutate(undefined, {
      onSuccess: () => {
        setOpen(false)
        toast({ variant: 'success', title: 'Sayt yayımlandı' })
      },
      onError: (err) => toast({ variant: 'destructive', title: 'Yayımlanmadı', description: err instanceof ApiError ? err.message : undefined }),
    })
  }

  return (
    <>
      <Button type="button" disabled={changed.length === 0} onClick={() => setOpen(true)}>
        <Rocket className="h-4 w-4" /> Yayımla{changed.length > 0 ? ` (${changed.length})` : ''}
      </Button>
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>Sayt yayımlansın?</DialogTitle>
            <DialogDescription>Bütün yadda saxlanmış dəyişikliklər dərhal saytda görünəcək.</DialogDescription>
          </DialogHeader>
          <ul className="max-h-60 list-inside list-disc overflow-y-auto text-sm">
            {changed.map((page) => <li key={page.key}>{page.title}</li>)}
          </ul>
          {unfinished.length > 0 && (
            <Alert variant="destructive"><AlertDescription>Bəzi səhifələrin adı boşdur. Onları doldurmadan yayımlamaq olmaz.</AlertDescription></Alert>
          )}
          {(missing > 0 || stale > 0) && (
            <Alert>
              <AlertDescription>
                Tərcümələr tam deyil: {missing > 0 ? `${missing} çatışmır` : ''}{missing > 0 && stale > 0 ? ', ' : ''}{stale > 0 ? `${stale} yenilənməlidir` : ''}.
                {missing > 0 ? ' Çatışmayan yerlərdə azərbaycanca mətn görünəcək.' : ''}
              </AlertDescription>
            </Alert>
          )}
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setOpen(false)}>İmtina</Button>
            <Button type="button" disabled={publish.isPending || unfinished.length > 0} onClick={confirm}>
              {publish.isPending && <Loader2 className="h-4 w-4 animate-spin" />}
              Yayımla
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}

const kindLabel = (release: SiteRelease) => (release.kind === 'rebuild' ? 'Avtomatik yeniləmə' : (release.admin ?? 'Yayım'))

export function ReleaseList() {
  const status = useSitePublishStatus()
  const rollback = useRollbackSite()
  const { toast } = useToast()
  const [target, setTarget] = useState<SiteRelease | null>(null)

  const confirm = () => {
    if (!target) return
    rollback.mutate(target.id, {
      onSuccess: () => {
        setTarget(null)
        toast({ variant: 'success', title: 'Sayt geri qaytarıldı' })
      },
      onError: (err) => toast({ variant: 'destructive', title: 'Xəta', description: err instanceof ApiError ? err.message : undefined }),
    })
  }

  return (
    <Card>
      <CardHeader><CardTitle className="text-base">Yayım tarixçəsi</CardTitle></CardHeader>
      <CardContent>
        {status.isLoading ? <LoadingState /> : status.isError ? <ErrorState error={status.error} onRetry={() => status.refetch()} /> : (
          <ul className="divide-y">
            {status.data?.releases.map((release) => (
              <li key={release.id} className="flex items-center justify-between gap-3 py-2 text-sm">
                <p>
                  {formatDateTime(release.created_at)}, {kindLabel(release)}
                  {release.live && <Badge variant="success" className="ml-2">Saytdadır</Badge>}
                </p>
                {!release.live && (
                  <Button type="button" variant="outline" size="sm" onClick={() => setTarget(release)}><Undo2 className="h-4 w-4" /> Geri qaytar</Button>
                )}
              </li>
            ))}
          </ul>
        )}
      </CardContent>
      <ConfirmDialog
        open={target !== null}
        onOpenChange={(value) => { if (!value) setTarget(null) }}
        title="Sayt bu yayıma qaytarılsın?"
        description="Sayt dərhal həmin görünüşə qayıdacaq. Yadda saxlanmış mətnlər silinmir, onlar yayımlanmamış kimi qalır."
        confirmLabel="Geri qaytar"
        loading={rollback.isPending}
        onConfirm={confirm}
      />
    </Card>
  )
}
