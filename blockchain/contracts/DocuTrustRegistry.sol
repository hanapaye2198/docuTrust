// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

contract DocuTrustRegistry {
    struct Document {
        string hash;
        uint256 timestamp;
        address issuer;
    }

    mapping(string => Document) private documents;

    event DocumentRegistered(string hash, uint256 timestamp, address issuer);

    function registerDocument(string memory _hash) external {
        require(bytes(_hash).length > 0, "Hash is required");
        require(documents[_hash].timestamp == 0, "Document already registered");

        uint256 registeredAt = block.timestamp;
        documents[_hash] = Document({
            hash: _hash,
            timestamp: registeredAt,
            issuer: msg.sender
        });

        emit DocumentRegistered(_hash, registeredAt, msg.sender);
    }

    function verifyDocument(string memory _hash) external view returns (bool exists, uint256 timestamp, address issuer) {
        Document memory document = documents[_hash];
        exists = document.timestamp != 0;
        timestamp = document.timestamp;
        issuer = document.issuer;
    }
}
