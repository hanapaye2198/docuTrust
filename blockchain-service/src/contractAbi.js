export const DOCUMENT_NOTARY_ABI = [
  "function storeDocumentHash(bytes32 documentHash) external",
  "function getDocumentProof(bytes32 documentHash) external view returns (uint256 timestamp, address submittedBy)",
  "function documentHashExists(bytes32 documentHash) external view returns (bool)"
];
