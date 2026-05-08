import { ethers } from "ethers";
import dotenv from "dotenv";

dotenv.config();

async function main() {
    const rpcUrl = process.env.POLYGON_RPC_URL;
    const rawPrivateKey = process.env.POLYGON_PRIVATE_KEY ?? process.env.PRIVATE_KEY;

    if (!rpcUrl) {
        throw new Error("Missing POLYGON_RPC_URL in blockchain/.env");
    }

    if (!rawPrivateKey) {
        throw new Error("Missing POLYGON_PRIVATE_KEY or PRIVATE_KEY in blockchain/.env");
    }

    const trimmedPrivateKey = rawPrivateKey.trim().replace(/^['"]|['"]$/g, "");
    const privateKey = /^0x/i.test(trimmedPrivateKey)
        ? trimmedPrivateKey
        : `0x${trimmedPrivateKey}`;

    if (privateKey.length === 42) {
        throw new Error(
            "Private key looks like a wallet address. Use the actual private key (0x + 64 hex chars)."
        );
    }

    if (!/^0x[0-9a-fA-F]{64}$/.test(privateKey)) {
        throw new Error(
            "Private key must be 0x followed by 64 hexadecimal characters."
        );
    }

    const provider = new ethers.JsonRpcProvider(rpcUrl);

    const wallet = new ethers.Wallet(privateKey, provider);

    console.log("Wallet:", wallet.address);

    const balance = await provider.getBalance(wallet.address);

    console.log(
        "Balance:",
        ethers.formatEther(balance),
        "POL"
    );
}

main().catch((error) => {
    console.error(error);
    process.exit(1);
});
