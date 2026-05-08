# Project Agents

## Scope

- Repository: produman-org/doctrine-encryption.
- Purpose: Symfony bundle for encrypting Doctrine entity fields with Halite.
- Public surface: a single `#[Encrypted]` attribute, one encryptor service, one Doctrine subscriber, and bundle auto-registration.
- Preferred model for work in this repository: GPT-5.5.

## Architecture

- `src/Attribute/Encrypted.php`: marker attribute for Doctrine entity properties.
- `src/Encryption/HaliteFieldEncryptor.php`: Halite-backed implementation of `FieldEncryptorInterface`.
- `src/EventSubscriber/DoctrineEncryptionSubscriber.php`: Doctrine lifecycle subscriber that encrypts/decrypts entity fields.
- `src/Metadata/EncryptedFieldMetadataFactory.php`: reflection-based metadata cache for encrypted properties.
- `src/Metadata/EncryptedFieldMetadata.php`: thin value object around `ReflectionProperty`.
- `src/DependencyInjection/DoctrineEncryptionExtension.php` and `config/services.php`: Symfony service registration.
- `src/DoctrineEncryptionBundle.php`: bundle entry point.

## Runtime Rules

- Treat the bundle as a reusable library, not an application.
- Keep the API small and explicit. Avoid new configuration unless the request clearly needs it.
- Do not mark Doctrine ORM entities or mutable Symfony/Doctrine infrastructure as `readonly` unless the runtime behavior is proven safe.
- Keep the key file path as `config/secrets/%kernel.environment%/.Halite.key`.
- Preserve the ciphertext prefix contract used by `HaliteFieldEncryptor`.
- Do not silently change behavior for legacy plaintext values, bulk DQL/DBAL operations, or Doctrine proxy handling.

## Editing Rules

- Prefer existing local patterns over introducing new abstractions.
- Keep edits tightly scoped to the requested behavior.
- Do not revert user changes or unrelated edits.
- Use `apply_patch` for manual file edits.
- Prefer `lean-ctx -c` for shell commands in this workspace.
- Default to ASCII unless the file already uses a different character set.

## Testing Rules

- Unit tests live under `tests/` and should stay small and behavior-focused.
- Integration tests should cover real Doctrine ORM and Symfony container behavior when the change touches runtime wiring or flush behavior.
- Keep test fixtures simple and explicit.
- If behavior changes, update the README and any relevant test doubles in the same change.
- Run `vendor/bin/phpunit`, `vendor/bin/phpstan analyse --no-progress`, and `composer cs-check` after nontrivial changes.

## Commands

- `composer install`
- `vendor/bin/phpunit`
- `vendor/bin/phpstan analyse --no-progress`
- `vendor/bin/php-cs-fixer fix --dry-run --diff`
- `composer test`
- `composer analyse`
- `composer cs-check`
- `composer cs-fix`

## Project Notes

- `.Halite.key` is created automatically in `config/secrets/%kernel.environment%/`.
- `ext-sodium` is required at runtime.
- `ext-pdo_sqlite` is required for the SQLite integration test suite.
- Doctrine lifecycle events do not fire for bulk DQL/DBAL/raw SQL paths.
- The repository uses README, PHPStan, PHPUnit, and PHP-CS-Fixer as the main correctness gates.
