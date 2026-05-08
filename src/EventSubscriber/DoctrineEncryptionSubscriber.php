<?php

declare(strict_types=1);

namespace DoctrineEncryption\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\Event\OnClearEventArgs;
use DoctrineEncryption\Contract\FieldEncryptorInterface;
use DoctrineEncryption\Metadata\EncryptedFieldMetadata;
use DoctrineEncryption\Metadata\EncryptedFieldMetadataFactory;

final class DoctrineEncryptionSubscriber implements EventSubscriber
{
    /**
     * @var \SplObjectStorage<object, array<string, true>>
     */
    private \SplObjectStorage $encryptedFieldsByObject;

    public function __construct(
        private readonly EncryptedFieldMetadataFactory $metadataFactory,
        private readonly FieldEncryptorInterface $encryptor,
    ) {
        $this->encryptedFieldsByObject = new \SplObjectStorage();
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

        $this->decryptObjectFields($object, $fields);
        $this->syncOriginalData($args, $object, $fields);
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        $fields = $this->metadataFactory->forObject($object);

        if ($fields === []) {
            return;
        }

        $this->decryptRememberedFields($object, $fields);
        $this->rememberEncryptedFields($object, $this->encryptObjectFields($object, $fields));
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        $fields = $this->metadataFactory->forObject($object);

        if ($fields === []) {
            return;
        }

        $this->decryptRememberedFields($object, $fields);
        $this->syncOriginalData($args, $object, $fields);
    }

    public function preUpdate(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        $fields = $this->metadataFactory->forObject($object);
        $encryptedFields = [];

        if ($fields === []) {
            return;
        }

        $this->decryptRememberedFields($object, $fields);

        foreach ($fields as $field) {
            if (method_exists($args, 'hasChangedField') && !$args->hasChangedField($field->name)) {
                continue;
            }

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
            $encryptedFields[] = $field->name;

            if (method_exists($args, 'hasChangedField') && $args->hasChangedField($field->name)) {
                $args->setNewValue($field->name, $encrypted);
            }
        }

        $this->rememberEncryptedFields($object, $encryptedFields);
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

        $this->decryptRememberedFields($object, $fields);
        $this->syncOriginalData($args, $object, $fields);
    }

    public function onClear(OnClearEventArgs $args): void
    {
        $this->encryptedFieldsByObject = new \SplObjectStorage();
    }

    /**
     * @return list<string>
     */
    public function encryptObject(object $object): array
    {
        return $this->encryptObjectFields($object, $this->metadataFactory->forObject($object));
    }

    public function decryptObject(object $object): void
    {
        $this->decryptObjectFields($object, $this->metadataFactory->forObject($object));
    }

    /**
     * @param list<EncryptedFieldMetadata> $fields
     *
     * @return list<string>
     */
    private function encryptObjectFields(object $object, array $fields): array
    {
        $encryptedFields = [];

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

            $field->setValue($object, $this->encryptor->encrypt($value));
            $encryptedFields[] = $field->name;
        }

        return $encryptedFields;
    }

    /**
     * @param list<EncryptedFieldMetadata> $fields
     */
    private function decryptObjectFields(object $object, array $fields): void
    {
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

            $field->setValue($object, $this->encryptor->decrypt($value));
        }
    }

    /**
     * @param list<string> $fieldNames
     */
    private function rememberEncryptedFields(object $object, array $fieldNames): void
    {
        if ($fieldNames === []) {
            return;
        }

        $rememberedFields = $this->encryptedFieldsByObject[$object] ?? [];

        foreach ($fieldNames as $fieldName) {
            $rememberedFields[$fieldName] = true;
        }

        $this->encryptedFieldsByObject[$object] = $rememberedFields;
    }

    private function hasRememberedFields(object $object): bool
    {
        return $this->encryptedFieldsByObject->contains($object)
            && $this->encryptedFieldsByObject[$object] !== [];
    }

    /**
     * @param list<EncryptedFieldMetadata> $fields
     */
    private function decryptRememberedFields(object $object, array $fields): void
    {
        if (!$this->hasRememberedFields($object)) {
            return;
        }

        $rememberedFields = $this->encryptedFieldsByObject[$object];

        foreach ($fields as $field) {
            if (!isset($rememberedFields[$field->name])) {
                continue;
            }

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

            $field->setValue($object, $this->encryptor->decrypt($value));
        }

        $this->encryptedFieldsByObject->detach($object);
    }

    /**
     * @param list<EncryptedFieldMetadata> $fields
     */
    private function syncOriginalData(LifecycleEventArgs $args, object $object, array $fields): void
    {
        if ($fields === []) {
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

        foreach ($fields as $field) {
            $unitOfWork->setOriginalEntityProperty($objectId, $field->name, $field->getValue($object));
        }
    }
}
