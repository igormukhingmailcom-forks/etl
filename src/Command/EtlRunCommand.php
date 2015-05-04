<?php

namespace BiSight\Etl\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use LinkORB\Component\DatabaseManager\DatabaseManager;
use BiSight\Etl\Job;
use BiSight\Etl\Extractor\PdoExtractor;
use BiSight\Etl\Transformer\NullTransformer;
use BiSight\Etl\Loader\PdoLoader;
use BiSight\Etl\JobLoader\XmlJobLoader;
use BiSight\Etl\Runner\ConsoleRunner;
use RuntimeException;

class EtlRunCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('etl:run')
            ->setDescription('Run an ETL job')
            ->addArgument(
                'jobfile',
                InputArgument::REQUIRED,
                'Filename of the job'
            )
            ->addArgument(
                'variables',
                InputArgument::IS_ARRAY,
                'Specify your variables!'
            )
        ;
    }
    
    

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $jobfile = $input->getArgument('jobfile');
        $variables = $input->getArgument('variables');
        $v = array();
        foreach ($variables as $variable) {
            $part = explode("=", $variable);
            if (count($part)!=2) {
                throw new RuntimeException("Invalid variable format: `" . $variable . '`. Please use the key=value format.');
            }
            $v[$part[0]] = $part[1];
        }
        $output->writeLn("Running jobfile: " . $jobfile);
        
        $jobloader = new XmlJobLoader();
        $jobs = $jobloader->loadFile($jobfile, $v);
        
        $runner = new ConsoleRunner($output);
        foreach ($jobs as $job) {
            $runner->run($job);
        }
    }
}
