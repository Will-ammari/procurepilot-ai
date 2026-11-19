import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');
const url = 'https://procurepilot-ai-nine.vercel.app/';

test('landing HTML declares a canonical URL and a meaningful description', () => {
  const html = read('../index.html');
  assert.match(html, /<link rel="canonical" href="https:\/\/procurepilot-ai-nine\.vercel\.app\/"/);
  assert.match(html, /<meta name="description" content=".{80,}"/);
  assert.match(html, /<meta property="og:site_name" content="ProcurePilot AI"/);
});

test('llms.txt is static Markdown, linked, and accurately describes the demo boundary', () => {
  const markdown = read('../public/llms.txt');
  assert.match(markdown, /^# ProcurePilot AI\s*$/m);
  assert.match(markdown, /\]\(https:\/\/github\.com\/Will-ammari\/procurepilot-ai/);
  assert.match(markdown, /synthetic/i);
  assert.doesNotMatch(markdown, /<!doctype html>/i);
});

test('ARD manifest is JSON with no invented public agents', () => {
  const data = JSON.parse(read('../public/.well-known/ard.json'));
  assert.deepEqual(Object.keys(data), ['entries']);
  assert.deepEqual(data.entries, []);
});

test('robots and sitemap contain only public entry pages', () => {
  const robots = read('../public/robots.txt');
  const sitemap = read('../public/sitemap.xml');
  assert.ok(robots.includes(`Sitemap: ${url}sitemap.xml`));
  assert.match(sitemap, /<urlset xmlns="http:\/\/www\.sitemaps\.org\/schemas\/sitemap\/0\.9">/);
  assert.match(sitemap, /<loc>https:\/\/procurepilot-ai-nine\.vercel\.app\/engineering<\/loc>/);
  assert.doesNotMatch(sitemap, /\/requests|\/invoices|\/approvals/);
});
