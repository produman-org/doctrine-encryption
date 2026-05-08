# Инструкции для проекта

## Область

- Репозиторий: `produman-org/doctrine-encryption`.
- Назначение: Symfony-бандл для шифрования полей Doctrine-сущностей через Halite.
- Публичная поверхность: один атрибут `#[Encrypted]`, один сервис-шифровальщик, один Doctrine-subscriber и автоподключение бандла.
- Предпочтительная модель для работы в этом репозитории: GPT-5.5.

## Архитектура

- `src/Attribute/Encrypted.php`: маркерный атрибут для свойств Doctrine-сущностей.
- `src/Encryption/HaliteFieldEncryptor.php`: реализация `FieldEncryptorInterface` на базе Halite.
- `src/EventSubscriber/DoctrineEncryptionSubscriber.php`: Doctrine lifecycle subscriber, который шифрует и расшифровывает поля сущностей.
- `src/Metadata/EncryptedFieldMetadataFactory.php`: reflection-based кеш метаданных для зашифрованных свойств.
- `src/Metadata/EncryptedFieldMetadata.php`: тонкий value object вокруг `ReflectionProperty`.
- `src/DependencyInjection/DoctrineEncryptionExtension.php` и `config/services.php`: регистрация сервисов Symfony.
- `src/DoctrineEncryptionBundle.php`: точка входа бандла.

## Правила рантайма

- Относиться к бандлу как к переиспользуемой библиотеке, а не к приложению.
- Держать API маленьким и явным. Не добавлять новую конфигурацию, если этого прямо не требует задача.
- Не помечать Doctrine ORM entities или изменяемую инфраструктуру Symfony/Doctrine как `readonly`, если корректность такого решения не доказана.
- Сохранять путь key file: `config/secrets/%kernel.environment%/.Halite.key`.
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

- `.Halite.key` создается автоматически в `config/secrets/%kernel.environment%/`.
- `ext-sodium` требуется во время выполнения.
- `ext-pdo_sqlite` требуется для SQLite integration test suite.
- Doctrine lifecycle events не срабатывают для bulk DQL/DBAL/raw SQL путей.
- В качестве основных gates корректности репозиторий использует README, PHPStan, PHPUnit и PHP-CS-Fixer.
