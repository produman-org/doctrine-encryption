<?php

declare(strict_types=1);

namespace DoctrineEncryption\Tests\EventSubscriber;

use DoctrineEncryption\EventSubscriber\DoctrineEncryptionSubscriber;
use DoctrineEncryption\Metadata\EncryptedFieldMetadataFactory;
use DoctrineEncryption\Tests\Fixtures\SecretNote;
use DoctrineEncryption\Tests\Support\InMemoryFieldEncryptor;
use PHPUnit\Framework\TestCase;

final class DoctrineEncryptionSubscriberTest extends TestCase
{
    public function testItEncryptsBeforePersistAndRestoresPlaintextAfterPersist(): void
    {
        $note = new SecretNote('public', 'secret');
        $subscriber = new DoctrineEncryptionSubscriber(new EncryptedFieldMetadataFactory(), new InMemoryFieldEncryptor());

        $subscriber->encryptObject($note);

        self::assertSame('enc:secret', $note->getSecret());

        $subscriber->decryptObject($note);

        self::assertSame('secret', $note->getSecret());
    }

    public function testItLeavesNullFieldsUntouched(): void
    {
        $note = new SecretNote('public', 'secret', null);
        $subscriber = new DoctrineEncryptionSubscriber(new EncryptedFieldMetadataFactory(), new InMemoryFieldEncryptor());

        $subscriber->encryptObject($note);

        self::assertNull($note->getNullableSecret());
    }
}
