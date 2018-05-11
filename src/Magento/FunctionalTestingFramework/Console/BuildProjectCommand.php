<?php
// @codingStandardsIgnoreFile
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types = 1);

namespace Magento\FunctionalTestingFramework\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Magento\FunctionalTestingFramework\Util\Env\EnvProcessor;
use Symfony\Component\Yaml\Yaml;

class BuildProjectCommand extends Command
{
    /**
     * Env processor manages .env files.
     *
     * @var \Magento\FunctionalTestingFramework\Util\Env\EnvProcessor
     */
    private $envProcessor;

    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('build:project');
        $this->setDescription('Generate configuration files for the project. Build the Codeception project.');
        $this->envProcessor = new EnvProcessor(TESTS_BP . DIRECTORY_SEPARATOR . '.env');
        $env = $this->envProcessor->getEnv();
        foreach ($env as $key => $value) {
            $this->addOption($key, null, InputOption::VALUE_REQUIRED, '', $value);
        }
    }

    /**
     * Executes the current command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \Symfony\Component\Console\Exception\LogicException
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->generateConfigFiles($output);

        $setupEnvCommand = new SetupEnvCommand();
        $commandInput = [];
        $options = $input->getOptions();
        $env = array_keys($this->envProcessor->getEnv());
        foreach ($options as $key => $value) {
            if (in_array($key, $env)) {
                $commandInput['--' . $key] = $value;
            }
        }
        $commandInput = new ArrayInput($commandInput);
        $setupEnvCommand->run($commandInput, $output);


        // TODO can we just import the codecept symfony command?
        $codeceptBuildCommand = realpath(PROJECT_ROOT . '/vendor/bin/codecept') .  ' build';
        $process = new Process($codeceptBuildCommand);
        $process->setWorkingDirectory(TESTS_BP);
        $process->run(
            function ($type, $buffer) use ($output) {
                $output->write($buffer);
            }
        );
    }

    /**
     * Generates needed codeception configuration files to the TEST_BP directory
     *
     * @param OutputInterface $output
     * @return void
     */
    private function generateConfigFiles(OutputInterface $output)
    {
        //Find travel path from codeception.yml to FW_BP
        $relativePath = $this->returnRelativePath(TESTS_BP, FW_BP);

        if (!file_exists(TESTS_BP . DIRECTORY_SEPARATOR . 'codeception.yml')) {
            // read in the codeception.yml file
            $configDistYml = Yaml::parse(file_get_contents(realpath(FW_BP . "/etc/config/codeception.dist.yml")));
            $configDistYml["paths"]["support"] = $relativePath . 'src/Magento/FunctionalTestingFramework';
            $configDistYml["paths"]["envs"] = $relativePath . 'etc/_envs';
            $configYmlText = Yaml::dump($configDistYml, 10);

            // dump output to new codeception.yml file
            file_put_contents(TESTS_BP . DIRECTORY_SEPARATOR . 'codeception.yml', $configYmlText);
            $output->writeln("codeception.yml configuration successfully applied.");
        }

        // copy the functional suite yml, this will only copy if there are differences between the template the destination
        $fileSystem = new Filesystem();
        $fileSystem->copy(
            realpath(FW_BP. "/etc/config/functional.suite.dist.yml"),
            TESTS_BP . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'functional.suite.yml'
        );
        $output->writeln("functional.suite.yml configuration successfully applied.");
    }

    /**
     * Finds relative paths between two paths passed in as strings.
     *
     * @param string $from
     * @param string $to
     * @return string
     */
    private function returnRelativePath($from, $to)
    {
        $from = is_dir($from) ? rtrim($from, '\/') . '/' : $from;
        $to   = is_dir($to)   ? rtrim($to, '\/') . '/'   : $to;
        $from = str_replace('\\', '/', $from);
        $to   = str_replace('\\', '/', $to);

        $from     = explode('/', $from);
        $to       = explode('/', $to);
        $relPath  = $to;

        foreach($from as $depth => $dir) {
            // find first non-matching dir
            if($dir === $to[$depth]) {
                // ignore this directory
                array_shift($relPath);
            } else {
                // get number of remaining dirs to $from
                $remaining = count($from) - $depth;
                if($remaining > 1) {
                    // add traversals up to first matching dir
                    $padLength = (count($relPath) + $remaining - 1) * -1;
                    $relPath = array_pad($relPath, $padLength, '..');
                    break;
                } else {
                    $relPath[0] = './' . $relPath[0];
                }
            }
        }

        return implode('/', $relPath);
    }
}
