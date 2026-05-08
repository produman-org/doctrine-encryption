<?php

declare(strict_types=1);

namespace DoctrineEncryption\Tests\Integration;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use DoctrineEncryption\Encryption\HaliteFieldEncryptor;
use DoctrineEncryption\EventSubscriber\DoctrineEncryptionSubscriber;
use DoctrineEncryption\Metadata\EncryptedFieldMetadataFactory;
use DoctrineEncryption\Tests\Fixtures\OrmSecretNote;
use PHPUnit\Framework\TestCase;

final class DoctrineOrmEncryptionTest extends TestCase
{
    private string $keyDirectory;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('The pdo_sqlite extension is required for Doctrine ORM integration tests.');
        }

        $this->keyDirectory = sys_get_temp_dir() . '/doctrine-encryption-orm-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        if (isset($this->keyDirectory)) {
            $this->removeDirectory($this->keyDirectory);
        }
    }

    public function testItEncryptsDatabaseValuesAndKeepsEntitiesPlaintext(): void
    {
        $entityManager = $this->createEntityManager();

        $note = new OrmSecretNote('public title', 'top secret');
        $entityManager->persist($note);
        $entityManager->flush();

        $id = $note->getId();
        self::assertIsInt($id);
        self::assertSame('top secret', $note->getSecret());

        $storedSecret = $this->fetchStoredSecret($entityManager, $id);
        self::assertIsString($storedSecret);
        self::assertNotSame('top secret', $storedSecret);
        self::assertStringStartsWith('doctrine-encryption:halite:v1:', $storedSecret);

        $entityManager->clear();

        $reloadedNote = $entityManager->find(OrmSecretNote::class, $id);
        self::assertInstanceOf(OrmSecretNote::class, $reloadedNote);
        self::assertSame('top secret', $reloadedNote->getSecret());

        $reloadedNote->setTitle('changed title');
        $entityManager->flush();

        self::assertSame('top secret', $reloadedNote->getSecret());
        self::assertSame($storedSecret, $this->fetchStoredSecret($entityManager, $id));

        $reloadedNote->setSecret('changed secret');
        $entityManager->flush();

        self::assertSame('changed secret', $reloadedNote->getSecret());

        $changedStoredSecret = $this->fetchStoredSecret($entityManager, $id);
        self::assertIsString($changedStoredSecret);
        self::assertNotSame($storedSecret, $changedStoredSecret);
        self::assertNotSame('changed secret', $changedStoredSecret);
        self::assertStringStartsWith('doctrine-encryption:halite:v1:', $changedStoredSecret);
    }

    public function testItKeepsNullEncryptedFieldsNull(): void
    {
        $entityManager = $this->createEntityManager();

        $note = new OrmSecretNote('public title', null);
        $entityManager->persist($note);
        $entityManager->flush();

        $id = $note->getId();
        self::assertIsInt($id);
        self::assertNull($note->getSecret());
        self::assertNull($this->fetchStoredSecret($entityManager, $id));

        $entityManager->clear();

        $reloadedNote = $entityManager->find(OrmSecretNote::class, $id);
        self::assertInstanceOf(OrmSecretNote::class, $reloadedNote);
        self::assertNull($reloadedNote->getSecret());

        $reloadedNote->setSecret('now secret');
        $entityManager->flush();

        self::assertSame('now secret', $reloadedNote->getSecret());

        $storedSecret = $this->fetchStoredSecret($entityManager, $id);
        self::assertIsString($storedSecret);
        self::assertStringStartsWith('doctrine-encryption:halite:v1:', $storedSecret);

        $reloadedNote->setSecret(null);
        $entityManager->flush();

        self::assertNull($reloadedNote->getSecret());
        self::assertNull($this->fetchStoredSecret($entityManager, $id));
    }

    public function testItLoadsPlaintextLegacyDatabaseValuesAndEncryptsThemWhenChanged(): void
    {
        $entityManager = $this->createEntityManager();
        $connection = $entityManager->getConnection();

        $connection->insert('orm_secret_notes', [
            'title' => 'legacy title',
            'secret' => 'legacy plaintext',
        ]);
        $id = (int) $connection->lastInsertId();

        $note = $entityManager->find(OrmSecretNote::class, $id);

        self::assertInstanceOf(OrmSecretNote::class, $note);
        self::assertSame('legacy plaintext', $note->getSecret());

        $note->setTitle('changed title');
        $entityManager->flush();

        self::assertSame('legacy plaintext', $note->getSecret());
        self::assertSame('legacy plaintext', $this->fetchStoredSecret($entityManager, $id));

        $note->setSecret('migrated secret');
        $entityManager->flush();

        self::assertSame('migrated secret', $note->getSecret());

        $storedSecret = $this->fetchStoredSecret($entityManager, $id);
        self::assertIsString($storedSecret);
        self::assertNotSame('migrated secret', $storedSecret);
        self::assertStringStartsWith('doctrine-encryption:halite:v1:', $storedSecret);
    }

    private function createEntityManager(): EntityManagerInterface
    {
        $config = ORMSetup::createAttributeMetadataConfig([__DIR__ . '/../Fixtures'], true);
        $config->enableNativeLazyObjects(true);
        $eventManager = new EventManager();
        $eventManager->addEventSubscriber(new DoctrineEncryptionSubscriber(
            new EncryptedFieldMetadataFactory(),
            new HaliteFieldEncryptor($this->keyDirectory . '/config/secrets/test/.Halite.key'),
        ));
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ], $config, $eventManager);
        $entityManager = new EntityManager($connection, $config, $eventManager);
        $schemaTool = new SchemaTool($entityManager);

        $schemaTool->createSchema([
            $entityManager->getClassMetadata(OrmSecretNote::class),
        ]);

        return $entityManager;
    }

    private function fetchStoredSecret(EntityManagerInterface $entityManager, int $id): mixed
    {
        return $entityManager->getConnection()->fetchOne(
            'SELECT secret FROM orm_secret_notes WHERE id = ?',
            [$id],
        );
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        ) as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
                continue;
            }

            unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
