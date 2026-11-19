import {readdir, stat, mkdir, rename} from 'node:fs/promises';
import {dirname, join, relative, resolve} from 'node:path';
import {fileURLToPath} from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
export const frontendRoot = resolve(here, '..');

async function existsAsFile(path) {
  try { return (await stat(path)).isFile(); }
  catch (error) { if (error?.code === 'ENOENT') return false; throw error; }
}

/**
 * TypeScript `tsc -b` (with emit enabled) can create sibling .js files.
 * Extensionless Vite imports may then load stale JS instead of TS/TSX.
 * Only match a JS file when its corresponding TS/TSX source exists.
 */
export async function scanShadowedSources(root = frontendRoot) {
  const found = [];
  async function visit(dir) {
    let entries;
    try { entries = await readdir(dir, {withFileTypes: true}); }
    catch (error) { if (error?.code === 'ENOENT') return; throw error; }
    for (const entry of entries) {
      const path = join(dir, entry.name);
      if (entry.isDirectory()) { await visit(path); continue; }
      if (!entry.isFile()) continue;
      const variants = entry.name.endsWith('.js')
        ? [path.slice(0, -3) + '.ts', path.slice(0, -3) + '.tsx']
        : entry.name.endsWith('.jsx')
          ? [path.slice(0, -4) + '.tsx', path.slice(0, -4) + '.ts']
          : [];
      for (const source of variants) {
        if (await existsAsFile(source)) {
          found.push({file: path, source, relativePath: relative(root, path)});
          break;
        }
      }
    }
  }
  await visit(join(root, 'src'));
  // Vite's default auto-detection may prefer vite.config.js to vite.config.ts.
  for (const name of ['vite.config', 'vitest.config', 'playwright.config']) {
    for (const ext of ['.js', '.jsx']) {
      const file = join(root, name + ext);
      if (!await existsAsFile(file)) continue;
      for (const srcExt of ['.ts', '.tsx']) {
        const source = join(root, name + srcExt);
        if (await existsAsFile(source)) {
          found.push({file, source, relativePath: relative(root, file)});
          break;
        }
      }
    }
  }
  return found.sort((a, b) => a.relativePath.localeCompare(b.relativePath));
}

/** Move, never delete, only conflicting companions. Preserve a rollback copy. */
export async function quarantineShadowedSources(root, matches) {
  if (!matches.length) return null;
  const stamp = new Date().toISOString().replaceAll(':', '-').replaceAll('.', '-') + '-' + process.pid;
  const backupRoot = join(root, '.generated-js-quarantine', stamp);
  for (const match of matches) {
    const destination = join(backupRoot, match.relativePath);
    await mkdir(dirname(destination), {recursive: true});
    await rename(match.file, destination);
    // Optional sourcemap from the generated JS file; keep it with the backup.
    if (await existsAsFile(match.file + '.map')) {
      await rename(match.file + '.map', destination + '.map');
    }
  }
  return backupRoot;
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const matches = await scanShadowedSources();
  if (process.argv.includes('--quarantine')) {
    if (!matches.length) {
      console.log('No shadowing JavaScript companions found. No files moved.');
    } else {
      console.log(`Found ${matches.length} JavaScript file(s) shadowing TypeScript sources:`);
      for (const match of matches) console.log(`  ${match.relativePath}`);
      const backup = await quarantineShadowedSources(frontendRoot, matches);
      console.log(`Moved conflicting files to backup: ${relative(frontendRoot, backup)}`);
      console.log('Your .ts/.tsx files were NOT changed. Do not deploy the quarantine folder.');
    }
  } else if (matches.length) {
    console.error(`Found ${matches.length} stale JS companion(s) that can override TypeScript source:`);
    for (const match of matches) console.error(`  ${match.relativePath} -> ${relative(frontendRoot, match.source)}`);
    console.error('Run npm run clean:shadowed to MOVE them to a timestamped backup, then rerun the build.');
    process.exitCode = 1;
  } else {
    console.log('No JavaScript files shadow TypeScript sources or Vite configuration.');
  }
}
