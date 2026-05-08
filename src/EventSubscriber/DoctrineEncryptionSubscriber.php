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
    public function __construct(
        private readonly EncryptedFieldMetadataFactory $metadataFactory,
        private readonly FieldEncryptorInterface $encryptor,
    ) {
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
        $this->encryptObject($args->getObject());
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        $this->decryptObject($object);
        $this->syncOriginalData($args, $object);
    }

    public function preUpdate(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        foreach ($this->metadataFactory->forObject($object) as $field) {
            $value = $field->getValue($object);

            if ($value !== null && !is_string($value)) {
                throw new \UnexpectedValueException(sprintf('Encrypted field "%s" must be a string or null.', $field->name));
            }

            $encrypted = $this->encryptor->encrypt($value);
            $field->setValue($object, $encrypted);

            if (method_exists($args, 'hasChangedField') && $args->hasChangedField($field->name)) {
                $args->setNewValue($field->name, $encrypted);
            }
        }
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        $this->decryptObject($object);
        $this->syncOriginalData($args, $object);
    }

    public function encryptObject(object $object): void
    {
        foreach ($this->metadataFactory->forObject($object) as $field) {
            $value = $field->getValue($object);

            if ($value !== null && !is_string($value)) {
                throw new \UnexpectedValueException(sprintf('Encrypted field "%s" must be a string or null.', $field->name));
            }

            $field->setValue($object, $this->encryptor->encrypt($value));
        }
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
