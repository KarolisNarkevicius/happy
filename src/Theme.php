<?php


namespace Happy;


use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\OutputInterface;

class Theme
{
    public OutputInterface $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;

        $this->output->getFormatter()->setStyle('line', new OutputFormatterStyle('green', 'black', ['bold']));
    }

    public function writeln(string $message)
    {
        $this->output->writeln('<line>' . $message . '</line>');
    }
}