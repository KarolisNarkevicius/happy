<?php


namespace Happy\Console;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class DumpDatabaseCommand extends Command
{
    public function configure()
    {
        $this->setName('db:dump')
            ->setDescription('Dumps a database from remote server to your project.')
            ->addArgument('directory', InputArgument::OPTIONAL, 'Project directory on server.');
//            ->addOption('update-database', 'u', InputOption::VALUE_OPTIONAL, 'Replace password for all users in the database of current project.', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $delimiter = 'EOF-HAPPY';
        $env = [];
        $script = 'cd /home/forge/www.smarttorc.com && ls -al && mysqldump';

        $process = Process::fromShellCommandline(
            "ssh forge@smarttorc.com 'bash -se' << \\$delimiter" . PHP_EOL
            . implode(PHP_EOL, $env) . PHP_EOL
            . 'set -e' . PHP_EOL
            . $script . PHP_EOL
            . $delimiter
        );
        $process->run(function($type, $output) {
            dd($output);
        });
        //connect to server
        //dump database
        //download database to local
        //upload dump to local database
        //delete dump
        return 0;
    }
}