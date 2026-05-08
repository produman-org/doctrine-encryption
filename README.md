# Doctrine Encryption Bundle

Минимальный Symfony bundle для шифрования полей Doctrine-сущностей через один PHP-атрибут. Шифрование выполняется Halite, ключ хранится в отдельном файле.

## Возможности

- один атрибут `#[Encrypted]` на поле сущности;
- без YAML/XML/PHP-конфига бандла;
- автоматическое шифрование перед `persist`/`update`;
- автоматическое расшифрование после загрузки сущности;
- пропуск лишнего шифрования при `flush`, если encrypted-поля не менялись;
- поддержка Doctrine proxy без повторного reflection-сканирования metadata;
- поддерживаются значения `string|null`.

## Установка

```bash
composer require doctrine-encryption/doctrine-encryption-bundle
```

Если Symfony Flex не подключил бандл автоматически, добавьте его вручную:

```php
// config/bundles.php
return [
    DoctrineEncryption\DoctrineEncryptionBundle::class => ['all' => true],
];
```

## Ключ шифрования

Бандл автоматически создает Halite key file при первом шифровании или расшифровании. По умолчанию файл называется `.Halite.key` и лежит в окружении Symfony:

```
config/secrets/%kernel.environment%/.Halite.key
```

Например, для `dev` окружения это будет `config/secrets/dev/.Halite.key`, для `prod` - `config/secrets/prod/.Halite.key`.

При автоматическом создании бандл выставляет права `0600` на `.Halite.key`. Файл ключа нельзя коммитить. Потеря или замена ключа означает потерю возможности расшифровать уже записанные данные.

## Использование

```php
<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use DoctrineEncryption\Attribute\Encrypted;

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

В коде приложения поле остается обычной строкой. В базу данных записывается ciphertext, при загрузке сущности значение возвращается в plaintext.

Для зашифрованных полей лучше использовать `text`, потому что ciphertext длиннее исходной строки.

Новые значения сохраняются с префиксом `doctrine-encryption:halite:v1:`. Это позволяет бандлу отличать свой ciphertext от plaintext/legacy значений и не пытаться расшифровывать строки, которые не были зашифрованы этой библиотекой.

## Ограничения

- Бандл шифрует только свойства, помеченные `#[Encrypted]`.
- Поля должны быть `string|null`; массивы, объекты и числа нужно сериализовать на стороне приложения.
- Ротация ключей и миграция уже зашифрованных данных пока не реализованы.
- DQL/SQL-поиск по зашифрованному значению невозможен, потому что Halite использует случайный nonce и выдает новый ciphertext при каждом шифровании.
- Legacy plaintext значения читаются как есть. Простая загрузка сущности или изменение другого поля не мигрирует такое значение в ciphertext; поле будет зашифровано только при изменении самого encrypted-поля.
- Doctrine lifecycle events не срабатывают для bulk DQL `UPDATE`/`DELETE`, прямых DBAL-запросов, raw SQL и сторонних импортов, которые обходят `EntityManager::persist()`/`flush()`. Такие операции должны шифровать значения вручную через `FieldEncryptorInterface` или не изменять encrypted-колонки.
- Из-за недетерминированного шифрования нельзя строить unique-ограничения, индексы поиска или сравнения по plaintext-смыслу encrypted-поля. Для поиска нужен отдельный осознанный индекс/хеш на стороне приложения.

## Разработка

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix --dry-run --diff
```

Также доступны composer scripts:

```bash
composer test
composer analyse
composer cs-check
composer cs-fix
```
