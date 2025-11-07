<?php

namespace Shedeza\SybaseAseOrmBundle\Command;

use Shedeza\SybaseAseOrmBundle\ORM\EntityManager;
use Shedeza\SybaseAseOrmBundle\ORM\Tools\SchemaValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'sybase:schema:validate',
    description: 'Validate the mapping files'
)]
class ValidateSchemaCommand extends Command
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $validator = new SchemaValidator($this->entityManager);
        
        $io->title('Validating Sybase ASE ORM Schema');
        
        $entityClasses = $this->getEntityClasses();
        
        $totalErrors = 0;
        foreach ($entityClasses as $entityClass) {
            $errors = $validator->validateEntity($entityClass);
            if (!empty($errors)) {
                $io->error("Errors in {$entityClass}:");
                foreach ($errors as $error) {
                    $io->writeln("  - {$error}");
                }
                $totalErrors += count($errors);
            }
        }
        
        if ($totalErrors === 0) {
            $io->success('Schema validation passed!');
            return Command::SUCCESS;
        } else {
            $io->error("Schema validation failed with {$totalErrors} errors");
            return Command::FAILURE;
        }
    }
    
    private function getEntityClasses(): array
    {
        return [];
    }
}