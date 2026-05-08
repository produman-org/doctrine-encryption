<?php

declare(strict_types=1);

namespace DoctrineEncryption\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use DoctrineEncryption\Contract\FieldEncryptorInterface;
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
        ];
    }

    public function postLoad(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        $this->decryptObject($object);
        $this->syncOriginalData($args, $object);
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        $this->rememberEncryptedFields($object, $this->encryptObject($object));
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        $this->decryptRememberedFields($object);
        $this->syncOriginalData($args, $object);
    }

    public function preUpdate(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        $encryptedFields = [];

        foreach ($this->metadataFactory->forObject($object) as $field) {
            if (method_exists($args, 'hasChangedField') && !$args->hasChangedField($field->name)) {
                continue;
            }

            $value = $field->getValue($object);

            if ($value !== null && !is_string($value)) {
                throw new \UnexpectedValueException(sprintf('Encrypted field "%s" must be a string or null.', $field->name));
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

        $this->decryptRememberedFields($object);
        $this->syncOriginalData($args, $object);
    }

    /**
     * @return list<string>
     */
    public function encryptObject(object $object): array
    {
        $encryptedFields = [];

        foreach ($this->metadataFactory->forObject($object) as $field) {
            $value = $field->getValue($object);

            if ($value !== null && !is_string($value)) {
                throw new \UnexpectedValueException(sprintf('Encrypted field "%s" must be a string or null.', $field->name));
            }

            $field->setValue($object, $this->encryptor->encrypt($value));
            $encryptedFields[] = $field->name;
        }

        return $encryptedFields;
    }

    public function decryptObject(object $object): void
    {
        foreach ($this->metadataFactory->forObject($object) as $field) {
            $value = $field->getValue($object);

            if ($value !== null && !is_string($value)) {
                throw new \UnexpectedValueException(sprintf('Encrypted field "%s" must be a string or null.', $field->name));
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

    private function decryptRememberedFields(object $object): void
    {
        if (!$this->hasRememberedFields($object)) {
            return;
        }

        $rememberedFields = $this->encryptedFieldsByObject[$object];

        foreach ($this->metadataFactory->forObject($object) as $field) {
            if (!isset($rememberedFields[$field->name])) {
                continue;
            }

            $value = $field->getValue($object);

            if ($value !== null && !is_string($value)) {
                throw new \UnexpectedValueException(sprintf('Encrypted field "%s" must be a string or null.', $field->name));
            }

            $field->setValue($object, $this->encryptor->decrypt($value));
        }

        $this->encryptedFieldsByObject->detach($object);
    }

    private function syncOriginalData(LifecycleEventArgs $args, object $object): void
    {
        $objectManager = $args->getObjectManager();

        if (!method_exists($objectManager, 'getUnitOfWork')) {
            return;
        }

        $unitOfWork = $objectManager->getUnitOfWork();

        if (!method_exists($unitOfWork, 'setOriginalEntityProperty')) {
            return;
        }

        $objectId = spl_object_id($object);

        foreach ($this->metadataFactory->forObject($object) as $field) {
            $unitOfWork->setOriginalEntityProperty($objectId, $field->name, $field->getValue($object));
        }
    }
}
