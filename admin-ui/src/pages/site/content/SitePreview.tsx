import { useState } from 'react'
import { Eye, Monitor, Smartphone } from 'lucide-react'
import { type SiteLang, useSitePreview } from '@/api/siteContent'
import { EmptyState, ErrorState, LoadingState } from '@/components/states'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Select } from '@/components/ui/select'
import { LANGS } from './model'

const PHONE_WIDTH = 390

/**
 * The saved draft as a visitor would see it. The frame is sandboxed: this is edited content, and it must not run
 * scripts inside the panel, which holds an admin's session.
 */
export function SitePreview({ documentKey, dirty }: { documentKey: string; dirty: boolean }) {
  const [open, setOpen] = useState(false)
  const [lang, setLang] = useState<SiteLang>('az')
  const [phone, setPhone] = useState(false)
  const preview = useSitePreview(documentKey, lang, open)

  return (
    <>
      <Button type="button" variant="outline" size="sm" disabled={dirty} title={dirty ? 'Əvvəlcə yadda saxlayın' : undefined} onClick={() => setOpen(true)}>
        <Eye className="h-4 w-4" /> Önbaxış
      </Button>
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="flex h-[92vh] max-w-[95vw] flex-col">
          <DialogHeader>
            <DialogTitle>Önbaxış</DialogTitle>
            <DialogDescription>Yadda saxlanmış mətn. Bu görünüş hələ saytda deyil.</DialogDescription>
          </DialogHeader>
          <div className="flex flex-wrap items-center gap-2">
            <Select className="w-32" aria-label="Dil" value={lang} onChange={(e) => setLang(e.target.value as SiteLang)}>
              {LANGS.map((code) => <option key={code} value={code}>{code.toUpperCase()}</option>)}
            </Select>
            <Button type="button" variant={phone ? 'outline' : 'secondary'} size="sm" aria-pressed={!phone} onClick={() => setPhone(false)}><Monitor className="h-4 w-4" /> Kompüter</Button>
            <Button type="button" variant={phone ? 'secondary' : 'outline'} size="sm" aria-pressed={phone} onClick={() => setPhone(true)}><Smartphone className="h-4 w-4" /> Telefon</Button>
          </div>
          <div className="min-h-0 flex-1 overflow-hidden rounded-md border bg-muted">
            {preview.isLoading ? <LoadingState /> : preview.isError ? <ErrorState error={preview.error} onRetry={() => preview.refetch()} /> : preview.data ? (
              <iframe
                title="Önbaxış"
                sandbox=""
                srcDoc={preview.data}
                className="mx-auto h-full bg-white"
                style={{ width: phone ? PHONE_WIDTH : '100%' }}
              />
            ) : (
              <EmptyState title="Bu səhifə saytda görünməyəcək" description="Aktiv kampaniya olmayanda kampaniyalar səhifəsi yoxdur." />
            )}
          </div>
        </DialogContent>
      </Dialog>
    </>
  )
}
