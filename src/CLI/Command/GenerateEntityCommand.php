<?php

namespace ORM\CLI\Command;

use Dotenv\Dotenv;
use ORM\Drivers\PDODriver;
use ORM\Generator\EntityGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateEntityCommand extends Command
{
    protected static string $defaultName = "generate:entity";

    protected function configure(): void
    {
        $this
            ->setName(self::$defaultName)
            ->setDescription("Generates entity class from existing table")
            ->setHelp("This command allows you to generate a PHP entity based on a database table")
            ->addArgument("table", InputArgument::REQUIRED, "Name of the database table")
            ->addArgument("class", InputArgument::REQUIRED, "Fully qualified class name (e.g. Entity\\User)");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $table = $input->getArgument("table");
        $class = $input->getArgument("class");

        $dotenv = Dotenv::createImmutable(getcwd());
        $dotenv->load();

        $driver = PDODriver::default();
        $code = new EntityGenerator($driver)->generate($table, $class);
        $filename = "src/" . str_replace("\\", "/", $class) . ".php";
        $dir = dirname($filename);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($filename, $code);
        $output->writeln("<info>✅ Entity generated:</info> $filename");
        return Command::SUCCESS;
    }
}
