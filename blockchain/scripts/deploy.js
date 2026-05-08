import { ethers } from "hardhat";

async function main() {
    const factory = await ethers.getContractFactory("DocuTrustRegistry");
    const contract = await factory.deploy();

    await contract.waitForDeployment();

    const contractAddress = await contract.getAddress();
    console.log("DocuTrustRegistry deployed successfully");
    console.log(`Contract Address: ${contractAddress}`);
}

main().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
