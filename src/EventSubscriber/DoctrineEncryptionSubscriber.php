<?php

declare(strict_types=1);

namespace DoctrineEncryption\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\Event\OnClearEventArgs;
use DoctrineEncryption\Contract\CiphertextDetectorInterface;
use DoctrineEncryption\Contract\FieldEncryptorInterface;
use DoctrineEncryption\Metadata\EncryptedFieldMetadata;
use DoctrineEncryption\Metadata\EncryptedFieldMetadataFactory;

final class DoctrineEncryptionSubscriber implements EventSubscriber
{
    /**
     * @var \SplObjectStorage<object, array<string, string>>
     */
    private \SplObjectStorage $encryptedFieldValuesByObject;

    public function __construct(
        private readonly EncryptedFieldMetadataFactory $metadataFactory,
        private readonly FieldEncryptorInterface $encryptor,
    ) {
        $this->encryptedFieldValuesByObject = new \SplObjectStorage();
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

    public function postLoad(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        $fields = $this->metadataFactory->forObject($object);

        if ($fields === []) {
            return;
        }

        $this->syncOriginalFieldValues($args, $object, $this->decryptObjectFields($object, $fields));
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        $fields = $this->metadataFactory->forObject($object);

        if ($fields === []) {
            return;
        }

        $this->restoreRememberedFieldValues($object, $fields);
        $this->rememberEncryptedFieldValues($object, $this->encryptObjectFields($object, $fields));
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        $fields = $this->metadataFactory->forObject($object);

        if ($fields === []) {
            return;
        }

        $this->syncOriginalFieldValues($args, $object, $this->restoreRememberedFieldValues($object, $fields));
    }

    public function preUpdate(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        $changedFieldNames = $this->changedFieldNames($args);
        $fields = $changedFieldNames === null
            ? $this->metadataFactory->forObject($object)
            : $this->metadataFactory->forObjectFieldNames($object, $changedFieldNames);
        $encryptedFieldValues = [];

        if ($fields === [] && !$this->hasRememberedFields($object)) {
            return;
        }

        if ($this->hasRememberedFields($object)) {
            $this->restoreRememberedFieldValues($object, $this->metadataFactory->forObject($object));
        }

        foreach ($fields as $field) {
            if (!$field->isInitialized($object)) {
                continue;
            }

            $value = $field->getValue($object);

            if ($value !== null && !is_string($value)) {
                throw new \UnexpectedValueException(sprintf('Encrypted field "%s" must be a string or null.', $field->name));
            }

            if ($value === null) {
                if (method_exists($args, 'hasChangedField') && $args->hasChangedField($field->name)) {
                    $args->setNewValue($field->name, null);
                }

                continue;
            }

            $encrypted = $this->encryptor->encrypt($value);
            $field->setValue($object, $encrypted);
            $encryptedFieldValues[$field->name] = $value;

            if (method_exists($args, 'hasChangedField') && $args->hasChangedField($field->name)) {
                $args->setNewValue($field->name, $encrypted);
            }
        }

        $this->rememberEncryptedFieldValues($object, $encryptedFieldValues);
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        if (!$this->hasRememberedFields($object)) {
            return;
        }

        $fields = $this->metadataFactory->forObject($object);

        if ($fields === []) {
            return;
        }

        $fieldValues = $this->restoreRememberedFieldValues($object, $fields);
        $this->syncOriginalFieldValues($args, $object, $fieldValues);
    }

    public function onClear(OnClearEventArgs $args): void
    {
        $this->encryptedFieldValuesByObject = new \SplObjectStorage();
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
                throw new \UnexpectedValueException(sprintf('Encrypted field "%s" must be a string or null.', $field->name));
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
                throw new \UnexpectedValueException(sprintf('Encrypted field "%s" must be a string or null.', $field->name));
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

    private function hasRememberedFields(object $object): bool
    {
        return $this->encryptedFieldValuesByObject->contains($object)
            && $this->encryptedFieldValuesByObject[$object] !== [];
    }

    /**
     * @return list<string>|null
     */
    private function changedFieldNames(LifecycleEventArgs $args): ?array
    {
        if (!method_exists($args, 'getEntityChangeSet')) {
            return null;
        }

        return array_keys($args->getEntityChangeSet());
    }

    /**
     * @param list<EncryptedFieldMetadata> $fields
     */
    private function restoreRememberedFieldValues(object $object, array $fields): array
    {
        if (!$this->hasRememberedFields($object)) {
            return [];
        }

        $rememberedFieldValues = $this->encryptedFieldValuesByObject[$object];

        foreach ($fields as $field) {
            if (!array_key_exists($field->name, $rememberedFieldValues)) {
                continue;
            }

            $field->setValue($object, $rememberedFieldValues[$field->name]);
        }

        $this->encryptedFieldValuesByObject->detach($object);

        return $rememberedFieldValues;
    }

    /**
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
