import "dotenv/config";
import "@nomicfoundation/hardhat-ethers";

const privateKey = process.env.POLYGON_PRIVATE_KEY ?? "";
const amoyRpcUrl = process.env.POLYGON_RPC_URL ?? "";

/** @type {import('hardhat/config').HardhatUserConfig} */
const config = {
  solidity: "0.8.20",
  networks: {
    amoy: {
      url: amoyRpcUrl,
      accounts: privateKey !== "" ? [privateKey] : [],
    },
  },
};

export default config;
