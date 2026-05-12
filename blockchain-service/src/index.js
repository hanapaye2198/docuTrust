import crypto, { X509Certificate, createPrivateKey, createPublicKey } from "crypto";
import fs from "fs";
import os from "os";
import path from "path";
import { spawnSync } from "child_process";
import { fileURLToPath } from "url";
import express from "express";
import { Contract, JsonRpcProvider, Wallet, getAddress, isAddress, isHexString } from "ethers";
import { DOCUMENT_NOTARY_ABI } from "./contractAbi.js";

loadLocalEnvFile();

const app = express();
app.use(express.json({ limit: "1mb" }));

const PORT = Number.parseInt(process.env.PORT ?? "3001", 10);
const OPENSSL_BINARY = process.env.CSC_OPENSSL_BINARY
  ?? process.env.REMOTE_SIGNING_CSC_TIMESTAMP_OPENSSL_BINARY
  ?? "openssl";

const blockchainConfig = buildBlockchainConfig();
const cscConfig = buildCscConfig();
const authorizationSessions = new Map();

const allowEmptyStartup = parseBoolean(process.env.DOCUTRUST_ALLOW_EMPTY_SERVICE ?? "");

if (!blockchainConfig.enabled && !cscConfig.enabled && !allowEmptyStartup) {
  throw new Error(
    "At least one of blockchain anchoring or CSC remote signing must be configured. "
      + "For local development without secrets, set DOCUTRUST_ALLOW_EMPTY_SERVICE=true "
      + "(/anchor and /verify will respond with 503 until Polygon or CSC is configured)."
  );
}

if (!blockchainConfig.enabled && !cscConfig.enabled && allowEmptyStartup) {
  console.warn(
    "[docutrust-blockchain-service] DOCUTRUST_ALLOW_EMPTY_SERVICE is enabled: "
      + "blockchain anchoring and CSC are off; POST /anchor and POST /verify return 503."
  );
}

const blockchainState = blockchainConfig.enabled ? buildBlockchainState(blockchainConfig) : null;

app.get("/health", (request, response) => {
  response.json({
    status: "ok",
    blockchain: blockchainState === null
      ? { enabled: false }
      : {
          enabled: true,
          network: `polygon-${blockchainConfig.network}`,
          walletAddress: blockchainState.wallet.address,
          contractAddress: blockchainState.normalizedNotaryAddress
        },
    csc: cscConfig.enabled
      ? {
          enabled: true,
          providerName: cscConfig.providerName,
          credentialId: cscConfig.credentialId,
          hasTimestamping: cscConfig.timestampEnabled
        }
      : {
          enabled: false
        }
  });
});

app.post("/anchor", async (request, response) => {
  if (blockchainState === null) {
    return response.status(503).json({
      message: "Blockchain anchoring is not configured."
    });
  }

  try {
    const { hash } = request.body ?? {};
    const documentHash = normalizeHexHash(hash);

    const transaction = await blockchainState.notaryContract.storeDocumentHash(documentHash);
    const receipt = await transaction.wait();

    response.status(201).json({
      hash: documentHash,
      transactionHash: receipt?.hash ?? transaction.hash
    });
  } catch (error) {
    response.status(422).json({
      message: "Unable to anchor hash on Polygon.",
      error: error instanceof Error ? error.message : "Unknown blockchain error"
    });
  }
});

app.post("/verify", async (request, response) => {
  if (blockchainState === null) {
    return response.status(503).json({
      exists: false,
      message: "Blockchain verification is not configured."
    });
  }

  try {
    const { transactionHash, hash } = request.body ?? {};
    const hasTransactionHash = typeof transactionHash === "string" && transactionHash.trim() !== "";
    const hasHash = typeof hash === "string" && hash.trim() !== "";

    if (!hasTransactionHash && !hasHash) {
      return response.status(422).json({ exists: false, message: "A transaction hash or document hash is required." });
    }

    let transactionMatches = null;
    let blockNumber = null;

    if (hasTransactionHash) {
      if (!isHexString(transactionHash, 32) && !isHexString(transactionHash)) {
        return response.status(422).json({ exists: false, message: "Invalid transaction hash format." });
      }

      const receipt = await blockchainState.provider.getTransactionReceipt(transactionHash);
      if (!receipt || receipt.status !== 1n) {
        return response.json({ exists: false, transactionMatches: false });
      }

      transactionMatches = receipt.to?.toLowerCase() === blockchainState.normalizedNotaryAddress.toLowerCase();
      blockNumber = receipt.blockNumber;
    }

    if (!hasHash) {
      return response.json({
        exists: transactionMatches === true,
        transactionMatches,
        blockNumber
      });
    }

    const documentHash = normalizeHexHash(hash);
    const exists = await blockchainState.notaryContract.documentHashExists(documentHash);

    if (!exists) {
      return response.json({
        exists: false,
        transactionMatches,
        blockNumber
      });
    }

    const proof = await blockchainState.notaryContract.getDocumentProof(documentHash);

    response.json({
      exists: true,
      transactionMatches,
      blockNumber,
      proofTimestamp: Number(proof.timestamp),
      submittedBy: proof.submittedBy
    });
  } catch (error) {
    response.status(422).json({
      exists: false,
      message: error instanceof Error ? error.message : "Unable to verify transaction."
    });
  }
});

app.post("/csc/v2/credentials/authorize", requireCscService, requireBearerAuth, (request, response) => {
  try {
    const { credentialID, numSignatures } = request.body ?? {};
    validateCredentialRequest(credentialID);

    const handle = `auth-${crypto.randomUUID()}`;
    const sad = `sad-${crypto.randomUUID()}`;
    const expiresIn = cscConfig.authorizationTtlSeconds;
    const session = {
      handle,
      sad,
      credentialID,
      numSignatures: Number.isInteger(numSignatures) && numSignatures > 0 ? numSignatures : 1,
      createdAt: Date.now(),
      expiresAt: Date.now() + (expiresIn * 1000)
    };

    authorizationSessions.set(handle, session);

    response.json({
      credentialID,
      SAD: sad,
      handle,
      expiresIn,
      authMode: cscConfig.authorizationMode
    });
  } catch (error) {
    response.status(422).json({
      error: "invalid_request",
      error_description: error instanceof Error ? error.message : "Authorization request is invalid."
    });
  }
});

app.post("/csc/v2/credentials/authorizeCheck", requireCscService, requireBearerAuth, (request, response) => {
  try {
    const { handle } = request.body ?? {};
    if (typeof handle !== "string" || handle.trim() === "") {
      throw new Error("Missing authorization handle.");
    }

    const session = authorizationSessions.get(handle);
    if (!session) {
      return response.status(404).json({
        error: "invalid_request",
        error_description: "Unknown authorization handle."
      });
    }

    if (session.expiresAt <= Date.now()) {
      authorizationSessions.delete(handle);

      return response.status(410).json({
        error: "invalid_request",
        error_description: "Authorization handle has expired."
      });
    }

    response.json({
      credentialID: session.credentialID,
      SAD: session.sad,
      handle: session.handle,
      expiresIn: Math.max(0, Math.floor((session.expiresAt - Date.now()) / 1000)),
      authMode: cscConfig.authorizationMode
    });
  } catch (error) {
    response.status(422).json({
      error: "invalid_request",
      error_description: error instanceof Error ? error.message : "Authorization status request is invalid."
    });
  }
});

app.post("/csc/v1/signatures/signHash", requireCscService, requireBearerAuth, (request, response) => {
  try {
    const { credentialID, hash, hashes, hashAlgo, signAlgo, SAD } = request.body ?? {};
    validateCredentialRequest(credentialID);
    validateSadIfRequired(SAD);
    validateSupportedHashAlgorithm(hashAlgo);
    validateSupportedSignatureAlgorithm(signAlgo);

    const hashList = Array.isArray(hashes) && hashes.length > 0 ? hashes : [hash];
    if (!Array.isArray(hashList) || hashList.length === 0) {
      throw new Error("Missing hash values to sign.");
    }

    const signatures = hashList.map((base64Hash) => signBase64Digest(base64Hash, cscConfig.signerPrivateKeyPem));

    response.json({
      signatures,
      signAlgo: cscConfig.signatureAlgorithm,
      credentialID: cscConfig.credentialId,
      transactionID: `sign-${crypto.randomUUID()}`,
      certificates: [
        cscConfig.signerCertificatePem,
        cscConfig.issuerCertificatePem
      ],
      public_key_pem: cscConfig.signerPublicKeyPem,
      SCAL: cscConfig.scal,
      authMode: cscConfig.authorizationMode,
      signingTime: new Date().toISOString(),
      validationInfo: {
        policy: "csc",
        provider: cscConfig.providerName
      },
      evidence: {
        authentication_method: cscConfig.authorizationMode === "explicit" ? "sad" : "implicit"
      }
    });
  } catch (error) {
    response.status(422).json({
      error: "invalid_request",
      error_description: error instanceof Error ? error.message : "Signing request is invalid."
    });
  }
});

app.post("/csc/v1/signatures/timestamp", requireCscService, requireBearerAuth, (request, response) => {
  try {
    if (!cscConfig.timestampEnabled) {
      return response.status(501).json({
        error: "unsupported_operation",
        error_description: "Timestamping is not configured."
      });
    }

    const { hash, hashAlgo, nonce } = request.body ?? {};
    validateSupportedHashAlgorithm(hashAlgo);
    const normalizedDigest = decodeBase64Digest(hash);
    const { tokenDer, tokenNonce } = issueTimestampToken(normalizedDigest.toString("hex"), nonce, cscConfig);

    response.json({
      timestamp: tokenDer.toString("base64"),
      transactionID: `ts-${crypto.randomUUID()}`,
      timestampNonce: tokenNonce
    });
  } catch (error) {
    response.status(422).json({
      error: "invalid_request",
      error_description: error instanceof Error ? error.message : "Timestamp request is invalid."
    });
  }
});

app.listen(PORT, () => {
  console.log(`DocuTrust service listening on port ${PORT}`);
});

function loadLocalEnvFile() {
  const envPath = path.resolve(currentDirectory(), "..", ".env");
  if (!fs.existsSync(envPath)) {
    return;
  }

  const envContents = fs.readFileSync(envPath, "utf8");
  for (const line of envContents.split(/\r?\n/u)) {
    const parsed = parseEnvLine(line);
    if (parsed === null) {
      continue;
    }

    if (process.env[parsed.key] === undefined) {
      process.env[parsed.key] = parsed.value;
    }
  }
}

function currentDirectory() {
  return path.dirname(fileURLToPath(import.meta.url));
}

function parseEnvLine(line) {
  const trimmed = line.trim();
  if (trimmed === "" || trimmed.startsWith("#")) {
    return null;
  }

  const separatorIndex = trimmed.indexOf("=");
  if (separatorIndex <= 0) {
    return null;
  }

  const key = trimmed.slice(0, separatorIndex).trim();
  if (key === "") {
    return null;
  }

  let value = trimmed.slice(separatorIndex + 1).trim();
  if (
    (value.startsWith("\"") && value.endsWith("\""))
    || (value.startsWith("'") && value.endsWith("'"))
  ) {
    value = value.slice(1, -1);
  }

  return { key, value };
}

function buildBlockchainConfig() {
  const network = process.env.POLYGON_NETWORK ?? "amoy";
  const rpcUrl = process.env.POLYGON_RPC_URL ?? "";
  const privateKey = process.env.POLYGON_PRIVATE_KEY ?? "";
  const notaryAddress = process.env.DOCUMENT_NOTARY_ADDRESS ?? "";
  const enabled = rpcUrl !== "" && privateKey !== "" && notaryAddress !== "";

  if (enabled) {
    if (!/^https?:\/\//i.test(rpcUrl)) {
      throw new Error("POLYGON_RPC_URL must be a valid HTTP(S) Polygon RPC endpoint.");
    }

    if (!isHexString(privateKey, 32)) {
      throw new Error("POLYGON_PRIVATE_KEY must be a 32-byte hex private key for the backend Polygon wallet.");
    }

    if (!isAddress(notaryAddress)) {
      throw new Error("DOCUMENT_NOTARY_ADDRESS must be a valid deployed DocumentNotary contract address.");
    }
  }

  return {
    enabled,
    network,
    rpcUrl,
    privateKey,
    notaryAddress
  };
}

function buildBlockchainState(config) {
  const provider = new JsonRpcProvider(config.rpcUrl);
  const wallet = new Wallet(config.privateKey, provider);
  const normalizedNotaryAddress = getAddress(config.notaryAddress);
  const notaryContract = new Contract(normalizedNotaryAddress, DOCUMENT_NOTARY_ABI, wallet);

  return {
    provider,
    wallet,
    normalizedNotaryAddress,
    notaryContract
  };
}

function buildCscConfig() {
  const providerName = process.env.CSC_PROVIDER_NAME ?? process.env.REMOTE_SIGNING_PROVIDER_NAME ?? "docutrust_tsp";
  const apiKey = process.env.CSC_SERVICE_API_KEY ?? process.env.REMOTE_SIGNING_API_KEY ?? "";
  const credentialId = process.env.CSC_CREDENTIAL_ID ?? process.env.REMOTE_SIGNING_DEFAULT_CREDENTIAL_ID ?? "";
  const signerPrivateKeyPath = process.env.CSC_SIGNER_PRIVATE_KEY_PATH ?? "";
  const signerCertPath = process.env.CSC_SIGNER_CERT_PATH ?? "";
  const issuerCertPath = process.env.CSC_ISSUER_CERT_PATH ?? "";
  const timestampEnabled = parseBoolean(process.env.CSC_TIMESTAMP_ENABLED ?? process.env.REMOTE_SIGNING_CSC_TIMESTAMP_ENABLED ?? "false");
  const authorizationMode = process.env.CSC_AUTHORIZATION_MODE ?? process.env.REMOTE_SIGNING_CSC_AUTHORIZATION_MODE ?? "explicit";
  const authorizationTtlSeconds = Number.parseInt(process.env.CSC_AUTHORIZATION_TTL_SECONDS ?? "600", 10);
  const scal = process.env.CSC_SCAL ?? "2";
  const signatureAlgorithm = process.env.CSC_SIGNATURE_ALGORITHM ?? "1.2.840.113549.1.1.11";
  const timestampSignerCertPath = process.env.CSC_TSA_CERT_PATH ?? "";
  const timestampSignerKeyPath = process.env.CSC_TSA_KEY_PATH ?? "";
  const timestampIssuerCertPath = process.env.CSC_TSA_ISSUER_CERT_PATH ?? issuerCertPath;
  const timestampPolicyOid = process.env.CSC_TSA_POLICY_OID ?? "1.2.3.4.1";

  const enabled = apiKey !== "" && credentialId !== "" && signerPrivateKeyPath !== "" && signerCertPath !== "" && issuerCertPath !== "";

  if (!enabled) {
    return { enabled: false };
  }

  const signerPrivateKeyPem = readRequiredFile(signerPrivateKeyPath, "CSC_SIGNER_PRIVATE_KEY_PATH");
  const signerCertificatePem = readRequiredFile(signerCertPath, "CSC_SIGNER_CERT_PATH");
  const issuerCertificatePem = readRequiredFile(issuerCertPath, "CSC_ISSUER_CERT_PATH");
  const signerPublicKeyPem = createPublicKey(new X509Certificate(signerCertificatePem).publicKey).export({
    format: "pem",
    type: "spki"
  }).toString();

  if (timestampEnabled) {
    readRequiredFile(timestampSignerCertPath, "CSC_TSA_CERT_PATH");
    readRequiredFile(timestampSignerKeyPath, "CSC_TSA_KEY_PATH");
    readRequiredFile(timestampIssuerCertPath, "CSC_TSA_ISSUER_CERT_PATH");
  }

  return {
    enabled: true,
    providerName,
    apiKey,
    credentialId,
    signerPrivateKeyPem,
    signerCertificatePem,
    issuerCertificatePem,
    signerPublicKeyPem,
    timestampEnabled,
    authorizationMode,
    authorizationTtlSeconds: Number.isInteger(authorizationTtlSeconds) && authorizationTtlSeconds > 0 ? authorizationTtlSeconds : 600,
    scal,
    signatureAlgorithm,
    timestampSignerCertPath,
    timestampSignerKeyPath,
    timestampIssuerCertPath,
    timestampPolicyOid
  };
}

function requireCscService(request, response, next) {
  if (!cscConfig.enabled) {
    return response.status(503).json({
      error: "service_unavailable",
      error_description: "CSC remote signing service is not configured."
    });
  }

  next();
}

function requireBearerAuth(request, response, next) {
  if (!cscConfig.enabled) {
    return response.status(503).json({
      error: "service_unavailable",
      error_description: "CSC remote signing service is not configured."
    });
  }

  const header = request.header("authorization") ?? "";
  const match = /^Bearer\s+(.+)$/i.exec(header);

  if (!match || match[1] !== cscConfig.apiKey) {
    return response.status(401).json({
      error: "invalid_token",
      error_description: "Missing or invalid bearer token."
    });
  }

  next();
}

function validateCredentialRequest(credentialId) {
  if (typeof credentialId !== "string" || credentialId.trim() === "") {
    throw new Error("Missing (or invalid type) string parameter credentialID");
  }

  if (credentialId !== cscConfig.credentialId) {
    throw new Error("Invalid parameter credentialID");
  }
}

function validateSadIfRequired(sad) {
  if (cscConfig.authorizationMode !== "explicit") {
    return;
  }

  if (typeof sad !== "string" || sad.trim() === "") {
    throw new Error("Missing SAD authorization token.");
  }

  const session = [...authorizationSessions.values()].find((candidate) => candidate.sad === sad);
  if (!session || session.expiresAt <= Date.now()) {
    throw new Error("Invalid or expired SAD authorization token.");
  }
}

function validateSupportedHashAlgorithm(hashAlgo) {
  const normalized = typeof hashAlgo === "string" ? hashAlgo.trim() : "";
  if (!["2.16.840.1.101.3.4.2.1", "sha256"].includes(normalized)) {
    throw new Error("Unsupported hash algorithm. Only SHA-256 is supported.");
  }
}

function validateSupportedSignatureAlgorithm(signAlgo) {
  const normalized = typeof signAlgo === "string" ? signAlgo.trim() : "";
  if (normalized === "" || ["1.2.840.113549.1.1.11", "RSA-SHA256"].includes(normalized)) {
    return;
  }

  throw new Error("Unsupported signature algorithm. Only RSA-SHA256 is supported.");
}

function signBase64Digest(base64Hash, privateKeyPem) {
  const digest = decodeBase64Digest(base64Hash);
  const signer = crypto.createSign("RSA-SHA256");
  signer.update(digest);
  signer.end();

  return signer.sign(createPrivateKey(privateKeyPem), "base64");
}

function decodeBase64Digest(base64Hash) {
  if (typeof base64Hash !== "string" || base64Hash.trim() === "") {
    throw new Error("Missing hash parameter.");
  }

  const digest = Buffer.from(base64Hash, "base64");
  if (digest.length !== 32) {
    throw new Error("Hash must be a base64-encoded SHA-256 digest.");
  }

  return digest;
}

function issueTimestampToken(digestHex, nonce, config) {
  const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), "docutrust-tsa-"));

  try {
    const configPath = path.join(tmpDir, "tsa.cnf");
    const serialPath = path.join(tmpDir, "tsa-serial");
    const queryPath = path.join(tmpDir, "req.tsq");
    const responsePath = path.join(tmpDir, "resp.tsr");
    const normalizedNonce = typeof nonce === "string" && nonce.trim() !== ""
      ? normalizeHexNonce(nonce)
      : null;

    fs.writeFileSync(serialPath, "01\n");
    fs.writeFileSync(configPath, buildTsaConfig({
      signerCertPath: config.timestampSignerCertPath,
      signerKeyPath: config.timestampSignerKeyPath,
      issuerCertPath: config.timestampIssuerCertPath,
      serialPath,
      policyOid: config.timestampPolicyOid
    }));

    fs.writeFileSync(queryPath, buildTimestampQuery(digestHex, normalizedNonce));

    const queryText = runOpenSsl([
      "ts",
      "-query",
      "-in",
      queryPath,
      "-text"
    ]);
    runOpenSsl([
      "ts",
      "-reply",
      "-config",
      configPath,
      "-section",
      "tsa_config1",
      "-queryfile",
      queryPath,
      "-out",
      responsePath,
      "-token_out"
    ]);

    return {
      tokenDer: fs.readFileSync(responsePath),
      tokenNonce: extractNonceFromText(queryText) ?? normalizedNonce
    };
  } finally {
    fs.rmSync(tmpDir, { recursive: true, force: true });
  }
}

function buildTsaConfig({ signerCertPath, signerKeyPath, issuerCertPath, serialPath, policyOid }) {
  const normalizePath = (value) => value.replaceAll("\\", "/");

  return [
    "[ tsa ]",
    "default_tsa = tsa_config1",
    "",
    "[ tsa_config1 ]",
    `signer_cert = ${normalizePath(signerCertPath)}`,
    `certs = ${normalizePath(issuerCertPath)}`,
    `signer_key = ${normalizePath(signerKeyPath)}`,
    "signer_digest = sha256",
    "digests = sha256",
    "ess_cert_id_chain = no",
    "ess_cert_id_alg = sha256",
    `default_policy = ${policyOid}`,
    `other_policies = ${policyOid}`,
    `serial = ${normalizePath(serialPath)}`,
    "crypto_device = builtin",
    "accuracy = secs:1",
    "ordering = no",
    "tsa_name = no"
  ].join("\n");
}

function runOpenSsl(args) {
  const result = spawnSync(OPENSSL_BINARY, args, {
    encoding: "utf8"
  });

  if (result.status !== 0) {
    const stderr = typeof result.stderr === "string" ? result.stderr.trim() : "";
    const stdout = typeof result.stdout === "string" ? result.stdout.trim() : "";
    throw new Error(stderr || stdout || "OpenSSL command failed.");
  }
}

function normalizeHexNonce(nonce) {
  if (typeof nonce !== "string" || nonce.trim() === "" || !/^[a-f0-9]+$/i.test(nonce)) {
    throw new Error("Timestamp nonce must be a hex string.");
  }

  const normalized = nonce.trim().toLowerCase();
  return normalized.length % 2 === 0 ? normalized : `0${normalized}`;
}

function readRequiredFile(filePath, envName) {
  if (typeof filePath !== "string" || filePath.trim() === "" || !fs.existsSync(filePath)) {
    throw new Error(`${envName} must point to an existing file.`);
  }

  return fs.readFileSync(filePath, "utf8");
}

function normalizeHexHash(hash) {
  if (typeof hash !== "string") {
    throw new Error("Hash must be a string.");
  }

  const trimmedHash = hash.trim();
  const withPrefix = trimmedHash.startsWith("0x") ? trimmedHash : `0x${trimmedHash}`;

  if (!isHexString(withPrefix, 32)) {
    throw new Error("Hash must be a SHA-256 hex string.");
  }

  return withPrefix;
}

function parseBoolean(value) {
  return ["1", "true", "yes", "on"].includes(String(value).trim().toLowerCase());
}

function extractNonceFromText(text) {
  const match = /Nonce:\s*0x([A-Fa-f0-9]+)/.exec(text);

  return match ? match[1] : null;
}

function buildTimestampQuery(digestHex, nonceHex) {
  const digest = Buffer.from(digestHex, "hex");
  if (digest.length !== 32) {
    throw new Error("Timestamp digest must be a 32-byte SHA-256 hash.");
  }

  const sha256AlgorithmIdentifier = derSequence([
    derObjectIdentifier("2.16.840.1.101.3.4.2.1"),
    derNull()
  ]);

  const messageImprint = derSequence([
    sha256AlgorithmIdentifier,
    derOctetString(digest)
  ]);

  const elements = [
    derInteger(1),
    messageImprint
  ];

  if (nonceHex !== null) {
    elements.push(derInteger(Buffer.from(nonceHex, "hex")));
  }

  elements.push(derBoolean(true));

  return derSequence(elements);
}

function derSequence(items) {
  const body = Buffer.concat(items);

  return Buffer.concat([Buffer.from([0x30]), derLength(body.length), body]);
}

function derObjectIdentifier(oid) {
  const parts = oid.split(".").map((part) => Number.parseInt(part, 10));
  if (parts.length < 2 || parts.some((part) => Number.isNaN(part) || part < 0)) {
    throw new Error(`Invalid OID: ${oid}`);
  }

  const firstByte = (parts[0] * 40) + parts[1];
  const rest = parts.slice(2).flatMap(encodeBase128Integer);
  const body = Buffer.from([firstByte, ...rest]);

  return Buffer.concat([Buffer.from([0x06]), derLength(body.length), body]);
}

function derNull() {
  return Buffer.from([0x05, 0x00]);
}

function derOctetString(value) {
  return Buffer.concat([Buffer.from([0x04]), derLength(value.length), value]);
}

function derBoolean(value) {
  return Buffer.from([0x01, 0x01, value ? 0xff : 0x00]);
}

function derInteger(value) {
  const body = typeof value === "number"
    ? encodeUnsignedInteger(value)
    : encodeUnsignedIntegerBuffer(value);

  return Buffer.concat([Buffer.from([0x02]), derLength(body.length), body]);
}

function derLength(length) {
  if (length < 0x80) {
    return Buffer.from([length]);
  }

  const bytes = [];
  let remaining = length;
  while (remaining > 0) {
    bytes.unshift(remaining & 0xff);
    remaining >>= 8;
  }

  return Buffer.from([0x80 | bytes.length, ...bytes]);
}

function encodeBase128Integer(value) {
  if (value === 0) {
    return [0];
  }

  const bytes = [];
  let remaining = value;
  while (remaining > 0) {
    bytes.unshift(remaining & 0x7f);
    remaining >>= 7;
  }

  for (let index = 0; index < bytes.length - 1; index += 1) {
    bytes[index] |= 0x80;
  }

  return bytes;
}

function encodeUnsignedInteger(value) {
  if (!Number.isInteger(value) || value < 0) {
    throw new Error("DER integer must be a non-negative integer.");
  }

  if (value === 0) {
    return Buffer.from([0x00]);
  }

  const bytes = [];
  let remaining = value;
  while (remaining > 0) {
    bytes.unshift(remaining & 0xff);
    remaining >>= 8;
  }

  return bytes[0] & 0x80 ? Buffer.from([0x00, ...bytes]) : Buffer.from(bytes);
}

function encodeUnsignedIntegerBuffer(value) {
  if (!Buffer.isBuffer(value) || value.length === 0) {
    throw new Error("DER integer buffer must not be empty.");
  }

  let index = 0;
  while (index < value.length - 1 && value[index] === 0x00) {
    index += 1;
  }

  const normalized = value.subarray(index);
  return normalized[0] & 0x80
    ? Buffer.concat([Buffer.from([0x00]), normalized])
    : normalized;
}
