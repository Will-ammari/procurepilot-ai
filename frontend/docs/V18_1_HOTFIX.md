# v18.1 quality gate hotfix

## What failed in the supplied 19 Nov 2026 Windows log?

- `npm run test`: Vitest accidentally collected Playwright specs under `e2e/`. Different test frameworks must not share test discovery.
- `npm run test:e2e`: Two identical assertions expected `Pending approval` while the UI renders `Pending Approval`. The UI updated correctly; execution stopped before the subsequent approvals were checked.
- `npm run build`: Vite itself finished but `report-bundle.mjs` failed because `dist/.vite/manifest.json` did not exist.
- The emitted `index-BsXC8eqn.js` was identical in filename and size to the previous unsplit bundle. That indicates your local `App.tsx` or Vite configuration was not fully updated from the overlay. This patch re-includes those files, and the new `check:overlay` task verifies them _before_ building.

## Install (PowerShell)

Make a Git commit or backup first. ZIP entries start at `procurepilot-ai/`, so extract into `D:\` rather than `D:\procurepilot-ai\`. Confirm `D:\procurepilot-ai\frontend\src\app\App.tsx` is actually replaced. Do not copy over your `frontend/.env`, and do not delete your existing `package-lock.json`.

```powershell
cd D:\procurepilot-ai\frontend
npm run check:overlay
npm run typecheck
npm run test
npm run build
npm run test:e2e
npm audit
```

If there is a stale Vite dev process running at port 4173, stop it before the Playwright run. The Playwright config starts its own isolated demo server and refuses to reuse an existing process.

## Expected gates

- Unit tests: **2 test files / 8 distinct tests**, not 5 test suites or duplicated E2E tests.
- E2E: **18 Playwright runs** across desktop/mobile when all tests pass. Do not claim this is verified until the full run is green; additional assertions later in the full workflow may uncover problems.
- Build: `dist/.vite/manifest.json` exists and report shows at least one lazy route chunk. Verify the initial JS is smaller than the previous unsplit baseline.
- `npm audit`: re-check after install on your machine; previous result was zero vulnerabilities.

## Design and security limits

These tests cover the **synthetic client-side demo**, not the Laravel API, database policies, authentication, or actual production behavior. UI visibility controls never replace backend authorization. Initial bundle size is not page-load latency; measure Lighthouse/Web Vitals separately if those metrics are needed.

Do not run `npm audit fix --force` without reviewing the dependency changes.
