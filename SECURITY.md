# Security Policy

## Supported Versions

| Version | Supported |
| ------- | --------- |
| 0.1.x   | Yes       |

Only the latest `0.1.x` release receives security fixes.

## Reporting a Vulnerability

Report security issues through GitHub Security Advisories for this repository.
Do not open a public issue with exploit details, key material, ciphertexts, or
application secrets.

Please include:

- affected package version;
- PHP, Symfony, Doctrine ORM and Halite versions;
- a minimal reproduction when possible;
- expected and actual impact;
- any suggested mitigation if you have one.

## Scope

Security bugs include issues that can cause plaintext disclosure, incorrect
encryption/decryption, unsafe key-file handling, or bypass of the documented
plaintext policy.

Out of scope:

- key management infrastructure such as KMS, Vault, HSM or secret managers;
- performance issues without security impact;
- issues that require an attacker to already have both database access and the
  Halite key file;
- vulnerabilities in Halite or libsodium themselves, unless this bundle uses
  them incorrectly.

## Security Model

This package provides field-level encryption at the Doctrine ORM lifecycle
layer. It protects against direct database reads when the attacker has database
access but does not have the Halite key file.

It does not protect against:

- an attacker who has both the database and the key file;
- application-level compromise where plaintext is available in PHP memory;
- raw SQL, DBAL writes or bulk DQL operations that bypass Doctrine lifecycle
  events;
- searches, sorting, unique constraints or indexes based on plaintext values.

## Key Handling Expectations

- Generate keys outside the application image and back them up securely.
- Do not commit `.Halite.key` to VCS.
- Keep key-file permissions at `0600` or stricter.
- Validate production keys with `bin/console doctrine-encryption:validate-key`.
- In Docker/Kubernetes, mount the key as a secret/volume and point
  `DOCTRINE_ENCRYPTION_KEY_FILE` at that path.

Operational mistakes such as losing the encryption key are not recoverable by
the library. If the Halite key is lost or replaced, already encrypted values
cannot be decrypted.

## Cryptographic Details

Encryption is delegated to `paragonie/halite` via
`ParagonIE\Halite\Symmetric\Crypto::encrypt()`.

Halite uses libsodium authenticated symmetric encryption with a random nonce per
encryption operation. The ciphertext is prefixed with
`doctrine-encryption:halite:v1:` so the bundle can distinguish its ciphertext
from legacy plaintext while `allow_plaintext` migration mode is enabled.
