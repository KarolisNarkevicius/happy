<?php

namespace Happy\Console;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ForgeDeployCommand extends Command
{
    public function configure()
    {
        $this->setName('forge:deploy')->setDescription('Triggers forge deployment url set in .happy file.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        //todo check if FORGE_DEPLOYMENT_TRIGGER exists
        //create if does not
        //trigger the url
        //check if "OK" 200 was the response
    }
}