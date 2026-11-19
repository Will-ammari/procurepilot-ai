import {readFile} from 'node:fs/promises';
import {fileURLToPath} from 'node:url';
import {resolve, dirname, join} from 'node:path';
import {scanShadowedSources} from './shadowed-sources.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const app = await readFile(join(root, 'src/app/App.tsx'), 'utf8');
const config = await readFile(join(root, 'vite.config.ts'), 'utf8');
const testConfig = await readFile(join(root, 'vitest.config.ts'), 'utf8');
const lazyFeatures = [...app.matchAll(/lazy\s*\(\s*\(\s*\)\s*=>\s*import\(\s*['"]\.\.\/features\//g)].length;
const problems = [];
if (lazyFeatures < 5 || !app.includes('<Suspense')) {
  problems.push(`App.tsx contains ${lazyFeatures} lazy page imports; expected at least 5 and a Suspense boundary.`);
}
if (!/manifest\s*:\s*true/.test(config)) {
  problems.push('vite.config.ts does not enable build.manifest.');
}
if (!testConfig.includes('src/**/*.test.') || !testConfig.includes('e2e/**')) {
  problems.push('vitest.config.ts must isolate unit tests from Playwright e2e.');
}
const shadowed = await scanShadowedSources(root);
if (shadowed.length) {
  problems.push(`${shadowed.length} shadowing .js file(s) detected: ${shadowed.map(x => x.relativePath).join(', ')}. Run npm run clean:shadowed first.`);
}
if (problems.length) {
  console.error('Overlay verification FAILED:\n - ' + problems.join('\n - '));
  process.exitCode = 1;
} else {
  console.log(`Overlay verified: ${lazyFeatures} lazy route entries, Vite manifest enabled, Vitest isolated, no stale JS sources.`);
}
