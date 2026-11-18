import http from 'k6/http';
import { check, sleep } from 'k6';

// Example baseline only. Calibrate thresholds from a controlled staging run.
export const options = {
  scenarios: {
    health_read: {
      executor: 'ramping-vus',
      startVUs: 1,
      stages: [
        { duration: '10s', target: 5 },
        { duration: '30s', target: 5 },
        { duration: '10s', target: 0 },
      ],
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<1500'],
  },
};

const base = (__ENV.BASE_URL || 'http://localhost:8000').replace(/\/$/, '');

export default function () {
  const response = http.get(`${base}/api/v1/health`, { timeout: '5s' });
  check(response, { 'health is ok': (r) => r.status === 200 });
  sleep(1);
}
