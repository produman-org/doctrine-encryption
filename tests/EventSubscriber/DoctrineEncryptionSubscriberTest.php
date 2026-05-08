<?php

declare(strict_types=1);

namespace DoctrineEncryption\Tests\EventSubscriber;

use DoctrineEncryption\EventSubscriber\DoctrineEncryptionSubscriber;
use DoctrineEncryption\Metadata\EncryptedFieldMetadataFactory;
use DoctrineEncryption\Tests\Fixtures\SecretNote;
use DoctrineEncryption\Tests\Support\InMemoryFieldEncryptor;
use Doctrine\Persistence\Event\PreUpdateEventArgs;
use Doctrine\Persistence\ObjectManager;
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

    public function testPreUpdateDoesNotEncryptFieldsThatDidNotChange(): void
    {
        $note = new SecretNote('public', 'secret');
        $encryptor = new InMemoryFieldEncryptor();
        $subscriber = new DoctrineEncryptionSubscriber(new EncryptedFieldMetadataFactory(), $encryptor);
        $changeSet = [
            'title' => ['public', 'changed'],
        ];

        $subscriber->preUpdate(new PreUpdateEventArgs($note, $this->createStub(ObjectManager::class), $changeSet));

        self::assertSame('secret', $note->getSecret());
        self::assertSame([], $encryptor->encryptedValues);
        self::assertSame(['public', 'changed'], $changeSet['title']);
    }

    public function testPreUpdateEncryptsAndPostUpdateRestoresOnlyChangedEncryptedFields(): void
    {
        $note = new SecretNote('public', 'changed secret', 'unchanged nullable');
        $encryptor = new InMemoryFieldEncryptor();
        $subscriber = new DoctrineEncryptionSubscriber(new EncryptedFieldMetadataFactory(), $encryptor);
        $changeSet = [
            'secret' => ['old secret', 'changed secret'],
        ];
        $event = new PreUpdateEventArgs($note, $this->createStub(ObjectManager::class), $changeSet);

        $subscriber->preUpdate($event);

        self::assertSame('enc:changed secret', $note->getSecret());
        self::assertSame(['changed secret'], $encryptor->encryptedValues);
        self::assertSame(['old secret', 'enc:changed secret'], $changeSet['secret']);
        self::assertSame('unchanged nullable', $note->getNullableSecret());

        $subscriber->postUpdate($event);

        self::assertSame('changed secret', $note->getSecret());
        self::assertSame(['enc:changed secret'], $encryptor->decryptedValues);
        self::assertSame('unchanged nullable', $note->getNullableSecret());
    }
}
