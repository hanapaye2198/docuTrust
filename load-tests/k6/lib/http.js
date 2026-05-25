import crypto from 'k6/crypto';
import http from 'k6/http';
import { check, fail } from 'k6';

export function buildCookieHeader(cookies) {
  return Object.entries(cookies)
    .filter(([, value]) => value !== undefined && value !== null && value !== '')
    .map(([name, value]) => `${name}=${value}`)
    .join('; ');
}

export function defaultHeaders(extraHeaders = {}) {
  const headers = {
    Accept: 'application/json, text/html;q=0.9,*/*;q=0.8',
    'User-Agent': 'docutrust-k6/1.0',
    ...extraHeaders,
  };

  return headers;
}

export function jsonPost(url, payload, params = {}) {
  const headers = defaultHeaders({
    'Content-Type': 'application/json',
    ...(params.headers || {}),
  });

  return http.post(url, JSON.stringify(payload), {
    ...params,
    headers,
  });
}

export function formPost(url, payload, params = {}) {
  const headers = defaultHeaders({
    ...(params.headers || {}),
  });

  return http.post(url, payload, {
    ...params,
    headers,
  });
}

export function assertStatus(response, expected, label) {
  const allowed = Array.isArray(expected) ? expected : [expected];
  const ok = check(response, {
    [`${label} status ${allowed.join('/')}`]: (r) => allowed.includes(r.status),
  });

  if (!ok) {
    fail(`${label} failed with status ${response.status}`);
  }
}

export function hmacHex(algorithm, secret, value) {
  return crypto.hmac(algorithm, secret, value, 'hex');
}
