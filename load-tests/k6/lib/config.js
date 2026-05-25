import { fail } from 'k6';

const defaultStages = [
  { duration: '1m', target: 5 },
  { duration: '3m', target: 15 },
  { duration: '1m', target: 0 },
];

export function requireEnv(name) {
  const value = __ENV[name];

  if (!value) {
    fail(`Missing required environment variable: ${name}`);
  }

  return value;
}

export function optionalEnv(name, fallback = '') {
  return __ENV[name] || fallback;
}

export function optionalIntEnv(name, fallback = 0) {
  const raw = __ENV[name];

  if (!raw) {
    return fallback;
  }

  const parsed = Number.parseInt(raw, 10);

  if (Number.isNaN(parsed)) {
    fail(`Environment variable ${name} must be an integer`);
  }

  return parsed;
}

export function loadStages() {
  const raw = __ENV.K6_STAGES;

  if (!raw) {
    return defaultStages;
  }

  try {
    const parsed = JSON.parse(raw);

    if (!Array.isArray(parsed) || parsed.length === 0) {
      fail('K6_STAGES must be a non-empty JSON array');
    }

    return parsed;
  } catch (error) {
    fail(`Invalid K6_STAGES JSON: ${error.message}`);
  }
}

export function baseUrl() {
  return requireEnv('BASE_URL').replace(/\/+$/, '');
}
