# produman-org/doctrine-encryption

Минимальный Symfony bundle для шифрования `string|null` полей Doctrine-сущностей через PHP-атрибут `#[Encrypted]`.

Шифрование выполняется через [Halite](https://github.com/paragonie/halite). В коде приложения поле остаётся обычной строкой, а в базу данных записывается ciphertext.

## Возможности

- один атрибут `#[Encrypted]` на поле сущности;
- Symfony configuration с production-safe defaults;
- автоматическое шифрование перед `persist` / `flush`;
- автоматическое расшифрование после загрузки сущности;
- пропуск лишнего шифрования при `flush`, если encrypted-поля не менялись;
- поддержка Doctrine proxy без повторного reflection-сканирования metadata;
- явные exceptions вместо silent failures;
- CLI-команды для генерации и проверки Halite key file.

## Установка

```bash
composer require produman-org/doctrine-encryption
```

Если Symfony Flex не подключил бандл автоматически, добавьте его вручную:

```php
// config/bundles.php
return [
    ProdumanOrg\DoctrineEncryption\DoctrineEncryptionBundle::class => ['all' => true],
];
```

## Конфигурация

```yaml
# config/packages/doctrine_encryption.yaml
doctrine_encryption:
    key_file: '%env(resolve:DOCTRINE_ENCRYPTION_KEY_FILE)%'
    auto_generate_key: false
    allow_plaintext: false
```

Значения по умолчанию безопасны для production:

- `key_file` - путь к Halite key file;
- `auto_generate_key=false` - ключ не создаётся молча при первом runtime-доступе;
- `allow_plaintext=false` - plaintext в encrypted-поле считается ошибкой.

Рекомендуемый путь для Symfony-приложения:

```dotenv
DOCTRINE_ENCRYPTION_KEY_FILE=config/secrets/prod/.Halite.key
```

Для другого окружения используйте соответствующую директорию, например `config/secrets/dev/.Halite.key` или `config/secrets/test/.Halite.key`.

## Ключ Шифрования

Ключ нужно создать явно:

```bash
php bin/console doctrine-encryption:generate-key
```

Команда создаёт директории при необходимости, записывает Halite key file и выставляет права `0600`. Существующий ключ не перезаписывается без `--force`:

```bash
php bin/console doctrine-encryption:generate-key --force
```

Для разовой операции можно переопределить путь из конфигурации:

```bash
php bin/console doctrine-encryption:generate-key --key-file=/run/secrets/doctrine-encryption.key
```

Используйте `--force` только если вы осознанно хотите заменить ключ. Старые данные, зашифрованные прежним ключом, после этого не расшифруются.

Проверить ключ:

```bash
php bin/console doctrine-encryption:validate-key
```

Или с явным путем:

```bash
php bin/console doctrine-encryption:validate-key --key-file=/run/secrets/doctrine-encryption.key
```

Проверяется наличие файла, читаемость, формат Halite key и права доступа.

## Production Usage

В production держите:

```yaml
doctrine_encryption:
    key_file: '%env(resolve:DOCTRINE_ENCRYPTION_KEY_FILE)%'
    auto_generate_key: false
    allow_plaintext: false
```

Если `auto_generate_key=false` и файл ключа отсутствует, библиотека выбросит `KeyNotFoundException`. Это намеренное поведение: новый пустой ключ в production привёл бы к невозможности расшифровать уже записанные данные.

`auto_generate_key=true` допустим только для локальной разработки, временных test environments или контролируемых ephemeral стендов.

## Docker / Kubernetes Warning

Не создавайте `.Halite.key` внутри Docker image во время build. Один и тот же image может использоваться в разных окружениях, а пересборка image может случайно заменить ключ.

Для Docker/Kubernetes передавайте ключ как secret/volume и указывайте путь через `DOCTRINE_ENCRYPTION_KEY_FILE`.

Пример:

```dotenv
DOCTRINE_ENCRYPTION_KEY_FILE=/run/secrets/doctrine-encryption.key
```

Убедитесь, что файл доступен PHP-процессу на чтение и имеет права `0600` или строже.

## Key Backup Warning

Потеря ключа = потеря данных.

Halite key file должен быть сохранён в надёжном backup-хранилище отдельно от базы данных. Если база восстановлена из backup, но ключ утерян или заменён, encrypted-колонки нельзя будет расшифровать.

Никогда не коммитьте `.Halite.key` в Git.

## Использование

```php
<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use ProdumanOrg\DoctrineEncryption\Attribute\Encrypted;

#[ORM\Entity]
final class Customer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Encrypted]
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $passportNumber = null;
}
```

Для зашифрованных полей лучше использовать `text`, потому что ciphertext длиннее исходной строки.

Readonly encrypted properties не поддерживаются:

```php
#[Encrypted]
private readonly string $secret; // ошибка конфигурации
```

## Plaintext Migration Mode

По умолчанию plaintext в encrypted-поле запрещён. Если в таблице уже есть legacy plaintext-значения, временно включите migration mode:

```yaml
doctrine_encryption:
    allow_plaintext: true
```

В этом режиме plaintext-значение будет прочитано как есть. Простая загрузка сущности или изменение другого поля не мигрирует значение в ciphertext. Оно будет зашифровано, когда изменится само encrypted-поле.

После миграции legacy-данных верните:

```yaml
doctrine_encryption:
    allow_plaintext: false
```

## Где Автоматическое Шифрование Не Сработает

Библиотека подключается через Doctrine lifecycle events. Автоматическое шифрование и расшифрование происходят только когда изменение проходит через обычный ORM-цикл `EntityManager::persist()` / `flush()`.

Следующие сценарии обходят subscriber:

- bulk DQL `UPDATE` / `DELETE`;
- прямые DBAL-запросы через `Connection`;
- raw SQL;
- массовые импорты и синхронизации, которые пишут в таблицы напрямую;
- любой код, который обходит `EntityManager::persist()` / `flush()`.

Для таких операций приложение должно шифровать значения вручную через `FieldEncryptorInterface` до записи в БД. Иначе encrypted-колонки получат plaintext, а при `allow_plaintext=false` последующая загрузка сущности завершится ошибкой.

## Current Limitations

- Поддерживаются только значения `string|null`.
- Key rotation пока не реализован.
- KMS/Vault integration пока не реализована.
- DBAL Type для прозрачного шифрования вне ORM lifecycle пока не реализован.
- DQL/SQL-поиск по plaintext-смыслу encrypted-поля невозможен: Halite использует случайный nonce и выдаёт новый ciphertext при каждом шифровании.
- Unique-ограничения, сортировка и сравнение по plaintext-смыслу encrypted-поля невозможны без отдельного осознанного индекса/хеша на стороне приложения.
- Bulk DQL/DBAL/raw SQL операции не шифруются автоматически.
- Readonly encrypted properties не поддерживаются.

## Разработка

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse --no-progress
vendor/bin/php-cs-fixer fix --dry-run --diff
```

Также доступны composer scripts:

```bash
composer test
composer analyse
composer cs-check
composer cs-fix
```
