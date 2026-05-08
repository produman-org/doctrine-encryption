<?php

declare(strict_types=1);

namespace ProdumanOrg\DoctrineEncryption\Tests\EventSubscriber;

use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\Event\OnClearEventArgs;
use Doctrine\Persistence\Event\PreUpdateEventArgs;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use ProdumanOrg\DoctrineEncryption\EventSubscriber\DoctrineEncryptionSubscriber;
use ProdumanOrg\DoctrineEncryption\Metadata\EncryptedFieldMetadataFactory;
use ProdumanOrg\DoctrineEncryption\Tests\Fixtures\PartialSecretNote;
use ProdumanOrg\DoctrineEncryption\Tests\Fixtures\PublicNote;
use ProdumanOrg\DoctrineEncryption\Tests\Fixtures\SecretNote;
use ProdumanOrg\DoctrineEncryption\Tests\Support\InMemoryFieldEncryptor;
use ProdumanOrg\DoctrineEncryption\Tests\Support\RecordingObjectManager;

final class DoctrineEncryptionSubscriberTest extends TestCase
{
    public function testItSubscribesToOnClearToReleaseRememberedFields(): void
    {
        $subscriber = new DoctrineEncryptionSubscriber(new EncryptedFieldMetadataFactory(), new InMemoryFieldEncryptor());

        self::assertContains(Events::onClear, $subscriber->getSubscribedEvents());
    }

    public function testPostLoadSkipsUnitOfWorkForObjectsWithoutEncryptedFields(): void
    {
        $objectManager = new RecordingObjectManager();
        $subscriber = new DoctrineEncryptionSubscriber(new EncryptedFieldMetadataFactory(), new InMemoryFieldEncryptor());

        $subscriber->postLoad(new LifecycleEventArgs(new PublicNote('public'), $objectManager));

        self::assertSame(0, $objectManager->getUnitOfWorkCalls);
        self::assertSame([], $objectManager->unitOfWork->originalEntityProperties);
    }

    public function testPostLoadDecryptsEncryptedFieldsAndSyncsOriginalData(): void
    {
        $objectManager = new RecordingObjectManager();
        $note = new SecretNote('public', 'enc:secret', 'legacy nullable');
        $subscriber = new DoctrineEncryptionSubscriber(new EncryptedFieldMetadataFactory(), new InMemoryFieldEncryptor());

        $subscriber->postLoad(new LifecycleEventArgs($note, $objectManager));

        self::assertSame('secret', $note->getSecret());
        self::assertSame('legacy nullable', $note->getNullableSecret());
        self::assertSame(1, $objectManager->getUnitOfWorkCalls);
        self::assertSame([
            [
                'objectId' => spl_object_id($note),
                'property' => 'secret',
                'value' => 'secret',
            ],
        ], $objectManager->unitOfWork->originalEntityProperties);
    }

    public function testPostLoadSkipsUninitializedEncryptedFieldsWhenSyncingOriginalData(): void
    {
        $objectManager = new RecordingObjectManager();
        $subscriber = new DoctrineEncryptionSubscriber(new EncryptedFieldMetadataFactory(), new InMemoryFieldEncryptor());

        $subscriber->postLoad(new LifecycleEventArgs(new PartialSecretNote(), $objectManager));

        self::assertSame(0, $objectManager->getUnitOfWorkCalls);
        self::assertSame([], $objectManager->unitOfWork->originalEntityProperties);
    }

    public function testPostLoadSkipsUnitOfWorkWhenEncryptedFieldsAreNull(): void
    {
        $objectManager = new RecordingObjectManager();
        $note = new SecretNote('public', null, null);
        $subscriber = new DoctrineEncryptionSubscriber(new EncryptedFieldMetadataFactory(), new InMemoryFieldEncryptor());

        $subscriber->postLoad(new LifecycleEventArgs($note, $objectManager));

        self::assertNull($note->getSecret());
        self::assertNull($note->getNullableSecret());
        self::assertSame(0, $objectManager->getUnitOfWorkCalls);
        self::assertSame([], $objectManager->unitOfWork->originalEntityProperties);
    }

    public function testItEncryptsBeforePersistAndRestoresPlaintextAfterPersist(): void
    {
        $note = new SecretNote('public', 'secret');
        $subscriber = new DoctrineEncryptionSubscriber(new EncryptedFieldMetadataFactory(), new InMemoryFieldEncryptor());

        $subscriber->encryptObject($note);

        self::assertSame('enc:secret', $note->getSecret());

        $subscriber->decryptObject($note);

        self::assertSame('secret', $note->getSecret());
    }

    public function testPrePersistAndPostPersistRestorePlaintextWithoutDecryptingAgain(): void
    {
        $objectManager = new RecordingObjectManager();
        $note = new SecretNote('public', 'secret', 'nullable secret');
        $encryptor = new InMemoryFieldEncryptor();
        $subscriber = new DoctrineEncryptionSubscriber(new EncryptedFieldMetadataFactory(), $encryptor);
        $event = new LifecycleEventArgs($note, $objectManager);

        $subscriber->prePersist($event);

        self::assertSame('enc:secret', $note->getSecret());
        self::assertSame('enc:nullable secret', $note->getNullableSecret());

        $subscriber->postPersist($event);

        self::assertSame('secret', $note->getSecret());
        self::assertSame('nullable secret', $note->getNullableSecret());
        self::assertSame([], $encryptor->decryptedValues);
        self::assertSame(1, $objectManager->getUnitOfWorkCalls);
        self::assertSame([
            [
                'objectId' => spl_object_id($note),
                'property' => 'secret',
                'value' => 'secret',
            ],
            [
                'objectId' => spl_object_id($note),
                'property' => 'nullableSecret',
                'value' => 'nullable secret',
            ],
        ], $objectManager->unitOfWork->originalEntityProperties);
    }

    public function testPostPersistSkipsUnitOfWorkWhenNoFieldsWereEncrypted(): void
    {
        $objectManager = new RecordingObjectManager();
        $note = new SecretNote('public', null, null);
        $encryptor = new InMemoryFieldEncryptor();
        $subscriber = new DoctrineEncryptionSubscriber(new EncryptedFieldMetadataFactory(), $encryptor);
        $event = new LifecycleEventArgs($note, $objectManager);

        $subscriber->prePersist($event);
        $subscriber->postPersist($event);

        self::assertNull($note->getSecret());
        self::assertNull($note->getNullableSecret());
        self::assertSame([], $encryptor->encryptedValues);
        self::assertSame([], $encryptor->decryptedValues);
        self::assertSame(0, $objectManager->getUnitOfWorkCalls);
        self::assertSame([], $objectManager->unitOfWork->originalEntityProperties);
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
        $objectManager = new RecordingObjectManager();
        $subscriber = new DoctrineEncryptionSubscriber(new EncryptedFieldMetadataFactory(), $encryptor);
        $changeSet = [
            'secret' => ['old secret', 'changed secret'],
        ];
        $event = new PreUpdateEventArgs($note, $objectManager, $changeSet);

        $subscriber->preUpdate($event);

        self::assertSame('enc:changed secret', $note->getSecret());
        self::assertSame(['changed secret'], $encryptor->encryptedValues);
        self::assertSame(['old secret', 'enc:changed secret'], $changeSet['secret']);
        self::assertSame('unchanged nullable', $note->getNullableSecret());

        $subscriber->postUpdate($event);

        self::assertSame('changed secret', $note->getSecret());
        self::assertSame([], $encryptor->decryptedValues);
        self::assertSame('unchanged nullable', $note->getNullableSecret());
        self::assertSame(1, $objectManager->getUnitOfWorkCalls);
        self::assertSame([
            [
                'objectId' => spl_object_id($note),
                'property' => 'secret',
                'value' => 'changed secret',
            ],
        ], $objectManager->unitOfWork->originalEntityProperties);
    }

    public function testPreUpdateDoesNotCallEncryptorWhenEncryptedFieldChangesToNull(): void
    {
        $note = new SecretNote('public', null);
        $encryptor = new InMemoryFieldEncryptor();
        $subscriber = new DoctrineEncryptionSubscriber(new EncryptedFieldMetadataFactory(), $encryptor);
        $changeSet = [
            'secret' => ['old secret', null],
        ];
        $event = new PreUpdateEventArgs($note, $this->createStub(ObjectManager::class), $changeSet);

        $subscriber->preUpdate($event);
        $subscriber->postUpdate($event);

        self::assertNull($note->getSecret());
        self::assertSame([], $encryptor->encryptedValues);
        self::assertSame([], $encryptor->decryptedValues);
        self::assertSame(['old secret', null], $changeSet['secret']);
    }

    public function testRepeatedPreUpdateRestoresPendingCiphertextBeforeEncryptingAgain(): void
    {
        $note = new SecretNote('public', 'changed secret');
        $encryptor = new InMemoryFieldEncryptor();
        $subscriber = new DoctrineEncryptionSubscriber(new EncryptedFieldMetadataFactory(), $encryptor);
        $firstChangeSet = [
            'secret' => ['old secret', 'changed secret'],
        ];
        $secondChangeSet = [
            'secret' => ['old secret', 'enc:changed secret'],
        ];

        $subscriber->preUpdate(new PreUpdateEventArgs($note, $this->createStub(ObjectManager::class), $firstChangeSet));
        $subscriber->preUpdate(new PreUpdateEventArgs($note, $this->createStub(ObjectManager::class), $secondChangeSet));

        self::assertSame('enc:changed secret', $note->getSecret());
        self::assertSame(['changed secret', 'changed secret'], $encryptor->encryptedValues);
        self::assertSame([], $encryptor->decryptedValues);
        self::assertSame(['old secret', 'enc:changed secret'], $secondChangeSet['secret']);
    }

    public function testOnClearReleasesRememberedFields(): void
    {
        $note = new SecretNote('public', 'changed secret');
        $encryptor = new InMemoryFieldEncryptor();
        $subscriber = new DoctrineEncryptionSubscriber(new EncryptedFieldMetadataFactory(), $encryptor);
        $changeSet = [
            'secret' => ['old secret', 'changed secret'],
        ];
        $event = new PreUpdateEventArgs($note, $this->createStub(ObjectManager::class), $changeSet);

        $subscriber->preUpdate($event);
        $subscriber->onClear(new OnClearEventArgs($this->createStub(ObjectManager::class)));
        $subscriber->postUpdate($event);

        self::assertSame('enc:changed secret', $note->getSecret());
        self::assertSame([], $encryptor->decryptedValues);
    }
}
