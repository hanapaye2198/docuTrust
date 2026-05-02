// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

contract DocumentNotary {
    struct DocumentProof {
        uint256 timestamp;
        address submittedBy;
    }

    mapping(bytes32 => DocumentProof) private proofs;

    event DocumentHashStored(bytes32 indexed documentHash, uint256 timestamp, address indexed submittedBy);

    function storeDocumentHash(bytes32 documentHash) external {
        require(documentHash != bytes32(0), "Invalid hash");
        require(proofs[documentHash].timestamp == 0, "Hash already stored");

        uint256 proofTimestamp = block.timestamp;
        proofs[documentHash] = DocumentProof({
            timestamp: proofTimestamp,
            submittedBy: msg.sender
        });

        emit DocumentHashStored(documentHash, proofTimestamp, msg.sender);
    }

    function getDocumentProof(bytes32 documentHash) external view returns (uint256 timestamp, address submittedBy) {
        DocumentProof memory proof = proofs[documentHash];
        return (proof.timestamp, proof.submittedBy);
    }

    function documentHashExists(bytes32 documentHash) external view returns (bool) {
        return proofs[documentHash].timestamp != 0;
    }
}
