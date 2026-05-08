# DocuTrust Blockchain Setup

This folder contains the smart contract infrastructure for DocuTrust's tamper-proof verification layer on Polygon Amoy.

## Architecture

- Laravel: business logic and application workflows
- blockchain-service: blockchain API bridge
- blockchain: smart contract infrastructure
- Polygon: tamper-proof verification layer

## 1) Install dependencies

```bash
npm install
```

## 2) Configure environment

Copy `.env.example` to `.env` and set:

- `PRIVATE_KEY`
- `POLYGON_RPC_URL`
- `CHAIN_ID=80002`

## 3) Compile contract

```bash
npx hardhat compile
```

## 4) Deploy to Polygon Amoy

```bash
npx hardhat run scripts/deploy.js --network amoy
```

## 5) Verify deployment

After deployment, confirm:

- deployment output prints contract address
- contract address is visible on [Polygon Amoy Explorer](https://www.oklink.com/amoy)
- test calls to `verifyDocument` return expected values

## Contract Overview

`DocuTrustRegistry.sol` stores only:

- document hash
- timestamp
- issuer address

No PDFs, personal information, signatures, emails, or OTPs are stored on-chain.
