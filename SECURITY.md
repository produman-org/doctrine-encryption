# Security Policy

## Supported Versions

| Version | Supported |
| ------- | --------- |
| 0.1.x   | Yes       |

## Reporting a Vulnerability

Report security issues through GitHub Security Advisories for this repository.
Do not open a public issue with exploit details, key material, ciphertexts, or
application secrets.

Please include:

- affected package version;
- PHP, Symfony, Doctrine ORM and Halite versions;
- a minimal reproduction when possible;
- expected and actual impact.

## Scope

Security bugs include issues that can cause plaintext disclosure, incorrect
encryption/decryption, unsafe key-file handling, or bypass of the documented
plaintext policy.

Operational mistakes such as losing the encryption key are not recoverable by
the library. If the Halite key is lost or replaced, already encrypted values
cannot be decrypted.

## Key Handling Expectations

- Generate keys outside the application image and back them up securely.
- Do not commit `.Halite.key` to VCS.
- Keep key-file permissions at `0600` or stricter.
- Validate production keys with `bin/console doctrine-encryption:validate-key`.
