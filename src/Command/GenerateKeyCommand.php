<?php

declare(strict_types=1);

namespace ProdumanOrg\DoctrineEncryption\Command;

use ProdumanOrg\DoctrineEncryption\Exception\DoctrineEncryptionException;
use ProdumanOrg\DoctrineEncryption\Key\KeyFileManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'doctrine-encryption:generate-key',
    description: 'Generate the Halite encryption key file.',
)]
final class GenerateKeyCommand extends Command
{
    public function __construct(private readonly KeyFileManager $keyFileManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite the existing key file.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = true === $input->getOption('force');

        try {
            $this->keyFileManager->generate($force);
        } catch (DoctrineEncryptionException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('Halite encryption key generated at "%s".', $this->keyFileManager->keyFile()));
        $io->warning('Back up this key securely. If the key is lost, already encrypted data cannot be decrypted.');

        return Command::SUCCESS;
    }
}
