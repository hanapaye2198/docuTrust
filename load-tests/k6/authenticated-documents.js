import http from 'k6/http';
import { check, sleep } from 'k6';
import { baseUrl, loadStages, optionalEnv, optionalIntEnv, requireEnv } from './lib/config.js';
import { assertStatus, buildCookieHeader, defaultHeaders } from './lib/http.js';

export const options = {
  stages: loadStages(),
  thresholds: {
    http_req_failed: ['rate<0.02'],
    http_req_duration: ['p(95)<1200'],
  },
};

const BASE_URL = baseUrl();
const LARAVEL_SESSION = requireEnv('LARAVEL_SESSION');
const XSRF_TOKEN = optionalEnv('XSRF_TOKEN');
const DOCUMENT_ID = optionalIntEnv('DOCUMENT_ID', 0);
const SIGNER_ID = optionalIntEnv('SIGNER_ID', 0);

function authHeaders() {
  return defaultHeaders({
    Cookie: buildCookieHeader({
      laravel_session: LARAVEL_SESSION,
      'XSRF-TOKEN': XSRF_TOKEN,
    }),
  });
}

export default function () {
  const headers = authHeaders();

  const dashboard = http.get(`${BASE_URL}/documents`, { headers });
  assertStatus(dashboard, 200, 'documents.index');

  if (DOCUMENT_ID > 0) {
    const show = http.get(`${BASE_URL}/documents/${DOCUMENT_ID}`, { headers });
    check(show, {
      'documents.show status is acceptable': (r) => [200, 302, 403, 404].includes(r.status),
    });

    const stream = http.get(`${BASE_URL}/documents/${DOCUMENT_ID}/stream`, { headers });
    check(stream, {
      'documents.stream status is acceptable': (r) => [200, 302, 403, 404].includes(r.status),
    });

    const certificate = http.get(`${BASE_URL}/documents/${DOCUMENT_ID}/certificate`, { headers });
    check(certificate, {
      'documents.certificate.show status is acceptable': (r) => [200, 302, 403, 404].includes(r.status),
    });
  }

  if (SIGNER_ID > 0) {
    const accountSign = http.get(`${BASE_URL}/account-sign/${SIGNER_ID}`, { headers });
    check(accountSign, {
      'sign.account.show status is acceptable': (r) => [200, 302, 403, 404].includes(r.status),
    });
  }

  sleep(1);
}
