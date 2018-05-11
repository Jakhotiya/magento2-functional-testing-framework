<?php
// @codingStandardsIgnoreFile
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types = 1);

namespace Magento\FunctionalTestingFramework\Console;

use Magento\FunctionalTestingFramework\Config\MftfApplicationConfig;
use Magento\FunctionalTestingFramework\Exceptions\TestFrameworkException;
use Magento\FunctionalTestingFramework\Suite\SuiteGenerator;
use Magento\FunctionalTestingFramework\Test\Handlers\TestObjectHandler;
use Magento\FunctionalTestingFramework\Util\Manifest\ParallelTestManifest;
use Magento\FunctionalTestingFramework\Util\Manifest\TestManifestFactory;
use Magento\FunctionalTestingFramework\Util\TestGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateTestsCommand extends Command
{
    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('generate:tests')
            ->setDescription('Generates all test files and suites based on xml declarations')
            ->addArgument('name', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'name(s) of specific tests to generate')
            ->addOption("config", null, InputOption::VALUE_REQUIRED, 'default, singleRun, or parallel', 'default')
            ->addOption("force", null,InputOption::VALUE_NONE, 'force generation of tests regardless of Magento Instance Configuration')
            ->addOption('lines', null, InputOption::VALUE_REQUIRED, 'Used in combination with a parallel configuration, determines desired group size', 500)
            ->addOption('tests', null, InputOption::VALUE_REQUIRED, 'A parameter accepting a JSON string used to determine the test configuration');
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
        $tests = $input->getArgument('name');
        $config = $input->getOption('config');
        $json = $input->getOption('tests');
        $force = $input->getOption('force');
        $lines = $input->getOption('lines');
        $verbose = $output->isVerbose();

        $testConfiguration = $this->createTestConfiguration($json, $tests, $force, $verbose);

        // create our manifest file here
        $testManifest = TestManifestFactory::makeManifest($config, $testConfiguration['suites']);
        TestGenerator::getInstance(null, $testConfiguration['tests'])->createAllTestFiles($testManifest);

        if ($config == 'parallel') {
            /** @var ParallelTestManifest $testManifest */
            $testManifest->createTestGroups($lines);
        }

        SuiteGenerator::getInstance()->generateAllSuites($testManifest);
        $testManifest->generate();

        print "Generate Tests Command Run" . PHP_EOL;
    }

    /**
     * Function which builds up a configuration including test and suites for consumption of Magento generation methods.
     *
     * @param string $json
     * @param array $tests
     * @param bool $force
     * @param bool $verbose
     * @return array
     */
    private function createTestConfiguration($json, array $tests, bool $force, bool $verbose)
    {
        // set our application configuration so we can references the user options in our framework
        MftfApplicationConfig::create(
            $force,
            MftfApplicationConfig::GENERATION_PHASE,
            $verbose
        );

        $testConfiguration = [];
        $testConfiguration['tests'] = $tests;
        $testConfiguration['suites'] = [];

        $testConfiguration = $this->parseTestsConfigJson($json, $testConfiguration);

        // if we have references to specific tests, we resolve the test objects and pass them to the config
        if (!empty($testConfiguration['tests']))
        {
            $testObjects = [];

            foreach ($testConfiguration['tests'] as $test)
            {
                $testObjects[$test] = TestObjectHandler::getInstance()->getObject($test);
            }

            $testConfiguration['tests'] = $testObjects;
        }

        return $testConfiguration;
    }

    /**
     * Function which takes a json string of potential custom configuration and parses/validates the resulting json
     * passed in by the user. The result is a testConfiguration array.
     *
     * @param string $json
     * @param array $testConfiguration
     * @throws TestFrameworkException
     * @return array
     */
    private function parseTestsConfigJson($json, array $testConfiguration) {
        if ($json == null) {
            return $testConfiguration;
        }

        $jsonTestConfiguration = [];
        $testConfigArray = json_decode($json, true);

        // stop execution if we have failed to properly parse any json
        if (json_last_error() != JSON_ERROR_NONE) {
            throw new TestFrameworkException("JSON could not be parsed: " . json_last_error_msg());
        }

        $jsonTestConfiguration['tests'] = $testConfigArray['tests'] ?? null;;
        $jsonTestConfiguration['suites'] = $testConfigArray['suites'] ?? null;
        return $jsonTestConfiguration;
    }
}
