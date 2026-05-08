<?php

declare(strict_types=1);

namespace DoctrineEncryption\Tests\Metadata;

use DoctrineEncryption\Metadata\EncryptedFieldMetadataFactory;
use DoctrineEncryption\Tests\Fixtures\InheritableSecretNote;
use DoctrineEncryption\Tests\Fixtures\PartialSecretNote;
use DoctrineEncryption\Tests\Fixtures\SecretNote;
use DoctrineEncryption\Tests\Fixtures\SecretNoteProxy;
use PHPUnit\Framework\TestCase;

final class EncryptedFieldMetadataFactoryTest extends TestCase
{
    public function testItFindsEncryptedProperties(): void
    {
        $factory = new EncryptedFieldMetadataFactory();

        $fields = $factory->forObject(new SecretNote('public', 'secret'));
        $names = array_map(static fn($field): string => $field->name, $fields);

        self::assertSame(['secret', 'nullableSecret'], $names);
    }

    public function testEncryptedFieldsCanReadAndWritePrivateProperties(): void
    {
        $note = new SecretNote('public', 'secret');
        $field = (new EncryptedFieldMetadataFactory())->forObject($note)[0];

        self::assertSame('secret', $field->getValue($note));

        $field->setValue($note, 'changed');

        self::assertSame('changed', $note->getSecret());
    }

    public function testItFindsOnlyRequestedEncryptedProperties(): void
    {
        $factory = new EncryptedFieldMetadataFactory();

        $fields = $factory->forObjectFieldNames(new SecretNote('public', 'secret'), [
            'title',
            'nullableSecret',
            'missing',
        ]);

        self::assertSame(['nullableSecret'], array_map(static fn($field): string => $field->name, $fields));
    }

    public function testEncryptedFieldsCanDetectUninitializedProperties(): void
    {
        $note = new PartialSecretNote();
        $field = (new EncryptedFieldMetadataFactory())->forObject($note)[0];

        self::assertFalse($field->isInitialized($note));
    }

    public function testItFindsEncryptedPropertiesDeclaredOnParentClasses(): void
    {
        $factory = new EncryptedFieldMetadataFactory();

        $fields = $factory->forObject(new SecretNoteProxy('secret'));

        self::assertSame(['secret'], array_map(static fn($field): string => $field->name, $fields));
    }

    public function testDoctrineProxyObjectsReuseParentClassMetadata(): void
    {
        $factory = new EncryptedFieldMetadataFactory();

        $parentFields = $factory->forObject(new InheritableSecretNote('secret'));
        $proxyFields = $factory->forObject(new SecretNoteProxy('secret'));

        self::assertSame($parentFields, $proxyFields);
    }
}
