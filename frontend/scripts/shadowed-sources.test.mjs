import {test} from 'node:test';
import {strict as assert} from 'node:assert';
import {mkdtemp, mkdir, readFile, writeFile, access, rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {scanShadowedSources, quarantineShadowedSources} from './shadowed-sources.mjs';

// Normalize separators for assertions only; do not change the production scanner.
const portablePath = path => path.replaceAll('\\', '/');

test('normalizes Windows and POSIX paths for portable assertions', () => {
  assert.equal(portablePath('src\\app\\App.js'), 'src/app/App.js');
  assert.equal(portablePath('src/app/App.js'), 'src/app/App.js');
});

test('detects and safely quarantines emitted JS while preserving original TypeScript', async () => {
  const root = await mkdtemp(join(tmpdir(), 'procurepilot-shadow-test-'));
  try {
    await mkdir(join(root, 'src', 'app'), {recursive: true});
    await writeFile(join(root, 'src', 'app', 'App.tsx'), 'export const App = 1;');
    await writeFile(join(root, 'src', 'app', 'App.js'), 'export const App = 0;');
    await writeFile(join(root, 'src', 'app', 'App.js.map'), '{}');
    await writeFile(join(root, 'src', 'app', 'handwritten.js'), 'export const intentional = true;');
    await writeFile(join(root, 'vite.config.ts'), 'export default {};');
    await writeFile(join(root, 'vite.config.js'), 'export default {};');
    const found = await scanShadowedSources(root);
    assert.deepEqual(found.map(x => portablePath(x.relativePath)).sort(), ['src/app/App.js', 'vite.config.js']);
    const backup = await quarantineShadowedSources(root, found);
    assert.match(await readFile(join(root, 'src', 'app', 'App.tsx'), 'utf8'), /App = 1/);
    assert.match(await readFile(join(root, 'src', 'app', 'handwritten.js'), 'utf8'), /intentional/);
    await access(join(backup, 'src', 'app', 'App.js'));
    await access(join(backup, 'src', 'app', 'App.js.map'));
    await access(join(backup, 'vite.config.js'));
    assert.deepEqual(await scanShadowedSources(root), []);
  } finally {
    await rm(root, {recursive: true, force: true});
  }
});

test('reports clean projects without removing handwritten JavaScript', async () => {
  const root = await mkdtemp(join(tmpdir(), 'procurepilot-clean-test-'));
  try {
    await mkdir(join(root, 'src'), {recursive: true});
    await writeFile(join(root, 'src', 'helper.js'), 'const x = 1;');
    assert.deepEqual(await scanShadowedSources(root), []);
    assert.equal(await quarantineShadowedSources(root, []), null);
    await access(join(root, 'src', 'helper.js'));
  } finally {
    await rm(root, {recursive: true, force: true});
  }
});
