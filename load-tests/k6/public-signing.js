import http from 'k6/http';
import { check, sleep } from 'k6';
import { baseUrl, loadStages, optionalEnv, optionalIntEnv, requireEnv } from './lib/config.js';
import { assertStatus, buildCookieHeader, defaultHeaders, formPost, jsonPost } from './lib/http.js';

export const options = {
  stages: loadStages(),
  thresholds: {
    http_req_failed: ['rate<0.02'],
    http_req_duration: ['p(95)<1500'],
  },
};

const BASE_URL = baseUrl();
const SIGN_TOKEN = requireEnv('SIGN_TOKEN');
const SIGN_PASSWORD = optionalEnv('SIGN_PASSWORD');
const TRUST_SESSION_ID = optionalEnv('TRUST_SESSION_ID');
const SIGN_FIELD_ID = optionalIntEnv('SIGN_FIELD_ID', 0);
const SIGN_FIELD_VALUE = optionalEnv('SIGN_FIELD_VALUE', 'Test Signer');
const SIGNATURE_IMAGE_DATA_URL = optionalEnv('SIGNATURE_IMAGE_DATA_URL');

function cookieHeader() {
  return buildCookieHeader({
    [`k6_sign_${__VU}`]: `${Date.now()}_${__ITER}`,
  });
}

export default function () {
  const headers = defaultHeaders({
    Cookie: cookieHeader(),
  });

  const signPage = http.get(`${BASE_URL}/sign/${SIGN_TOKEN}`, { headers });
  assertStatus(signPage, [200, 423], 'sign.show');

  if (SIGN_PASSWORD) {
    const unlock = formPost(
      `${BASE_URL}/sign/${SIGN_TOKEN}/unlock`,
      { password: SIGN_PASSWORD },
      {
        headers: defaultHeaders({
          Accept: 'application/json',
          Cookie: headers.Cookie,
        }),
      }
    );
    assertStatus(unlock, [200, 302], 'sign.unlock');
  }

  const pdf = http.get(`${BASE_URL}/sign/${SIGN_TOKEN}/pdf`, { headers });
  check(pdf, {
    'sign.pdf status is acceptable': (r) => [200, 302, 423].includes(r.status),
  });

  if (TRUST_SESSION_ID) {
    const trustPoll = http.get(
      `${BASE_URL}/sign/${SIGN_TOKEN}/trust/authorize/${TRUST_SESSION_ID}`,
      {
        headers: defaultHeaders({
          Accept: 'application/json',
          Cookie: headers.Cookie,
        }),
      }
    );

    check(trustPoll, {
      'trust poll status is acceptable': (r) => [200, 401, 403, 423].includes(r.status),
    });
  }

  if (SIGN_FIELD_ID > 0 && SIGNATURE_IMAGE_DATA_URL) {
    const storeSignature = jsonPost(
      `${BASE_URL}/sign/${SIGN_TOKEN}/signature`,
      {
        signature_field_id: SIGN_FIELD_ID,
        submitted_value: SIGN_FIELD_VALUE,
        signature_image: SIGNATURE_IMAGE_DATA_URL,
      },
      {
        headers: defaultHeaders({
          Accept: 'application/json',
          Cookie: headers.Cookie,
        }),
      }
    );

    check(storeSignature, {
      'sign.signature status is acceptable': (r) => [200, 422, 423].includes(r.status),
    });
  }

  sleep(1);
}
