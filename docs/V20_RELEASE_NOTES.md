# v20 — Portfolio documentation & Lighthouse follow-up

**Project base:** commit `06b13f2` supplied in the archive, November 19, 2026.

## Scope

This overlay covers the first three portfolio tasks: (1) a concise GitHub README; (2) a detailed engineering case study grounded in actual services and tests; (3) a recorded PageSpeed assessment with small SEO/discovery improvements and regression checks.

It does **not** add a feature or publish the Laravel backend. The original `/engineering` page gains a link to the complete case study and route-specific title/canonical metadata. Existing Vercel SPA rewrite, login and workflow code remain in place.

## Key changes

| File / folder | Purpose |
| --- | --- |
| `README.md` | Fast project introduction, architecture, quickstart, real evidence and boundaries |
| `docs/engineering-case-study.md` | Concurrency, outbox, tenant isolation, deterministic AI fallback, trade-offs, source links |
| `docs/performance-lighthouse.md` | Supplied mobile/desktop scores, evidence screenshot, caveats and rerun instructions |
| `docs/assets/` | Owner-supplied PageSpeed screenshot and clearly labeled cropped landing preview |
| `frontend/index.html` | Description, canonical and share metadata; ARD discovery link |
| `frontend/src/features/EngineeringPage.tsx` | Link to full study and route metadata |
| `frontend/public/` | Static llms.txt, valid empty ARD JSON, robots.txt, sitemap.xml |
| `frontend/scripts/` | Source metadata tests and post-build static asset verification |
| `frontend/package.json` | Include metadata tests in `test:tooling`; verify public files after production build |
| `.github/workflows/frontend.yml` | Run public metadata/tooling checks on CI |

## Install on Windows

1. Back up the project or create a local Git commit first.
2. **Extract the ZIP root contents directly into `D:\procurepilot-ai\`**, merging `frontend`, `docs` and `.github`. Do not introduce another `procurepilot-ai` folder.
3. From `D:\procurepilot-ai\frontend`:

   ```powershell
   npm run format:check
   npm run lint
   npm run typecheck
   npm run test
   npm run test:tooling
   npm run build
   npm run test:e2e
   ```

4. `npm run test:tooling` now targets **14 tests** (previously 10). `npm run build` also executes `verify:public` after Vite, ensuring `/llms.txt` and `/.well-known/ard.json` are emitted into `dist`.
5. From `D:\procurepilot-ai`, inspect `git status --short`, `git diff --check` and `git diff --stat`; then commit/push the intentional file changes.
6. After Vercel says **Ready**, open `/llms.txt`, `/.well-known/ard.json`, `/robots.txt`, `/sitemap.xml` on the public domain. These must show real Markdown/JSON/text/XML, **not** SPA HTML. Then rerun mobile/desktop PageSpeed Insights.

## Verification performed during authoring

- TypeScript `typecheck`: passed in this environment.
- ESLint: passed.
- Prettier `format:check`: passed.
- Node tooling tests: **14/14 passed**.
- Built-public-assets verifier: exercised against a staging `dist` populated with the new static files (source-level validation only).
- Full new Vite/Vitest/Playwright suite: **not executed here**; uploaded `node_modules` originated on Windows and do not contain the native Linux Rollup binary. Download access was also unavailable. **Run those commands on your Windows checkout and do not push if they fail.**

**Historical scores:** owner-provided PageSpeed screenshots recorded Performance 100, Accessibility 100, Best Practices 100, SEO 91 on mobile and desktop; experimental Agentic Browsing 2/4. The new metadata is not a promise that any audit score will increase.
