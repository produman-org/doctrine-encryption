<?php

declare(strict_types=1);

namespace ProdumanOrg\DoctrineEncryption\Command;

use ProdumanOrg\DoctrineEncryption\Key\KeyFileManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'doctrine-encryption:validate-key',
    description: 'Validate the configured Halite encryption key file.',
)]
final class ValidateKeyCommand extends Command
{
    public function __construct(private readonly KeyFileManager $keyFileManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $errors = $this->keyFileManager->validationErrors();

        if ([] !== $errors) {
            $io->error($errors);

            return Command::FAILURE;
        }

        $io->success(sprintf('Halite encryption key "%s" is valid.', $this->keyFileManager->keyFile()));

        return Command::SUCCESS;
    }
}
