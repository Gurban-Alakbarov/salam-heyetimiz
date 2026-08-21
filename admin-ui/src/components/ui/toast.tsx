import * as React from 'react'
import * as ToastPrimitive from '@radix-ui/react-toast'
import { X } from 'lucide-react'
import { cn } from '@/lib/utils'

type ToastVariant = 'default' | 'success' | 'destructive'

interface ToastOptions {
  title?: string
  description?: string
  variant?: ToastVariant
}

interface ToastItem extends ToastOptions {
  id: number
}

interface ToastContextValue {
  toast: (options: ToastOptions) => void
}

const ToastContext = React.createContext<ToastContextValue | null>(null)

export function useToast(): ToastContextValue {
  const ctx = React.useContext(ToastContext)
  if (!ctx) throw new Error('useToast must be used within <ToastProvider>')
  return ctx
}

let counter = 0

const variantClasses: Record<ToastVariant, string> = {
  default: 'border bg-card text-card-foreground',
  success: 'border-success/30 bg-success/10 text-success',
  destructive: 'border-destructive/30 bg-destructive/10 text-destructive',
}

export function ToastProvider({ children }: { children: React.ReactNode }) {
  const [items, setItems] = React.useState<ToastItem[]>([])

  const toast = React.useCallback((options: ToastOptions) => {
    counter += 1
    setItems((prev) => [...prev, { id: counter, variant: 'default', ...options }])
  }, [])

  const remove = React.useCallback((id: number) => {
    setItems((prev) => prev.filter((item) => item.id !== id))
  }, [])

  return (
    <ToastContext.Provider value={{ toast }}>
      <ToastPrimitive.Provider swipeDirection="right" duration={5000}>
        {children}
        {items.map((item) => (
          <ToastPrimitive.Root
            key={item.id}
            onOpenChange={(open) => {
              if (!open) remove(item.id)
            }}
            className={cn(
              'group pointer-events-auto relative flex w-full items-start justify-between gap-3 rounded-md p-4 shadow-lg data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:slide-in-from-right-full',
              variantClasses[item.variant ?? 'default'],
            )}
          >
            <div className="grid gap-1">
              {item.title && <ToastPrimitive.Title className="text-sm font-semibold">{item.title}</ToastPrimitive.Title>}
              {item.description && (
                <ToastPrimitive.Description className="text-sm opacity-90">{item.description}</ToastPrimitive.Description>
              )}
            </div>
            <ToastPrimitive.Close className="rounded-sm opacity-70 transition-opacity hover:opacity-100">
              <X className="h-4 w-4" />
            </ToastPrimitive.Close>
          </ToastPrimitive.Root>
        ))}
        <ToastPrimitive.Viewport className="fixed bottom-0 right-0 z-[100] flex max-h-screen w-full flex-col-reverse gap-2 p-4 sm:max-w-[400px]" />
      </ToastPrimitive.Provider>
    </ToastContext.Provider>
  )
}
