import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const root = new URL('../dist/', import.meta.url);
const read = (path) => readFileSync(new URL(path, root), 'utf8');

const llms = read('llms.txt');
assert.match(llms, /^# ProcurePilot AI/m);
assert.match(llms, /\]\(https:\/\/github\.com/);

const ard = JSON.parse(read('.well-known/ard.json'));
assert.ok(Array.isArray(ard.entries), 'ARD manifest must contain an entries array');
assert.equal(ard.entries.length, 0, 'Do not advertise an unimplemented agent/API');

const sitemap = read('sitemap.xml');
assert.match(sitemap, /https:\/\/procurepilot-ai-nine\.vercel\.app\/engineering/);
assert.match(read('robots.txt'), /^Sitemap:/m);
console.log('Public metadata verified in dist/: llms.txt, ARD JSON, robots.txt, sitemap.xml');
