import {readFile} from 'node:fs/promises';
import {fileURLToPath} from 'node:url';
import {dirname, join, resolve} from 'node:path';
import {gzipSync} from 'node:zlib';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../dist');
const manifestPath = join(root, '.vite', 'manifest.json');
let manifest;
try {
  manifest = JSON.parse(await readFile(manifestPath, 'utf8'));
} catch (error) {
  if (error?.code === 'ENOENT') {
    throw new Error('Missing Vite manifest at dist/.vite/manifest.json. Verify frontend/vite.config.ts has build: {manifest: true}, rerun `npm run check:overlay`, then rebuild.');
  }
  throw error;
}
const entries = Object.values(manifest);
const entry = manifest['src/main.tsx'] ?? entries.find(item => item.isEntry && item.file?.endsWith('.js'));
if (!entry?.file) throw new Error('The Vite manifest has no JavaScript application entry.');

// Traverse static imports once; dynamically imported route modules are excluded.
const staticFiles = new Set();
function walk(item) {
  if (!item?.file || staticFiles.has(item.file)) return;
  if (item.file.endsWith('.js')) staticFiles.add(item.file);
  for (const key of item.imports ?? []) walk(manifest[key]);
}
walk(entry);
const lazyRoutes = entries.filter(item => item.isDynamicEntry && item.file?.endsWith('.js'));
if (lazyRoutes.length === 0) {
  throw new Error('No lazy route chunks found in manifest. Confirm frontend/src/app/App.tsx was replaced by the v18.1 overlay.');
}
const allJsFiles = new Set(entries.map(item => item.file).filter(file => file?.endsWith('.js')));
async function sizeOf(file) {
  const contents = await readFile(join(root, file));
  return {raw: contents.length, gzip: gzipSync(contents).length};
}
async function aggregate(files) {
  let raw = 0, gzip = 0;
  for (const file of files) {
    const size = await sizeOf(file);
    raw += size.raw;
    gzip += size.gzip;
  }
  return {raw, gzip};
}
const kb = value => `${(value/1000).toFixed(2)} kB`;
const initial = await aggregate(staticFiles);
const all = await aggregate(allJsFiles);
const summary = {
  initialRawBytes: initial.raw,
  initialGzipBytes: initial.gzip,
  totalRawBytes: all.raw,
  totalGzipBytes: all.gzip,
  staticChunks: staticFiles.size,
  lazyRouteChunks: lazyRoutes.length,
  totalChunks: allJsFiles.size,
};
console.log('\nJavaScript bundle analysis (not network timing or Lighthouse metrics)');
console.log(`Initial JS (entry + static): ${kb(initial.raw)} raw / ${kb(initial.gzip)} gzip across ${staticFiles.size} chunks`);
console.log(`All JS (including lazy):    ${kb(all.raw)} raw / ${kb(all.gzip)} gzip across ${allJsFiles.size} chunks`);
console.log(`Lazy page modules: ${lazyRoutes.length}`);
for (const route of lazyRoutes) {
  const {raw, gzip} = await sizeOf(route.file);
  console.log(`  ${route.file}: ${kb(raw)} raw / ${kb(gzip)} gzip`);
}
console.log(`Previous unsplit entry reference: 583.09 kB raw / 181.43 kB gzip`);
console.log('Remember: initial JS is a transfer estimate, not measured LCP.');
console.log('Summary:', JSON.stringify(summary));
