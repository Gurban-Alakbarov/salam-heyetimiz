import { useEffect, useState } from 'react'
import { Loader2 } from 'lucide-react'
import type { ComplexFamilyMember, ComplexMemberRow } from '@/api/complexes'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'

export const personName = (p: { full_name: string | null; email: string | null; phone?: string | null; user_id: number }) =>
  p.full_name?.trim() || p.email || p.phone || `#${p.user_id}`

/** What was decided: `newHeadUserId` null = the whole family leaves (remove mode only). */
export interface FamilyDecision {
  newHeadUserId: number | null
  removeOldHead: boolean
}

/**
 * O7 — pick a family's new head. In remove mode (removing the head from the complex) the alternative is to
 * remove the whole family; otherwise the admin chooses whether the old head stays as a member (default).
 */
export function FamilyHeadDialog({
  head,
  removeMode,
  loading,
  onClose,
  onConfirm,
}: {
  head: ComplexMemberRow | null
  removeMode: boolean
  loading: boolean
  onClose: () => void
  onConfirm: (d: FamilyDecision) => void
}) {
  const members: ComplexFamilyMember[] = head?.family ?? []
  const [newHead, setNewHead] = useState<number | null>(null)
  const [wholeFamily, setWholeFamily] = useState(false)
  const [removeOldHead, setRemoveOldHead] = useState(false)

  useEffect(() => {
    setNewHead(head?.family?.[0]?.user_id ?? null)
    setWholeFamily(false)
    setRemoveOldHead(false)
  }, [head])

  const name = head ? personName(head) : ''

  return (
    <Dialog open={head !== null} onOpenChange={(open) => !open && !loading && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{removeMode ? 'Ailə başçısını kompleksdən çıxar' : 'Ailə başçısını dəyiş'}</DialogTitle>
          <DialogDescription>
            {removeMode
              ? `${name} ailə başçısıdır. Ailə üzvləri ilə nə olsun?`
              : `${name} ailəsini kim idarə etsin? Yeni başçı dərhal təyin olunur; üzvlərin girişi və ödənilmiş günləri dəyişmir.`}
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 py-2 text-sm">
          {removeMode && (
            <div className="space-y-2">
              <label className="flex items-center gap-2">
                <input type="radio" checked={!wholeFamily} onChange={() => setWholeFamily(false)} />
                Üzvlərdən biri yeni başçı olsun
              </label>
              <label className="flex items-center gap-2">
                <input type="radio" checked={wholeFamily} onChange={() => setWholeFamily(true)} />
                Bütün ailəni çıxar (üzvlərin girişi bağlanır, abunələri ləğv olunur)
              </label>
            </div>
          )}

          {!wholeFamily && (
            <div className="space-y-2">
              <Label>Yeni başçı</Label>
              {members.map((m) => (
                <label key={m.user_id} className="flex items-center gap-2">
                  <input type="radio" name="new-head" checked={newHead === m.user_id} onChange={() => setNewHead(m.user_id)} />
                  {personName(m)}
                </label>
              ))}
            </div>
          )}

          {!removeMode && (
            <div className="space-y-2 border-t pt-3">
              <label className="flex items-center gap-2">
                <input type="radio" checked={!removeOldHead} onChange={() => setRemoveOldHead(false)} />
                Köhnə başçı ailədə üzv qalsın
              </label>
              <label className="flex items-center gap-2">
                <input type="radio" checked={removeOldHead} onChange={() => setRemoveOldHead(true)} />
                Köhnə başçını kompleksdən çıxar
              </label>
            </div>
          )}
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onClose} disabled={loading}>İmtina</Button>
          <Button
            variant={removeMode ? 'destructive' : 'default'}
            disabled={loading || (!wholeFamily && newHead === null)}
            onClick={() => onConfirm({ newHeadUserId: wholeFamily ? null : newHead, removeOldHead })}
          >
            {loading && <Loader2 className="h-4 w-4 animate-spin" />}
            {removeMode ? 'Kompleksdən çıxar' : 'Başçını dəyiş'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
