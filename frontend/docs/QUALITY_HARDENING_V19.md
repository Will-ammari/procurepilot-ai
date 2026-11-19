# ProcurePilot AI — v19 Quality Hardening

## Changes

- Demo **role identity only** is persisted in `sessionStorage` (versioned `id`/`role`, validated against synthetic users). Browser refresh restores access to the existing URL. Logout clears the role. **API tokens remain memory-only**.
- Authentication, login, app shell, purchase request list, creation form, detail view and quote/actions were separated by responsibility. Existing route contracts and backend were not changed.
- Added accessible skip link, higher-contrast secondary text, focus-visible styles, keyboard-accessible mobile overlay, improved dialog focus trap and named dialog, reduced-motion support.
- Added `/engineering` case study and visible GitHub source links on the public demo.
- Added ESLint + Prettier configs, WCAG axe audits and Playwright session/keyboard regression coverage.

## Testing on your machine (PowerShell)

```powershell
cd D:\procurepilot-ai\frontend
npm install
npm run format
npm run lint
npm run typecheck
npm run test
npm run test:tooling
npm run build
npx playwright install chromium
npm run test:e2e
npm audit
```

`npm install` **must** update `package-lock.json`: the ZIP does not include a generated lockfile because it wasn't available from the latest local environment. Commit both `package.json` and `package-lock.json` before pushing; GitHub Actions uses `npm ci`. CI now also **requires** `npm run lint` and `npm run format:check`, so run both successfully before push.

## Boundaries

The axe suite has not run on your workstation yet. If it reports a real accessibility violation, fix the application, not the assertion. It currently fails for **critical/serious** WCAG A/AA findings and reports through Playwright traces; complete audits and manual review should also address moderate findings. Tests cover the **simulated demo**, not the Laravel authorization API.

`npm run format` will format all TypeScript, CSS and selected configuration files. Formatting may produce a large diff due to the previous condensed source; keep it in a dedicated formatting commit for ease of review.

Note: do not deploy `.env`, `node_modules`, generated JS, local backups or test artifacts.
