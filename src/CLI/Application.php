<?php

namespace ORM\CLI;

use Exception;
use Symfony\Component\Console\Application as SymfonyApp;
use ORM\CLI\Command\GenerateEntityCommand;

class Application
{
    /**
     * @throws Exception
     */
    public function run(): void
    {
        $cli = new SymfonyApp('ORM CLI', '1.0.0');
        $cli->add(new GenerateEntityCommand());
//        $cli->add(new GenerateMigrationCommand());
//        $cli->add(new MigrateCommand());
        $cli->run();
    }
}
