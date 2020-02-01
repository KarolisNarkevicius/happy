<?php

namespace Happy\Console;


use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class MakeDtoCommand extends Command
{

    private string $className;

    private array $fields = [];

    public function configure()
    {
        $this->setName('make:dto')
            ->setDescription('Creates a new DTO object in your App/DTO directory.')
            ->addArgument('name', InputArgument::REQUIRED, 'Name of your DTO class.')
            ->addOption('interactive', 'i', InputOption::VALUE_OPTIONAL, 'Get an interactive form for fields', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $name = $input->getArgument('name');
        $interactive = $input->getOption('interactive') !== false ? true : false;
        $this->className = Str::studly($name);

        $this->createDtoFolderIfMissing();
        $this->throwExceptionIfClassExists();

        if ($interactive) {
            $run = true;
            while ($run) {
                $question = new Question('Enter field name: ');
                $field = $this->getHelper('question')->ask($input, $output, $question);

                if (!$field) {
                    $run = false;
                    continue;
                }

                if (Str::contains($field, ':')) {
                    [$type, $field] = explode(':', $field);
                    if (!$this->typeAllowed($type)) {
                        $output->writeln('Type "' . $type . '" is not allowed.');
                        continue;
                    }
                } else {
                    $type = false;
                }
                $this->fields[$field] = $type;

            }
        }


        $this->generateClass();

        return 0;
    }

    private function createDtoFolderIfMissing(): void
    {
        if (!file_exists($this->getAppPath())) {
            throw new \Exception('App folder not found this directory.');
        }

        if (!file_exists($this->getDtoPath())) {
            $created = mkdir($this->getDtoPath(), 0755);
            if (!$created) {
                throw new \Exception('Couldn\'t create directory - "' . $this->getDtoPath() . '"');
            }
        }
    }

    private function getAppPath(): string
    {
        return getcwd() . DIRECTORY_SEPARATOR . 'app';
    }

    private function getDtoPath(): string
    {
        return $this->getAppPath() . DIRECTORY_SEPARATOR . 'DTO';
    }

    private function getClassFilePath(): string
    {
        return $this->getDtoPath() . DIRECTORY_SEPARATOR . $this->className . '.php';
    }

    private function throwExceptionIfClassExists(): void
    {
        if (file_exists($this->getClassFilePath())) {
            throw new \Exception('Class already exists - "' . $this->getClassFilePath() . '"');
        }
    }

    private function generateClass(): void
    {
        $loader = new FilesystemLoader(__DIR__ . '/../../templates');
        $twig = new Environment($loader, []);

        $template = $twig->load('dto.twig');

        $output = $template->render(['class_name' => $this->className, 'fields' => $this->fields]);

        file_put_contents($this->getClassFilePath(), $output);
    }

    private function typeAllowed(string $type): bool
    {
        //TODO CHECK TYPES? MIGHT NOT WANT THIS IF GOING TO SUPPORT CUSTOM OBJECTS AS TYPES
        return true;
    }

}