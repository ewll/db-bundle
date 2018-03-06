<?php namespace Ewll\DBBundle\Command;

use Ewll\DBBundle\Migration\MigrationManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrateCommand extends Command
{
    /**
     * @var MigrationManager
     */
    private $migrationManager;

    public function __construct(MigrationManager $migrationManager)
    {
        parent::__construct();

        $this->migrationManager = $migrationManager;
    }

    protected function configure()
    {
        $this
            ->setName('ewll:db-bundle:migrate')
            ->addOption('all', null, InputOption::VALUE_NONE)
            ->addOption('up', null, InputOption::VALUE_OPTIONAL)
            ->addOption('down', null, InputOption::VALUE_OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $all = $input->getOption('all');
        $upName = $input->getOption('up');
        $downName = $input->getOption('down');

        $style = new SymfonyStyle($input, $output);

        $migrations = $this->migrationManager->getMigrationsInfo();

        if (true === $all) {
            foreach ($migrations as $migration) {
                if (!$migration['migrated']) {
                    $this->migrate($style, $migrations, $migration['name']);
                }
            }
        } elseif (null !== $upName) {
            $this->migrate($style, $migrations, $upName);
        } elseif (null !== $downName) {
            $this->migrationManager->down($migrations, $downName);
            $style->success("Downgraded $downName");
        } else {
            $this->writeInfo($style, $migrations);
        }
    }

    private function writeInfo(SymfonyStyle $style, array $migrations)
    {
        $rows = [];
        foreach ($migrations as $migration) {
            $rows[] = [$migration['name'], $migration['migrated'], $migration['description']];
        }
        $style->table(['Name', 'Migrated', 'Description'], $rows);
    }

    private function migrate(SymfonyStyle $style, array $migrations, string $name)
    {
        $this->migrationManager->up($migrations, $name);
        $style->success("Upgraded $name");
    }
}
