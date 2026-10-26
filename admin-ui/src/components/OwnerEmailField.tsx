import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

/**
 * K1 — the email the person will register with in the app. A phone number alone never hands an account
 * with device access over to whoever registers with it; the app's email code proves this email instead.
 */
export function OwnerEmailField({
  id,
  value,
  onChange,
  who,
}: {
  id: string
  value: string
  onChange: (value: string) => void
  who: string
}) {
  return (
    <div className="space-y-2">
      <Label htmlFor={id}>E-poçt (tövsiyə olunur)</Label>
      <Input id={id} type="email" value={value} onChange={(e) => onChange(e.target.value)} placeholder="ad@example.com" />
      <p className="text-xs text-muted-foreground">
        {who} tətbiqdə bu e-poçt ilə qeydiyyatdan keçəcək. E-poçt yazılmasa, təhlükəsizlik üçün hesabı yalnız
        telefonla aktivləşdirmək olmayacaq — sonra Sakinlər səhifəsindən də yazmaq olar.
      </p>
    </div>
  )
}
