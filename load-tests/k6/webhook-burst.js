import { sleep } from 'k6';
import http from 'k6/http';
import { baseUrl, loadStages, optionalEnv, requireEnv } from './lib/config.js';
import { assertStatus, defaultHeaders, hmacHex, jsonPost } from './lib/http.js';

export const options = {
  stages: loadStages(),
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<1000'],
  },
};

const BASE_URL = baseUrl();
const PROVIDER = requireEnv('WEBHOOK_PROVIDER').toLowerCase();

function gatewayHubConfig() {
  return {
    path: '/api/gatewayhub/webhook',
    secret: requireEnv('GATEWAYHUB_WEBHOOK_SECRET'),
  };
}

function sumsubConfig() {
  return {
    path: '/api/webhooks/sumsub',
    secret: requireEnv('SUMSUB_WEBHOOK_SECRET'),
  };
}

function gatewayHubPayload() {
  return {
    event: 'payment.updated',
    data: {
      payment_id: optionalEnv('GATEWAYHUB_PAYMENT_ID', `k6-payment-${__VU}-${__ITER}`),
      reference: optionalEnv('GATEWAYHUB_REFERENCE', `k6-ref-${__VU}-${__ITER}`),
      status: optionalEnv('GATEWAYHUB_STATUS', 'paid'),
      amount: 10000,
      currency: 'PHP',
    },
  };
}

function sumsubPayload() {
  return {
    type: optionalEnv('SUMSUB_EVENT_TYPE', 'applicantPending'),
    applicantId: optionalEnv('SUMSUB_APPLICANT_ID', `k6-applicant-${__VU}-${__ITER}`),
    externalUserId: optionalEnv('SUMSUB_EXTERNAL_USER_ID', `k6-user-${__VU}-${__ITER}`),
    reviewStatus: optionalEnv('SUMSUB_REVIEW_STATUS', 'pending'),
  };
}

function sendGatewayHub() {
  const config = gatewayHubConfig();
  const payload = gatewayHubPayload();
  const body = JSON.stringify(payload);
  const timestamp = new Date().toISOString();
  const signature = hmacHex('sha256', config.secret, `${timestamp}.${body}`);

  const response = http.post(`${BASE_URL}${config.path}`, body, {
    headers: defaultHeaders({
      'Content-Type': 'application/json',
      'X-Merchant-Timestamp': timestamp,
      'X-Merchant-Signature': signature,
    }),
  });

  assertStatus(response, [200, 202, 401], 'gatewayhub.webhook');
}

function sendSumsub() {
  const config = sumsubConfig();
  const payload = sumsubPayload();
  const body = JSON.stringify(payload);
  const digest = hmacHex('sha1', config.secret, body);

  const response = jsonPost(`${BASE_URL}${config.path}`, payload, {
    headers: defaultHeaders({
      'X-Payload-Digest': digest,
    }),
  });

  assertStatus(response, [200, 401], 'sumsub.webhook');
}

export default function () {
  if (PROVIDER === 'gatewayhub') {
    sendGatewayHub();
  } else if (PROVIDER === 'sumsub') {
    sendSumsub();
  } else {
    throw new Error(`Unsupported WEBHOOK_PROVIDER: ${PROVIDER}`);
  }

  sleep(1);
}
