import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { test } from 'node:test';

const cssUrl = new URL('../src/styles/accessibility.css', import.meta.url);
const css = readFileSync(fileURLToPath(cssUrl), 'utf8');

function value(name) {
  const match = css.match(new RegExp(`(?:^|[\\s{;])--${name}\\s*:\\s*(#[\\da-fA-F]{6})\\s*;`));
  assert.ok(match, `Missing contrast design token --${name}`);
  return match[1];
}
function luminance(hex) {
  const values = [1, 3, 5].map((offset) => parseInt(hex.slice(offset, offset + 2), 16) / 255);
  const linear = values.map((v) => (v <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4));
  return linear[0] * 0.2126 + linear[1] * 0.7152 + linear[2] * 0.0722;
}
function contrast(foreground, background) {
  const [bright, dark] = [luminance(foreground), luminance(background)].sort((a, b) => b - a);
  return (bright + 0.05) / (dark + 0.05);
}

const pairs = [
  ['primary button', 'a11y-button-text', 'a11y-primary-button'],
  ['login badge', 'a11y-brand-badge-text', 'a11y-brand-badge-surface'],
  ['secondary labels', 'a11y-secondary-ink', null],
  ['links', 'a11y-link-ink', null],
  ['snapshot badge', 'a11y-snapshot-ink', 'a11y-snapshot-surface'],
  ['urgent priority', 'a11y-urgent-ink', null],
];

for (const [label, ink, surface] of pairs) {
  test(`WCAG AA normal text: ${label} >= 4.5:1`, () => {
    const ratio = contrast(value(ink), surface ? value(surface) : '#ffffff');
    assert.ok(ratio >= 4.5, `${label}: contrast ${ratio.toFixed(2)}:1 is below 4.5:1`);
  });
}

test('primary button and label styles consume the verified contrast tokens', () => {
  assert.match(css, /\.btn--primary\s*\{[^}]*var\(--a11y-primary-button\)/s);
  assert.match(css, /\.kpi__top\s*>\s*span:first-child\s*,[\s\S]*?var\(--a11y-secondary-ink\)/);
});
