<?php


namespace Happy\Console;


use Happy\EnvFromString;
use Happy\Theme;
use Illuminate\Hashing\BcryptHasher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PasswordCommand extends Command
{
    public function configure()
    {
        $this->setName('db:password')
            ->setDescription('Generates a laravel password for your database.')
            ->addArgument('password', InputArgument::OPTIONAL, 'String to generate password from. (defaults to "password" if not set)')
            ->addOption('output-only', 'o', InputOption::VALUE_OPTIONAL, 'Dont update the database and just output generated password to console.', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output = new Theme($output);

        $password = $input->getArgument('password') ?? 'password';
        $hasher = new BcryptHasher(['rounds' => 10]);

        $password = $hasher->make($password);

        $outputOnly = $input->getOption('output-only') !== false ? true : false;

        if (!$outputOnly) {
            if (!file_exists(getcwd() . '/.env')) {
                throw new \Exception('.env file not found.');
            }

            $env = new EnvFromString(file_get_contents(getcwd() . '/.env'));

            if ($env->get('DB_CONNECTION') !== 'mysql') {
                throw new \Exception('DB_CONNECTION is not mysql on local machine, check your .env file.');
            }

            if (!$env->get('DB_HOST') || !$env->get('DB_USERNAME') || !$env->get('DB_DATABASE')) {
                throw new \Exception('DB_HOST, DB_USERNAME or DB_DATABASE is not set on local machine, check your .env file.');
            }

            $conn = mysqli_connect($env->get('DB_HOST'), $env->get('DB_USERNAME'), $env->get('DB_PASSWORD'), $env->get('DB_DATABASE'), $env->get('DB_PORT'));
            if (!$conn) {
                throw new \Exception('Couldn\'t connect to database.');
            }
            $updated = $conn->query('UPDATE `users` SET `password`="' . $password . '"');
            if (!$updated) {
                throw new \Exception($conn->error);
            }

            $output->writeln('Updated passwords in "' . $env->get('DB_DATABASE') . '" database, "users" table.');
        } else {
            $output->writeln($password);
        }

        return 0;
    }
}