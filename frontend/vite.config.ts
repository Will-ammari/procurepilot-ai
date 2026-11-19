import {defineConfig} from 'vite';
import react from '@vitejs/plugin-react';

// Production app build. Keep E2E/unit-test discovery in separate test configs.
export default defineConfig({
  plugins: [react()],
  server: {port: 5173},
  build: {
    // Required by scripts/report-bundle.mjs for entry/static/lazy size evidence.
    manifest: true,
  },
});
