<?php

declare(strict_types=1);

namespace DoctrineEncryption\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PreUpdateEventArgs as OrmPreUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\Event\OnClearEventArgs;
use Doctrine\Persistence\Event\PreUpdateEventArgs as PersistencePreUpdateEventArgs;
use Doctrine\Persistence\ObjectManager;
use DoctrineEncryption\Contract\CiphertextDetectorInterface;
use DoctrineEncryption\Contract\FieldEncryptorInterface;
use DoctrineEncryption\Metadata\EncryptedFieldMetadata;
use DoctrineEncryption\Metadata\EncryptedFieldMetadataFactory;
use SplObjectStorage;
use UnexpectedValueException;

final class DoctrineEncryptionSubscriber implements EventSubscriber
{
    /**
     * @var SplObjectStorage<object, array<string, string>>
     */
    private SplObjectStorage $encryptedFieldValuesByObject;

    public function __construct(
        private readonly EncryptedFieldMetadataFactory $metadataFactory,
        private readonly FieldEncryptorInterface $encryptor,
    ) {
        $this->encryptedFieldValuesByObject = new SplObjectStorage();
    }

    /**
     * @return list<string>
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::postLoad,
            Events::prePersist,
            Events::postPersist,
            Events::preUpdate,
            Events::postUpdate,
            Events::onClear,
        ];
    }

    /**
     * @param LifecycleEventArgs<ObjectManager> $args
     */
    public function postLoad(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        $fields = $this->metadataFactory->forObject($object);

        if ($fields === []) {
            return;
        }

        $this->syncOriginalFieldValues($args, $object, $this->decryptObjectFields($object, $fields));
    }

    /**
     * @param LifecycleEventArgs<ObjectManager> $args
     */
    public function prePersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        $fields = $this->metadataFactory->forObject($object);

        if ($fields === []) {
            return;
        }

        $this->restoreRememberedFieldValues($object, $this->takeRememberedFieldValues($object));
        $this->rememberEncryptedFieldValues($object, $this->encryptObjectFields($object, $fields));
    }

    /**
     * @param LifecycleEventArgs<ObjectManager> $args
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        $fieldValues = $this->restoreRememberedFieldValues($object, $this->takeRememberedFieldValues($object));

        $this->syncOriginalFieldValues($args, $object, $fieldValues);
    }

    /**
     * @param LifecycleEventArgs<ObjectManager> $args
     */
    public function preUpdate(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        $changedFieldNames = $this->changedFieldNames($args);
        $fields = $changedFieldNames === null
            ? $this->metadataFactory->forObject($object)
            : $this->metadataFactory->forObjectFieldNames($object, $changedFieldNames);
        $encryptedFieldValues = [];
        $rememberedFieldValues = $this->takeRememberedFieldValues($object);

        if ($fields === [] && $rememberedFieldValues === []) {
            return;
        }

        $this->restoreRememberedFieldValues($object, $rememberedFieldValues);

        foreach ($fields as $field) {
            if (!$field->isInitialized($object)) {
                continue;
            }

            $value = $field->getValue($object);

            if ($value !== null && !is_string($value)) {
                throw new UnexpectedValueException(sprintf('Encrypted field "%s" must be a string or null.', $field->name));
            }

            if ($value === null) {
                $this->setNewValueIfChanged($args, $field->name, null);

                continue;
            }

            $encrypted = $this->encryptor->encrypt($value);
            $field->setValue($object, $encrypted);
            $encryptedFieldValues[$field->name] = $value;

            $this->setNewValueIfChanged($args, $field->name, $encrypted);
        }

        $this->rememberEncryptedFieldValues($object, $encryptedFieldValues);
    }

    /**
     * @param LifecycleEventArgs<ObjectManager> $args
     */
    public function postUpdate(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        $rememberedFieldValues = $this->takeRememberedFieldValues($object);
        $fieldValues = $this->restoreRememberedFieldValues($object, $rememberedFieldValues);

        $this->syncOriginalFieldValues($args, $object, $fieldValues);
    }

    /**
     * @param OnClearEventArgs<ObjectManager> $args
     */
    public function onClear(OnClearEventArgs $args): void
    {
        $this->encryptedFieldValuesByObject = new SplObjectStorage();
    }

    /**
     * @return list<string>
     */
    public function encryptObject(object $object): array
    {
        return array_keys($this->encryptObjectFields($object, $this->metadataFactory->forObject($object)));
    }

    public function decryptObject(object $object): void
    {
        $this->decryptObjectFields($object, $this->metadataFactory->forObject($object));
    }

    /**
     * @param list<EncryptedFieldMetadata> $fields
     *
     * @return array<string, string>
     */
    private function encryptObjectFields(object $object, array $fields): array
    {
        $encryptedFieldValues = [];

        foreach ($fields as $field) {
            if (!$field->isInitialized($object)) {
                continue;
            }

            $value = $field->getValue($object);

            if ($value !== null && !is_string($value)) {
                throw new UnexpectedValueException(sprintf('Encrypted field "%s" must be a string or null.', $field->name));
            }

            if ($value === null) {
                continue;
            }

            $encryptedFieldValues[$field->name] = $value;
            $field->setValue($object, $this->encryptor->encrypt($value));
        }

        return $encryptedFieldValues;
    }

    /**
     * @param list<EncryptedFieldMetadata> $fields
     * @return array<string, string|null>
     */
    private function decryptObjectFields(object $object, array $fields): array
    {
        $decryptedFieldValues = [];

        foreach ($fields as $field) {
            if (!$field->isInitialized($object)) {
                continue;
            }

            $value = $field->getValue($object);

            if ($value !== null && !is_string($value)) {
                throw new UnexpectedValueException(sprintf('Encrypted field "%s" must be a string or null.', $field->name));
            }

            if ($value === null) {
                continue;
            }

            if ($this->encryptor instanceof CiphertextDetectorInterface && !$this->encryptor->isCiphertext($value)) {
                continue;
            }

            $decryptedValue = $this->encryptor->decrypt($value);
            $field->setValue($object, $decryptedValue);
            $decryptedFieldValues[$field->name] = $decryptedValue;
        }

        return $decryptedFieldValues;
    }

    /**
     * @param array<string, string> $fieldValues
     */
    private function rememberEncryptedFieldValues(object $object, array $fieldValues): void
    {
        if ($fieldValues === []) {
            return;
        }

        $this->encryptedFieldValuesByObject[$object] = [
            ...($this->encryptedFieldValuesByObject[$object] ?? []),
            ...$fieldValues,
        ];
    }

    /**
     * @param LifecycleEventArgs<ObjectManager> $args
     *
     * @return list<string>|null
     */
    private function changedFieldNames(LifecycleEventArgs $args): ?array
    {
        if ($args instanceof OrmPreUpdateEventArgs || $args instanceof PersistencePreUpdateEventArgs) {
            return array_keys($args->getEntityChangeSet());
        }

        return null;
    }

    /**
     * @param LifecycleEventArgs<ObjectManager> $args
     */
    private function setNewValueIfChanged(LifecycleEventArgs $args, string $fieldName, mixed $value): void
    {
        if ($args instanceof OrmPreUpdateEventArgs || $args instanceof PersistencePreUpdateEventArgs) {
            if ($args->hasChangedField($fieldName)) {
                $args->setNewValue($fieldName, $value);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function takeRememberedFieldValues(object $object): array
    {
        if (!$this->encryptedFieldValuesByObject->contains($object)) {
            return [];
        }

        $rememberedFieldValues = $this->encryptedFieldValuesByObject[$object];
        $this->encryptedFieldValuesByObject->detach($object);

        return $rememberedFieldValues;
    }

    /**
     * @param array<string, string> $rememberedFieldValues
     *
     * @return array<string, string>
     */
    private function restoreRememberedFieldValues(object $object, array $rememberedFieldValues): array
    {
        if ($rememberedFieldValues === []) {
            return [];
        }

        $fields = $this->metadataFactory->forObjectFieldNames($object, array_keys($rememberedFieldValues));
        foreach ($fields as $field) {
            if (!array_key_exists($field->name, $rememberedFieldValues)) {
                continue;
            }

            $field->setValue($object, $rememberedFieldValues[$field->name]);
        }

        return $rememberedFieldValues;
    }

    /**
     * @param LifecycleEventArgs<ObjectManager> $args
     * @param array<string, string|null> $fieldValues
     */
    private function syncOriginalFieldValues(LifecycleEventArgs $args, object $object, array $fieldValues): void
    {
        if ($fieldValues === []) {
            return;
        }

        $objectManager = $args->getObjectManager();

        if (!method_exists($objectManager, 'getUnitOfWork')) {
            return;
        }

        $unitOfWork = $objectManager->getUnitOfWork();

        if (!method_exists($unitOfWork, 'setOriginalEntityProperty')) {
            return;
        }

        $objectId = spl_object_id($object);

        foreach ($fieldValues as $fieldName => $fieldValue) {
            $unitOfWork->setOriginalEntityProperty($objectId, $fieldName, $fieldValue);
        }
    }
}
