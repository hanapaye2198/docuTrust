import express from "express";
import { Contract, JsonRpcProvider, Wallet, getAddress, isAddress, isHexString } from "ethers";
import { DOCUMENT_NOTARY_ABI } from "./contractAbi.js";

const app = express();
app.use(express.json());

const PORT = Number.parseInt(process.env.PORT ?? "3001", 10);
const POLYGON_NETWORK = process.env.POLYGON_NETWORK ?? "amoy";
const POLYGON_RPC_URL = process.env.POLYGON_RPC_URL ?? "";
const POLYGON_PRIVATE_KEY = process.env.POLYGON_PRIVATE_KEY ?? "";
const DOCUMENT_NOTARY_ADDRESS = process.env.DOCUMENT_NOTARY_ADDRESS ?? "";

if (POLYGON_RPC_URL === "" || POLYGON_PRIVATE_KEY === "" || DOCUMENT_NOTARY_ADDRESS === "") {
  throw new Error("Missing required Polygon blockchain configuration.");
}

if (!/^https?:\/\//i.test(POLYGON_RPC_URL)) {
  throw new Error("POLYGON_RPC_URL must be a valid HTTP(S) Polygon RPC endpoint.");
}

if (!isHexString(POLYGON_PRIVATE_KEY, 32)) {
  throw new Error("POLYGON_PRIVATE_KEY must be a 32-byte hex private key for the backend Polygon wallet.");
}

if (!isAddress(DOCUMENT_NOTARY_ADDRESS)) {
  throw new Error("DOCUMENT_NOTARY_ADDRESS must be a valid deployed DocumentNotary contract address.");
}

const provider = new JsonRpcProvider(POLYGON_RPC_URL);
const wallet = new Wallet(POLYGON_PRIVATE_KEY, provider);
const normalizedNotaryAddress = getAddress(DOCUMENT_NOTARY_ADDRESS);
const notaryContract = new Contract(normalizedNotaryAddress, DOCUMENT_NOTARY_ABI, wallet);

app.get("/health", (request, response) => {
  response.json({
    status: "ok",
    network: `polygon-${POLYGON_NETWORK}`,
    walletAddress: wallet.address,
    contractAddress: normalizedNotaryAddress
  });
});

app.post("/anchor", async (request, response) => {
  try {
    const { hash } = request.body ?? {};
    const documentHash = normalizeHash(hash);

    const transaction = await notaryContract.storeDocumentHash(documentHash);
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

      const receipt = await provider.getTransactionReceipt(transactionHash);
      if (!receipt || receipt.status !== 1n) {
        return response.json({ exists: false, transactionMatches: false });
      }

      transactionMatches = receipt.to?.toLowerCase() === normalizedNotaryAddress.toLowerCase();
      blockNumber = receipt.blockNumber;
    }

    if (!hasHash) {
      return response.json({
        exists: transactionMatches === true,
        transactionMatches,
        blockNumber
      });
    }

    const documentHash = normalizeHash(hash);
    const exists = await notaryContract.documentHashExists(documentHash);

    if (!exists) {
      return response.json({
        exists: false,
        transactionMatches,
        blockNumber
      });
    }

    const proof = await notaryContract.getDocumentProof(documentHash);

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

app.listen(PORT, () => {
  console.log(`DocuTrust blockchain service listening on port ${PORT}`);
});

function normalizeHash(hash) {
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
