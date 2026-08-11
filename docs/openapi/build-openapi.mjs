// Builds docs/openapi/openapi.json by merging v1.yaml + v1.extra.yaml and
// normalising every path to an absolute, executable URL.
//
//   node docs/openapi/build-openapi.mjs
//
// js-yaml is resolved from admin-ui/node_modules (no extra install needed).
import { createRequire } from 'module'
import { readFileSync, writeFileSync } from 'fs'
import { fileURLToPath, URL } from 'url'

const require = createRequire(new URL('../../admin-ui/package.json', import.meta.url))
const yaml = require('js-yaml')

const here = (p) => fileURLToPath(new URL(p, import.meta.url))

const base = yaml.load(readFileSync(here('./v1.yaml'), 'utf8'))
const extra = yaml.load(readFileSync(here('./v1.extra.yaml'), 'utf8'))

// ---- deep-merge the two documents ----
const merged = { ...base }
merged.paths = { ...(base.paths || {}), ...(extra.paths || {}) }
merged.components = { ...(base.components || {}) }
merged.components.schemas = { ...(base.components?.schemas || {}), ...(extra.components?.schemas || {}) }

// tags: concat + dedupe by name (base order first)
const tagByName = new Map()
for (const t of [...(base.tags || []), ...(extra.tags || [])]) {
  if (!tagByName.has(t.name)) tagByName.set(t.name, t)
}
merged.tags = [...tagByName.values()]

// ---- normalise paths to absolute, executable URLs ----
// /admin/X -> /admin/v1/X ; /.well-known/X stays ; everything else -> /v1/X
const normalise = (p) => {
  if (p.startsWith('/.well-known/')) return p
  if (p === '/admin' || p.startsWith('/admin/')) return '/admin/v1' + p.slice('/admin'.length)
  return '/v1' + p
}
const normPaths = {}
for (const [p, item] of Object.entries(merged.paths)) {
  const np = normalise(p)
  if (normPaths[np]) {
    // merge two source paths that collapse to the same URL (e.g. base + extra both touching it)
    normPaths[np] = { ...normPaths[np], ...item }
  } else {
    normPaths[np] = item
  }
}
merged.paths = normPaths

// ---- version + servers ----
merged.openapi = '3.1.0'
merged.info = { ...merged.info, version: '1.3.0' }
merged.servers = [
  { url: 'https://api.salamheyetimiz.com', description: 'Production' },
  { url: 'http://localhost:8000', description: 'Local development' },
]

const out = here('./openapi.json')
writeFileSync(out, JSON.stringify(merged, null, 2) + '\n', 'utf8')

const opCount = Object.values(merged.paths).reduce(
  (n, item) => n + Object.keys(item).filter((k) => ['get', 'post', 'put', 'patch', 'delete'].includes(k)).length,
  0,
)
console.log(
  `openapi.json written: ${Object.keys(merged.paths).length} paths, ${opCount} operations, ` +
    `${Object.keys(merged.components.schemas).length} schemas, ${merged.tags.length} tags, v${merged.info.version}`,
)
