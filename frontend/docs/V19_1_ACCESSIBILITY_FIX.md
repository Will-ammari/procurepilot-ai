# ProcurePilot AI v19.1 — accessibility and lint corrections

This patch overlays **v19**, without replacing Laravel, existing tests, or the lockfile.

## What changed

- Reduced WCAG axe `color-contrast` violations reported for the login and dashboard by increasing contrast in the **actual CSS** for the primary action, brand badge, KPI labels, pipeline labels, snapshot markers, links, and priorities. Accessibility checks remain enabled.
- Replaced `repo.departments(user)` with `repo.departments()` because `user` was not used at the repository boundary. Updated the single matching caller. This fixes the ESLint unused-argument error without weakening lint rules.
- Added `scripts/contrast-tokens.test.mjs` (six contrast checks and one wiring check) and included it in `npm run test:tooling`.

Contrast checks use the WCAG relative-luminance calculation for explicit token foreground/background pairs. They do **not** replace browser-side axe tests, and they are not proof of complete WCAG compliance.

## Apply

The ZIP contains paths starting at `procurepilot-ai/`. Extract it into `D:\` so those paths overlay your existing `D:\procurepilot-ai\` files. Back up or commit your local changes first. Do **not** delete your project or run `git reset --hard`.

From PowerShell:

```powershell
cd D:\procurepilot-ai\frontend
npm run format
npm run lint
npm run format:check
npm run typecheck
npm run test
npm run test:tooling
npm run build
npm run test:e2e
npm audit
```

If `format:check` still reports `src/services/demo.ts`, format that file once more and retry:

```powershell
npx prettier --write src/services/demo.ts
npx prettier --check src/services/demo.ts
npm run format:check
```

Do not bypass formatting checks. If it still fails, share the exact two command outputs for diagnosis.

## Expected outcome

- ESLint unused parameter error should be eliminated.
- Color-contrast failures previously seen on `/login` and the authenticated dashboard should be addressed by these CSS rules, but this is **not verified until Playwright/axe runs locally**.
- `npm run test:tooling` should now have **10 tests** (previous 3 + 7 new). This number is verified in the patch workspace.
- Existing 11 unit tests should remain intact; the patch does not modify the feature behavior. Validate on your machine.

No backend API logic, data migrations, secrets, or npm dependency versions were changed.
