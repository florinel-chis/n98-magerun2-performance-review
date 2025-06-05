<?php

declare(strict_types=1);

namespace PerformanceReview\Command;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ShowTitleCommand extends AbstractMagentoCommand
{
    /**
     * Configure the command
     */
    protected function configure()
    {
        $this
            ->setName('performance:review:title')
            ->setDescription('Display Magento Performance Review title')
            ->setHelp('This command displays a formatted title for Magento Performance Review');
    }

    /**
     * Execute the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Using Symfony Console's built-in styling
        $io = new \Symfony\Component\Console\Style\SymfonyStyle($input, $output);
        
        $io->newLine();
        
        // Create a fancy title block
        $io->block([
            'MAGENTO PERFORMANCE REVIEW',
            'System Analysis Tool',
            'Version 1.0'
        ], 'INFO', 'fg=white;bg=blue', ' ', true);
        
        $io->newLine();
        
        // Alternative simple approach (commented out)
        // $output->writeln('');
        // $output->writeln('<info>========================================</info>');
        // $output->writeln('<info>       Magento Performance Review       </info>');
        // $output->writeln('<info>========================================</info>');
        // $output->writeln('');

        return 0;
    }
}