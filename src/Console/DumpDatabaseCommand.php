<?php


namespace Happy\Console;


use Happy\EnvFromString;
use Happy\Theme;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class DumpDatabaseCommand extends Command
{

    public function configure()
    {
        $this->setName('db:dump')->setDescription('Dumps a database from remote server to your project.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output = new Theme($output);

        $output->writeln('Checking scp...');
        //check if scp is available for downloading file
        $this->executeCommand('localhost', 'which scp || echo NOT_FOUND', function ($type, $output) {
            if (Str::contains($output, 'NOT_FOUND')) {
                throw new \Exception('Scp not installed on local machine.');
            }
        });

        $output->writeln('Checking .happy file...');
        //generate .happy file if its not there
        if (!file_exists(getcwd() . '/.happy')) {
            $output->writeln('Creating .happy file...');

            file_put_contents(getcwd() . '/.happy', join("\n", ['REMOTE_SERVER_HOST= #forge@your-server.com', 'REMOTE_PROJECT_PATH= #/home/forge/your-project']));

            if (file_exists(getcwd() . '/.gitignore')) {
                file_put_contents(getcwd() . '/.gitignore', "\n.happy", FILE_APPEND);
                $output->writeln('Added .happy to .gitignore...');
            }
            $output->writeln('PLEASE FILL IN YOUR SERVER DETAILS IN .happy FILE AND RERUN THE COMMAND.');

            return 0;
        }

        $output->writeln('Checking .happy config...');
        //get .happy variables
        $happyEnv = new EnvFromString(file_get_contents(getcwd() . '/.happy'));
        $remoteServerHost = $happyEnv->get('REMOTE_SERVER_HOST') ?? null;
        $remoteServerPath = $happyEnv->get('REMOTE_PROJECT_PATH') ?? null;

        if (!$remoteServerHost || !$remoteServerPath) {
            throw new \Exception('REMOTE_SERVER_HOST or REMOTE_PROJECT_PATH not set in .happy file.');
        }

        $output->writeln('Checking local .env config...');
        //get local environment
        $localEnvironment = new EnvFromString(file_get_contents(getcwd() . '/.env'));

        if ($localEnvironment->get('DB_CONNECTION') !== 'mysql') {
            throw new \Exception('DB_CONNECTION is not mysql on local machine, check your .env file.');
        }
        if (!$localEnvironment->get('DB_USERNAME') || !$localEnvironment->get('DB_DATABASE')) {
            throw new \Exception('DB_USERNAME or DB_DATABASE is not set on local machine, check your .env file.');
        }

        $output->writeln('Checking remote .env config...');
        //get .env from remote server
        /** @var EnvFromString $serverEnvironment */
        $this->executeCommand(
            $remoteServerHost,
            'cd ' . $remoteServerPath . ' && cat .env',
            function ($type, $output) use (&$serverEnvironment) {
                $serverEnvironment = new EnvFromString($output);

                if ($serverEnvironment->get('DB_CONNECTION') !== 'mysql') {
                    throw new \Exception('DB_CONNECTION is not mysql on remote server, check your .env file.');
                }
                if (!$serverEnvironment->get('DB_USERNAME') || !$serverEnvironment->get('DB_PASSWORD') || !$serverEnvironment->get('DB_DATABASE')) {
                    throw new \Exception('DB_USERNAME, DB_PASSWORD or DB_DATABASE is not set on remote server, check your .env file.');
                }
            }
        );

        $output->writeln('Dumping database on remote server...');
        //dump the database
        $this->executeCommand(
            $remoteServerHost,
            'cd ' . $remoteServerPath . ' && mysqldump -u' . $serverEnvironment->get('DB_USERNAME') . ' -p' . $serverEnvironment->get('DB_PASSWORD') . ' ' . $serverEnvironment->get('DB_DATABASE') . ' > happy_dump.sql',
            );

        $output->writeln('Downloading dump from remote server...');
        //download the dump
        $this->executeCommand('localhost', 'cd ' . getcwd() . ' && scp ' . $remoteServerHost . ':' . $remoteServerPath . '/happy_dump.sql ' . getcwd() . '/happy_dump.sql');

        $output->writeln('Removing dump in remote server...');
        //remove the dump from remote server
        $this->executeCommand($remoteServerHost, 'cd ' . $remoteServerPath . ' && rm -rf happy_dump.sql');

        $output->writeln('Importing dump to local database...');
        //import database to local server
        $minusP = $localEnvironment->get('DB_PASSWORD') ? '-p' . $localEnvironment->get('DB_PASSWORD') : ''; //need this, cause if password is empty, terminal will ask to enter it

        $this->executeCommand('localhost', 'mysql -u' . $localEnvironment->get('DB_USERNAME') . ' ' . $minusP . ' -e "drop database if exists ' . $localEnvironment->get('DB_DATABASE') . '"');
        $this->executeCommand('localhost', 'mysql -u' . $localEnvironment->get('DB_USERNAME') . ' ' . $minusP . ' -e "create database ' . $localEnvironment->get('DB_DATABASE') . '"');
        $this->executeCommand('localhost', 'mysql -u' . $localEnvironment->get('DB_USERNAME') . ' ' . $minusP . ' ' . $localEnvironment->get('DB_DATABASE') . ' < ' . getcwd() . '/happy_dump.sql');

        $output->writeln('Deleting local dump file...');
        //delete local dump
        $this->executeCommand('localhost', 'rm -rf ' . getcwd() . '/happy_dump.sql');

        $output->writeln('Done.');

        return 0;
    }

    private function executeCommand(string $server, string $command, \Closure $callback = null): void
    {
        $delimiter = 'EOF-HAPPY';
        $env = [];

        if ($server === 'localhost') {
            $process = Process::fromShellCommandline($command, null, $env);
        } else {
            $process = Process::fromShellCommandline(
                "ssh " . $server . " 'bash -se' << \\$delimiter" . PHP_EOL
                . implode(PHP_EOL, $env) . PHP_EOL
                . 'set -e' . PHP_EOL
                . $command . PHP_EOL
                . $delimiter
            );
        }

        //TODO HANDLE ERRORS ON EXECUTION
        //TODO HANDLE BAD URL EXCEPTION
        //ADD LOG FILE LOGGING FOR EVERY PROCESS
        if ($callback instanceof \Closure) {
            $process->run(function ($type, $output) use ($callback) {
//                dump($output);
                $callback($type, $output);
            });
        } else {
            $process->run(function ($type, $output) {
//                dump($output);
            });
        }
    }

    private function b(string $string)
    {
        $max = strlen('Downloading dump from remote server...');

        $current = strlen($string);
        if ($max > $current) {
            $string = $string . str_repeat(' ', $max - $current);
        }

        return "<black>" . $string . "</black>";
    }
}