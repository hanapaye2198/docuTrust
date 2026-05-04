import { ethers } from "hardhat";

async function main() {
  const [deployer] = await ethers.getSigners();

  if (!deployer) {
    throw new Error("No deployer account is configured. Set POLYGON_PRIVATE_KEY in blockchain/.env.");
  }

  console.log(`Deploying DocumentNotary with account: ${deployer.address}`);

  const balance = await ethers.provider.getBalance(deployer.address);
  console.log(`Deployer balance: ${ethers.formatEther(balance)} POL`);

  const factory = await ethers.getContractFactory("DocumentNotary");
  const contract = await factory.deploy();
  await contract.waitForDeployment();

  const address = await contract.getAddress();

  console.log(`DocumentNotary deployed to: ${address}`);
  console.log("");
  console.log("Next step:");
  console.log(`Set DOCUMENT_NOTARY_ADDRESS=${address} in blockchain-service/.env`);
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
