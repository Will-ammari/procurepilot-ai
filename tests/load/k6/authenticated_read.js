import http from 'k6/http';
import { check, sleep } from 'k6';

// Read-only load. Use ONLY a staging token, scoped to synthetic test tenants.
export const options = {
  vus: 5,
  duration: '45s',
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<1500'],
  },
};

const base = (__ENV.BASE_URL || 'http://localhost:8000').replace(/\/$/, '');
const token = __ENV.PROCUREPILOT_TEST_TOKEN;
if (!token) {
  throw new Error('Set PROCUREPILOT_TEST_TOKEN to a staging-only read-access token.');
}

export default function () {
  const response = http.get(`${base}/api/v1/purchase-requests?per_page=15`, {
    headers: { Authorization: `Bearer ${token}` },
    timeout: '8s',
  });
  check(response, { 'authenticated read is ok': (r) => r.status === 200 });
  sleep(1);
}
