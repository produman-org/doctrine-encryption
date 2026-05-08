# Инструкции для проекта

## Область

- Репозиторий: `produman-org/doctrine-encryption`.
- Назначение: Symfony-бандл для шифрования `string|null` полей Doctrine-сущностей через Halite.
- Публичная поверхность: `#[Encrypted]`, `FieldEncryptorInterface`, `CiphertextDetectorInterface`, package-specific exceptions, console commands и `DoctrineEncryptionBundle`.
- Предпочтительная модель для работы в этом репозитории: GPT-5.5.

## Архитектура

- `src/Attribute/Encrypted.php`: маркерный атрибут для свойств Doctrine-сущностей.
- `src/Encryption/HaliteFieldEncryptor.php`: реализация `FieldEncryptorInterface` на базе Halite.
- `src/EventSubscriber/DoctrineEncryptionSubscriber.php`: Doctrine lifecycle subscriber, который шифрует и расшифровывает поля сущностей.
- `src/Metadata/EncryptedFieldMetadataFactory.php`: reflection-based кеш метаданных для зашифрованных свойств.
- `src/Metadata/EncryptedFieldMetadata.php`: тонкий value object вокруг `ReflectionProperty`.
- `src/Key/KeyFileManager.php`: internal service для загрузки, генерации и проверки Halite key file.
- `src/Command/GenerateKeyCommand.php` и `src/Command/ValidateKeyCommand.php`: CLI для lifecycle ключа.
- `src/DependencyInjection/Configuration.php`, `src/DependencyInjection/DoctrineEncryptionExtension.php` и `config/services.php`: конфигурация и регистрация сервисов Symfony.
- `src/DoctrineEncryptionBundle.php`: точка входа бандла.

## Правила рантайма

- Относиться к бандлу как к переиспользуемой библиотеке, а не к приложению.
- Держать API маленьким и явным. Новая конфигурация должна иметь production-safe default.
- Не помечать Doctrine ORM entities или изменяемую инфраструктуру Symfony/Doctrine как `readonly`, если корректность такого решения не доказана.
- Default `key_file` задаётся через `%env(resolve:DOCTRINE_ENCRYPTION_KEY_FILE)%`; рекомендуемый путь: `config/secrets/%kernel.environment%/.Halite.key`.
- Не возвращать silent auto-generation ключа по умолчанию: `auto_generate_key=false` должен падать с `KeyNotFoundException`.
- Plaintext в encrypted-полях по умолчанию запрещён: `allow_plaintext=false`. Legacy режим включается только явно и временно.
- Сохранять контракт префикса ciphertext, который использует `HaliteFieldEncryptor`.
- Не менять молча поведение для legacy plaintext-значений, bulk DQL/DBAL операций или обработки Doctrine proxy.

## Правила редактирования

- Предпочитать локальные существующие паттерны, а не вводить новые абстракции.
- Держать изменения строго в рамках запрошенного поведения.
- Не откатывать пользовательские изменения или несвязанные правки.
- Для ручного редактирования использовать `apply_patch`.
- Для shell-команд в этом workspace предпочитать `lean-ctx -c`.
- По умолчанию использовать ASCII, если файл уже не использует другой набор символов.

## Правила тестирования

- Unit-тесты живут в `tests/` и должны оставаться небольшими и ориентированными на поведение.
- Интеграционные тесты должны покрывать реальный Doctrine ORM и Symfony container, когда изменение затрагивает runtime wiring или flush-поведение.
- Тестовые фикстуры держать простыми и явными.
- Если поведение меняется, обновлять README и связанные test doubles в том же изменении.
- После нетривиальных изменений запускать `vendor/bin/phpunit`, `vendor/bin/phpstan analyse --no-progress` и `composer cs-check`.

## Команды

- `composer install`
- `vendor/bin/phpunit`
- `vendor/bin/phpstan analyse --no-progress`
- `vendor/bin/php-cs-fixer fix --dry-run --diff`
- `composer test`
- `composer analyse`
- `composer cs-check`
- `composer cs-fix`

## Примечания по проекту

- `.Halite.key` нельзя коммитить; потеря ключа означает потерю доступа к уже зашифрованным данным.
- Автоматическая генерация ключа допустима только при явном `auto_generate_key=true`.
- `ext-sodium` требуется во время выполнения.
- `ext-pdo_sqlite` требуется для SQLite integration test suite.
- Doctrine lifecycle events не срабатывают для bulk DQL/DBAL/raw SQL путей.
- В качестве основных gates корректности репозиторий использует README, PHPStan, PHPUnit и PHP-CS-Fixer.
