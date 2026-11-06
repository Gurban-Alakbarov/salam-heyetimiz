import { useRef, useState } from 'react'
import { ImageOff, Loader2, Upload } from 'lucide-react'
import { useSiteMedia, useUploadSiteMedia } from '@/api/siteContent'
import { ApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { useToast } from '@/components/ui/toast'
import { ErrorState, LoadingState } from '@/components/states'
import { imageUrl } from './model'

const MAX_BYTES = 8 * 1024 * 1024

/** Shows the chosen image; the dialog picks another one or uploads a new one. */
export function ImagePicker({ value, onChange, siteUrl, clearable }: { value: string; onChange: (path: string) => void; siteUrl: string; clearable: boolean }) {
  const [open, setOpen] = useState(false)
  const media = useSiteMedia(open)
  const upload = useUploadSiteMedia()
  const { toast } = useToast()
  const input = useRef<HTMLInputElement>(null)

  const choose = (path: string) => {
    onChange(path)
    setOpen(false)
  }

  const send = async (file: File | undefined) => {
    if (!file) return
    if (file.size > MAX_BYTES) {
      toast({ variant: 'destructive', title: 'Şəkil çox böyükdür', description: 'Ən çoxu 8 MB.' })
      return
    }
    try {
      choose((await upload.mutateAsync(file)).path)
      toast({ variant: 'success', title: 'Şəkil yükləndi' })
    } catch (err) {
      toast({ variant: 'destructive', title: 'Xəta', description: err instanceof ApiError ? err.message : 'Yükləmə alınmadı.' })
    }
  }

  return (
    <div className="flex items-center gap-3">
      <div className="flex h-20 w-32 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted">
        {value ? <img src={imageUrl(value, siteUrl)} alt="" className="h-full w-full object-cover" /> : <ImageOff className="h-5 w-5 text-muted-foreground" />}
      </div>
      <div className="flex flex-wrap gap-2">
        <Button type="button" variant="outline" size="sm" onClick={() => setOpen(true)}>Şəkil seç</Button>
        {clearable && value && <Button type="button" variant="ghost" size="sm" onClick={() => onChange('')}>Sil</Button>}
      </div>

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="max-w-3xl">
          <DialogHeader>
            <DialogTitle>Şəkil seçin</DialogTitle>
            <DialogDescription>Yeni şəkil yükləyin və ya mövcud olanlardan birini seçin. JPG, PNG, WebP, ən çoxu 8 MB.</DialogDescription>
          </DialogHeader>
          <div>
            <input ref={input} type="file" accept=".jpg,.jpeg,.png,.webp" className="hidden" onChange={(e) => { void send(e.target.files?.[0]); e.target.value = '' }} />
            <Button type="button" variant="outline" size="sm" disabled={upload.isPending} onClick={() => input.current?.click()}>
              {upload.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Upload className="h-4 w-4" />}
              Şəkil yüklə
            </Button>
          </div>
          {media.isLoading ? <LoadingState /> : media.isError ? <ErrorState error={media.error} onRetry={() => media.refetch()} /> : (
            <div className="grid max-h-[60vh] grid-cols-2 gap-3 overflow-y-auto sm:grid-cols-3">
              {media.data?.map((image) => (
                <button key={image.path} type="button" onClick={() => choose(image.path)} className="overflow-hidden rounded-md border hover:ring-2 hover:ring-primary">
                  <img src={image.url} alt="" loading="lazy" className="h-28 w-full object-cover" />
                </button>
              ))}
            </div>
          )}
        </DialogContent>
      </Dialog>
    </div>
  )
}
