import express from "express";
import { Contract, JsonRpcProvider, Wallet, isHexString } from "ethers";
import { DOCUMENT_NOTARY_ABI } from "./contractAbi.js";

const app = express();
app.use(express.json());

const PORT = Number.parseInt(process.env.PORT ?? "3001", 10);
const POLYGON_NETWORK = process.env.POLYGON_NETWORK ?? "amoy";
const POLYGON_RPC_URL = process.env.POLYGON_RPC_URL ?? "";
const POLYGON_PRIVATE_KEY = process.env.POLYGON_PRIVATE_KEY ?? "";
const DOCUMENT_NOTARY_ADDRESS = process.env.DOCUMENT_NOTARY_ADDRESS ?? "";

if (POLYGON_RPC_URL === "" || POLYGON_PRIVATE_KEY === "" || DOCUMENT_NOTARY_ADDRESS === "") {
  throw new Error("Missing required blockchain configuration.");
}

const provider = new JsonRpcProvider(POLYGON_RPC_URL);
const wallet = new Wallet(POLYGON_PRIVATE_KEY, provider);
const notaryContract = new Contract(DOCUMENT_NOTARY_ADDRESS, DOCUMENT_NOTARY_ABI, wallet);

app.get("/health", (request, response) => {
  response.json({ status: "ok", network: `polygon-${POLYGON_NETWORK}` });
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

      transactionMatches = receipt.to?.toLowerCase() === DOCUMENT_NOTARY_ADDRESS.toLowerCase();
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
