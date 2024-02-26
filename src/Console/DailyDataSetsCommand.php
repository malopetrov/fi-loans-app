<?php

namespace App\Console;

use Exception;
use DateTime;
use DateTimeZone;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UnexpectedValueException;
use App\Domain\Loan\Data\DebtInformationItem;
use App\Domain\Loan\Data\CustomerItem;
use App\Domain\Loan\Data\LoanItem;
use App\Domain\Loan\Data\SsnCollectionItem;

final class DailyDataSetsCommand extends Command
{
    private array $settings = [];
    private string $timestamp;
    
    protected function configure(): void
    {
        parent::configure();

        $this->setName('dailydatasets');
        $this->setDescription('Creating the daily datasets for DIC to get');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        require __DIR__ . '/../../config/settings.php';
        
        $settings['page_size_limit'] = 100000000; // 104857600 = 100MB
        
        $this->settings = $settings;

        //$this->rotateDataSetsFiles();

        // get all loans data
        // create DebtInformationCollection
        // save to files (split data into files like pages as per page_size_limit settings)

        // get all ssns data
        // create SsnCollectionCollection
        // save to files (split data into files like pages as per page_size_limit settings)
        
        $output->writeln(sprintf('<info>All done!</info>'));

        // The error code, 0 on success
        return 0;
    }
}