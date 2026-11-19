// SPDX-License-Identifier: MIT
pragma solidity ^0.8.30;

/// @title Registro documental académico GOREPA
/// @notice Guarda huellas e identificadores; no documentos, nombres ni datos personales.
/// @dev Prototipo sin auditoría externa. Primero probar en Remix VM; luego Sepolia.
contract RegistroDocumentalGOREPA {
    struct Registro {
        bytes32 fileHash;
        bytes32 metadataHash;
        bytes32 previousId;
        address registrar;
        uint256 timestamp;
        bool exists;
    }

    address public owner;
    address public pendingOwner;
    mapping(address => bool) private operators;
    mapping(bytes32 => Registro) private records;
    mapping(bytes32 => bytes32) public supersededBy;
    mapping(bytes32 => bytes32) public documentByFileHash;

    error Unauthorized();
    error InvalidAddress();
    error InvalidHash();
    error DuplicateDocument();
    error DuplicateFile();
    error UnknownPreviousDocument();
    error AlreadySuperseded();
    error UnknownDocument();

    event OperatorUpdated(address indexed operator, bool authorized);
    event OwnershipTransferStarted(address indexed previousOwner, address indexed candidate);
    event OwnershipTransferred(address indexed previousOwner, address indexed newOwner);
    event DocumentRegistered(
        bytes32 indexed documentId,
        bytes32 fileHash,
        bytes32 metadataHash,
        bytes32 indexed previousId,
        address indexed registrar,
        uint256 timestamp
    );

    constructor() {
        owner = msg.sender;
        emit OwnershipTransferred(address(0), msg.sender);
    }

    modifier onlyOwner() {
        if (msg.sender != owner) revert Unauthorized();
        _;
    }

    modifier onlyOperator() {
        if (!isOperator(msg.sender)) revert Unauthorized();
        _;
    }

    function isOperator(address account) public view returns (bool) {
        return account == owner || operators[account];
    }

    function setOperator(address account, bool authorized) external onlyOwner {
        if (account == address(0) || account == owner) revert InvalidAddress();
        operators[account] = authorized;
        emit OperatorUpdated(account, authorized);
    }

    function transferOwnership(address candidate) external onlyOwner {
        if (candidate == address(0) || candidate == owner) revert InvalidAddress();
        pendingOwner = candidate;
        emit OwnershipTransferStarted(owner, candidate);
    }

    function acceptOwnership() external {
        if (msg.sender != pendingOwner) revert Unauthorized();
        address previousOwner = owner;
        owner = pendingOwner;
        pendingOwner = address(0);
        // La transferencia de administración no conserva privilegios del dueño anterior.
        operators[previousOwner] = false;
        emit OwnershipTransferred(previousOwner, owner);
    }

    /// @param previousId Cero para un registro nuevo; ID anterior para su sustitución.
    /// @dev Los registros anteriores nunca se editan ni se eliminan.
    function registerDocument(
        bytes32 documentId,
        bytes32 fileHash,
        bytes32 metadataHash,
        bytes32 previousId
    ) external onlyOperator {
        if (documentId == bytes32(0) || fileHash == bytes32(0) || metadataHash == bytes32(0)) revert InvalidHash();
        if (records[documentId].exists) revert DuplicateDocument();
        if (documentByFileHash[fileHash] != bytes32(0)) revert DuplicateFile();
        if (previousId != bytes32(0)) {
            if (!records[previousId].exists) revert UnknownPreviousDocument();
            if (supersededBy[previousId] != bytes32(0)) revert AlreadySuperseded();
            supersededBy[previousId] = documentId;
        }
        records[documentId] = Registro(fileHash, metadataHash, previousId, msg.sender, block.timestamp, true);
        documentByFileHash[fileHash] = documentId;
        emit DocumentRegistered(documentId, fileHash, metadataHash, previousId, msg.sender, block.timestamp);
    }

    function getDocument(bytes32 documentId) external view returns (Registro memory) {
        if (!records[documentId].exists) revert UnknownDocument();
        return records[documentId];
    }

    function verifyDocument(bytes32 documentId, bytes32 fileHash) external view returns (bool) {
        return records[documentId].exists && records[documentId].fileHash == fileHash;
    }
}
