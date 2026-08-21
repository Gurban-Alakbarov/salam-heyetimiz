import { type FormEvent, useState } from 'react'
import { Navigate, useLocation, useNavigate } from 'react-router-dom'
import { AlertCircle, KeyRound, Loader2 } from 'lucide-react'
import { useAuth } from '@/auth/useAuth'
import { ApiError } from '@/lib/api'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

interface ChallengeState {
  challengeToken?: string
  email?: string
}

export function TwoFactorPage() {
  const { verify2fa } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const state = location.state as ChallengeState | null

  const [code, setCode] = useState('')
  const [isRecovery, setIsRecovery] = useState(false)
  const [error, setError] = useState<ApiError | null>(null)
  const [loading, setLoading] = useState(false)

  if (!state?.challengeToken) {
    return <Navigate to="/login" replace />
  }

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    setError(null)
    setLoading(true)
    try {
      await verify2fa(state.challengeToken as string, code.trim(), isRecovery)
      navigate('/', { replace: true })
    } catch (err) {
      setError(err instanceof ApiError ? err : new ApiError(0))
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-background p-4">
      <Card className="w-full max-w-sm">
        <CardHeader className="items-center text-center">
          <div className="mb-2 flex h-12 w-12 items-center justify-center rounded-xl bg-primary text-primary-foreground">
            <KeyRound className="h-6 w-6" />
          </div>
          <CardTitle>İki-faktorlu doğrulama</CardTitle>
          <CardDescription>{state.email}</CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={submit} className="space-y-4" noValidate>
            {error && (
              <Alert variant="destructive">
                <AlertCircle className="h-4 w-4" />
                <AlertDescription>{error.message}</AlertDescription>
              </Alert>
            )}
            <div className="space-y-2">
              <Label htmlFor="code">{isRecovery ? 'Bərpa kodu' : 'Doğrulama kodu (6 rəqəm)'}</Label>
              <Input
                id="code"
                inputMode={isRecovery ? 'text' : 'numeric'}
                autoComplete="one-time-code"
                autoFocus
                value={code}
                onChange={(e) => setCode(e.target.value)}
                placeholder={isRecovery ? '0000000000' : '000000'}
                required
              />
            </div>
            <Button type="submit" className="w-full" disabled={loading || code.trim().length < 6}>
              {loading && <Loader2 className="h-4 w-4 animate-spin" />}
              Təsdiqlə
            </Button>
          </form>
          <div className="mt-4 flex items-center justify-between text-xs">
            <button
              type="button"
              className="text-primary hover:underline"
              onClick={() => {
                setIsRecovery((v) => !v)
                setCode('')
                setError(null)
              }}
            >
              {isRecovery ? 'TOTP kodu ilə daxil ol' : 'Bərpa kodu istifadə et'}
            </button>
            <button type="button" className="text-muted-foreground hover:underline" onClick={() => navigate('/login')}>
              Geri
            </button>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
