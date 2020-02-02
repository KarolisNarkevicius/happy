<?php


namespace Happy\Console;


use Happy\EnvFromString;
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
        //check if scp is available for downloading file
        $this->executeCommand('localhost', 'which scp || echo NOT_FOUND', function ($type, $output) {
            if (Str::contains($output, 'NOT_FOUND')) {
                throw new \Exception('Scp not installed on local machine.');
            }
        });

        //generate a .happy file and ask for env vars if they are not there
        if (!file_exists(getcwd() . '/.happy')) {
            $output->writeln('.happy file not found.');
            $output->writeln('Creating .happy file...');
            $output->writeln('PLEASE FILL IN YOUR SERVER DETAILS IN .happy FILE');
            file_put_contents(getcwd() . '/.happy', join("\n", ['REMOTE_SERVER_HOST= #forge@your-server.com', 'REMOTE_PROJECT_PATH= #/home/forge/your-project']));

            return 0;
        }

        $happyEnv = new EnvFromString(file_get_contents(getcwd() . '/.happy'));
        $remoteServerHost = $happyEnv->get('REMOTE_SERVER_HOST') ?? null;
        $remoteServerPath = $happyEnv->get('REMOTE_PROJECT_PATH') ?? null;

        if (!$remoteServerHost || !$remoteServerPath) {
            throw new \Exception('REMOTE_SERVER_HOST or REMOTE_PROJECT_PATH not set in .happy file.');
        }

        //get env from remote server
        /** @var EnvFromString $serverEnvironment */
        $this->executeCommand(
            $remoteServerHost,
            'cd ' . $remoteServerPath . ' && cat .env',
            function ($type, $output) use (&$serverEnvironment) {
                $serverEnvironment = new EnvFromString($output);
                if ($serverEnvironment->get('DB_CONNECTION') !== 'mysql') {
                    throw new \Exception('DB_CONNECTION is not mysql on remote server, check your env file.');
                }
                if (!$serverEnvironment->get('DB_USERNAME') || !$serverEnvironment->get('DB_PASSWORD') || !$serverEnvironment->get('DB_DATABASE')) {
                    throw new \Exception('DB_USERNAME, DB_PASSWORD or DB_DATABASE is not set on remote server, check your .env file.');
                }
            }
        );

        //dump the database
        $this->executeCommand(
            $remoteServerHost,
            'cd ' . $remoteServerPath . ' && mysqldump -u' . $serverEnvironment->get('DB_USERNAME') . ' -p' . $serverEnvironment->get('DB_PASSWORD') . ' ' . $serverEnvironment->get('DB_DATABASE') . ' > happy_dump.sql',
            );

        //download the dump
        $this->executeCommand('localhost', 'cd ' . getcwd() . ' && scp ' . $remoteServerHost . ':' . $remoteServerPath . '/happy_dump.sql ' . getcwd() . '/happy_dump.sql');

        //remove the dump from remote server
        $this->executeCommand($remoteServerHost, 'cd ' . $remoteServerPath . ' && rm -rf happy_dump.sql');

        //get local environment
        $localEnvironment = new EnvFromString(file_get_contents(getcwd() . '/.env'));

        if ($localEnvironment->get('DB_CONNECTION') !== 'mysql') {
            throw new \Exception('DB_CONNECTION is not mysql on local machine, check your env file.');
        }
        if (!$localEnvironment->get('DB_USERNAME') || !$localEnvironment->get('DB_PASSWORD') || !$localEnvironment->get('DB_DATABASE')) {
            throw new \Exception('DB_USERNAME, DB_PASSWORD or DB_DATABASE is not set on local machine, check your .env file.');
        }

        //import database to local server
        //TODO GENERATE COMMANDS BASED ON PASSWORD EXISTENCE
        $this->executeCommand('localhost', 'mysql -u' . $localEnvironment->get('DB_USERNAME') . ' -e "drop database if exists ' . $localEnvironment->get('DB_DATABASE') . '"');
        $this->executeCommand('localhost', 'mysql -u' . $localEnvironment->get('DB_USERNAME') . ' -e "create database ' . $localEnvironment->get('DB_DATABASE') . '"');
        $this->executeCommand('localhost', 'mysql -u' . $localEnvironment->get('DB_USERNAME') . ' ' . $localEnvironment->get('DB_DATABASE') . ' < ' . getcwd() . '/happy_dump.sql');

        //delete local dump
        $this->executeCommand('localhost', 'rm -rf ' . getcwd() . '/happy_dump.sql');

        return 0;
    }

    private function executeCommand(string $server, string $command, \Closure $callback = null)
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
        if ($callback instanceof \Closure) {
            $process->run(function ($type, $output) use ($callback) {
                dump($output);
                $callback($type, $output);
            });
        } else {
            $process->run(function ($type, $output) {
                dump($output);
            });
        }
    }
}