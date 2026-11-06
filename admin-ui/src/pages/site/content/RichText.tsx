import { useEffect, useRef } from 'react'
import type { ClipboardEvent, KeyboardEvent, ReactNode } from 'react'
import { Bold, Heading3, Italic, Link2, List } from 'lucide-react'
import { cn } from '@/lib/utils'

const AREA = 'min-h-10 w-full rounded-b-md border border-input bg-card px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring [&_a]:text-primary [&_a]:underline [&_em]:text-primary [&_h3]:mb-1 [&_h3]:font-semibold [&_li]:ml-4 [&_li]:list-disc [&_p]:mb-2'

function Tool({ title, onUse, children }: { title: string; onUse: () => void; children: ReactNode }) {
  return (
    <button
      type="button"
      title={title}
      aria-label={title}
      // Keeps the selection in the text while the button is pressed.
      onMouseDown={(e) => { e.preventDefault(); onUse() }}
      className="rounded p-1 text-muted-foreground hover:bg-accent hover:text-accent-foreground"
    >
      {children}
    </button>
  )
}

/**
 * Text with simple formatting. The server keeps only the tags it allows, so this stays a small editor: `inline` gives
 * bold, emphasis and links; `rich` adds subheadings and lists.
 */
export function RichText({ value, onChange, rich, label }: { value: string; onChange: (html: string) => void; rich: boolean; label: string }) {
  const area = useRef<HTMLDivElement>(null)
  const last = useRef<string | null>(null)

  // Only write into the area when the text came from outside (a load, a restore), never while someone types.
  useEffect(() => {
    if (area.current && value !== last.current) {
      area.current.innerHTML = value
      last.current = value
    }
  }, [value])

  const emit = () => {
    const html = area.current?.innerHTML ?? ''
    const next = area.current?.textContent?.trim() ? html : ''
    last.current = next
    onChange(next)
  }

  const run = (command: string, argument?: string) => {
    area.current?.focus()
    document.execCommand('defaultParagraphSeparator', false, 'p')
    document.execCommand(command, false, argument)
    emit()
  }

  const addLink = () => {
    const href = window.prompt('Keçid ünvanı (məsələn /contact və ya https://…)')
    if (href) run('createLink', href.trim())
  }

  const onPaste = (e: ClipboardEvent<HTMLDivElement>) => {
    e.preventDefault()
    document.execCommand('insertText', false, e.clipboardData.getData('text/plain'))
  }

  const onKeyDown = (e: KeyboardEvent<HTMLDivElement>) => {
    if (!rich && e.key === 'Enter') {
      e.preventDefault()
      document.execCommand('insertLineBreak')
    }
  }

  return (
    <div>
      <div className="flex gap-0.5 rounded-t-md border border-b-0 border-input bg-muted/40 px-1 py-0.5">
        <Tool title="Qalın" onUse={() => run('bold')}><Bold className="h-3.5 w-3.5" /></Tool>
        <Tool title="Vurğu" onUse={() => run('italic')}><Italic className="h-3.5 w-3.5" /></Tool>
        <Tool title="Keçid" onUse={addLink}><Link2 className="h-3.5 w-3.5" /></Tool>
        {rich && <Tool title="Alt başlıq" onUse={() => run('formatBlock', 'h3')}><Heading3 className="h-3.5 w-3.5" /></Tool>}
        {rich && <Tool title="Siyahı" onUse={() => run('insertUnorderedList')}><List className="h-3.5 w-3.5" /></Tool>}
      </div>
      <div
        ref={area}
        role="textbox"
        aria-label={label}
        aria-multiline={rich}
        contentEditable
        suppressContentEditableWarning
        onInput={emit}
        onPaste={onPaste}
        onKeyDown={onKeyDown}
        className={cn(AREA, rich && 'max-h-96 min-h-24 overflow-y-auto')}
      />
    </div>
  )
}
