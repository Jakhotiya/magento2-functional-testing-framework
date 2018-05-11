<?php
// @codingStandardsIgnoreFile
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types = 1);

namespace Magento\FunctionalTestingFramework\Console;

use Magento\FunctionalTestingFramework\Suite\SuiteGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateSuiteCommand extends Command
{
    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('generate:suite')
            ->setDescription('Generates a single suite based on declaration in xml')
            ->addArgument(
                'suites',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'suite names for generation (separated by space)'
            );
    }

    /**
     * Executes the current command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \Symfony\Component\Console\Exception\LogicException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $suites = $input->getArgument('suites');

        foreach ($suites as $suite) {
            SuiteGenerator::getInstance()->generateSuite($suite);
        }

        if ($output->isVerbose()) {
            $output->writeLn("All Suites Generated");
        }
    }

}
