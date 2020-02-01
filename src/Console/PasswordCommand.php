<?php


namespace Happy\Console;


use Dotenv\Dotenv;
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
            ->addArgument('password', InputArgument::OPTIONAL, 'String to generate password from.')
            ->addOption('update-database', 'u', InputOption::VALUE_OPTIONAL, 'Replace password for all users in the database of current project.', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $password = $input->getArgument('password') ?? 'password';
        $hasher = new BcryptHasher(['rounds' => 10]);

        $password = $hasher->make($password);

        $update = $input->getOption('update-database') !== false ? true : false;

        if ($update) {
            //todo throw exception if env is not there of keys are missing
            $credentials = $this->getCredentials();

            //todo throw exception if cant connect or update
            $conn = mysqli_connect($credentials->host, $credentials->username, $credentials->password, $credentials->database, $credentials->port);
            $conn->query('UPDATE `users` SET `password`="' . $password . '"');
            
            $output->writeln('Updated passwords in "' . $credentials->database . '" database, "users" table.');
        } else {
            $output->writeln($password);
        }

        return 0;
    }

    private function getCredentials(): object
    {
        //load .env file from current (project) directory
        $dotenv = Dotenv::createMutable(getcwd());
        $dotenv->load();

        return (object)[
            'connection' => getenv('DB_CONNECTION'),
            'host'       => getenv('DB_HOST'),
            'port'       => getenv('DB_PORT'),
            'database'   => getenv('DB_DATABASE'),
            'username'   => getenv('DB_USERNAME'),
            'password'   => getenv('DB_PASSWORD'),
        ];
    }
}