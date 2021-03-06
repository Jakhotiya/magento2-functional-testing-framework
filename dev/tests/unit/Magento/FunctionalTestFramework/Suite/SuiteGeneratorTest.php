<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Tests\unit\Magento\FunctionalTestFramework\Suite;

use AspectMock\Test as AspectMock;
use Magento\FunctionalTestingFramework\ObjectManager\ObjectManager;
use Magento\FunctionalTestingFramework\ObjectManagerFactory;
use Magento\FunctionalTestingFramework\Suite\SuiteGenerator;
use Magento\FunctionalTestingFramework\Suite\Generators\GroupClassGenerator;
use Magento\FunctionalTestingFramework\Suite\Handlers\SuiteObjectHandler;
use Magento\FunctionalTestingFramework\Suite\Parsers\SuiteDataParser;
use Magento\FunctionalTestingFramework\Test\Handlers\TestObjectHandler;
use Magento\FunctionalTestingFramework\Test\Util\TestObjectExtractor;
use Magento\FunctionalTestingFramework\Test\Parsers\TestDataParser;
use Magento\FunctionalTestingFramework\Util\Manifest\DefaultTestManifest;
use PHPUnit\Framework\TestCase;
use tests\unit\Util\SuiteDataArrayBuilder;
use tests\unit\Util\TestDataArrayBuilder;

class SuiteGeneratorTest extends TestCase
{

    /**
     * Tests generating a single suite given a set of parsed test data
     * @throws \Exception
     */
    public function testGenerateSuite()
    {
        $suiteDataArrayBuilder = new SuiteDataArrayBuilder();
        $mockData = $suiteDataArrayBuilder
            ->withName('basicTestSuite')
            ->withAfterHook()
            ->withBeforeHook()
            ->includeTests(['simpleTest'])
            ->includeGroups(['group1'])
            ->build();

        $testDataArrayBuilder = new TestDataArrayBuilder();
        $mockSimpleTest = $testDataArrayBuilder
            ->withName('simpleTest')
            ->withTestActions()
            ->build();

        $mockTestData = ['tests' => array_merge($mockSimpleTest)];
        $this->setMockTestAndSuiteParserOutput($mockTestData, $mockData);

        // parse and generate suite object with mocked data
        $mockSuiteGenerator = SuiteGenerator::getInstance();
        $mockSuiteGenerator->generateSuite("basicTestSuite");

        // assert that expected suite is generated
        $this->expectOutputString("Suite basicTestSuite generated to _generated/basicTestSuite." . PHP_EOL);
    }

    /**
     * Tests generating all suites given a set of parsed test data
     * @throws \Exception
     */
    public function testGenerateAllSuites()
    {
        $suiteDataArrayBuilder = new SuiteDataArrayBuilder();
        $mockData = $suiteDataArrayBuilder
            ->withName('basicTestSuite')
            ->withAfterHook()
            ->withBeforeHook()
            ->includeTests(['simpleTest'])
            ->includeGroups(['group1'])
            ->build();

        $testDataArrayBuilder = new TestDataArrayBuilder();
        $mockSimpleTest = $testDataArrayBuilder
            ->withName('simpleTest')
            ->withTestActions()
            ->build();

        $mockTestData = ['tests' => array_merge($mockSimpleTest)];
        $this->setMockTestAndSuiteParserOutput($mockTestData, $mockData);

        // parse and retrieve suite object with mocked data
        $exampleTestManifest = new DefaultTestManifest([], "sample/path");
        $mockSuiteGenerator = SuiteGenerator::getInstance();
        $mockSuiteGenerator->generateAllSuites($exampleTestManifest);

        // assert that expected suites are generated
        $this->expectOutputString("Suite basicTestSuite generated to _generated/basicTestSuite." . PHP_EOL);
    }

    /**
     * Tests attempting to generate a suite with no included/excluded tests and no hooks
     * @throws \Exception
     */
    public function testGenerateEmptySuite()
    {
        $suiteDataArrayBuilder = new SuiteDataArrayBuilder();
        $mockData = $suiteDataArrayBuilder
            ->withName('basicTestSuite')
            ->build();
        unset($mockData['suites']['basicTestSuite'][TestObjectExtractor::TEST_BEFORE_HOOK]);
        unset($mockData['suites']['basicTestSuite'][TestObjectExtractor::TEST_AFTER_HOOK]);

        $mockTestData = null;
        $this->setMockTestAndSuiteParserOutput($mockTestData, $mockData);

        // set expected error message
        $this->expectExceptionMessage("Suites must not be empty. Suite: \"basicTestSuite\"");

        // parse and generate suite object with mocked data
        $mockSuiteGenerator = SuiteGenerator::getInstance();
        $mockSuiteGenerator->generateSuite("basicTestSuite");
    }

    /**
     * Function used to set mock for parser return and force init method to run between tests.
     *
     * @param array $testData
     * @throws \Exception
     */
    private function setMockTestAndSuiteParserOutput($testData, $suiteData)
    {
        $property = new \ReflectionProperty(SuiteGenerator::class, 'SUITE_GENERATOR_INSTANCE');
        $property->setAccessible(true);
        $property->setValue(null);

        // clear test object handler value to inject parsed content
        $property = new \ReflectionProperty(TestObjectHandler::class, 'testObjectHandler');
        $property->setAccessible(true);
        $property->setValue(null);

        // clear suite object handler value to inject parsed content
        $property = new \ReflectionProperty(SuiteObjectHandler::class, 'SUITE_OBJECT_HANLDER_INSTANCE');
        $property->setAccessible(true);
        $property->setValue(null);

        $mockDataParser = AspectMock::double(TestDataParser::class, ['readTestData' => $testData])->make();
        $mockSuiteDataParser = AspectMock::double(SuiteDataParser::class, ['readSuiteData' => $suiteData])->make();
        $mockGroupClass = AspectMock::double(
            GroupClassGenerator::class,
            ['generateGroupClass' => 'namespace']
        )->make();
        $mockSuiteClass = AspectMock::double(SuiteGenerator::class, ['generateRelevantGroupTests' => null])->make();
        $instance = AspectMock::double(
            ObjectManager::class,
            ['create' => function ($clazz) use (
                $mockDataParser,
                $mockSuiteDataParser,
                $mockGroupClass,
                $mockSuiteClass
            ) {
                if ($clazz == TestDataParser::class) {
                    return $mockDataParser;
                }
                if ($clazz == SuiteDataParser::class) {
                    return $mockSuiteDataParser;
                }
                if ($clazz == GroupClassGenerator::class) {
                    return $mockGroupClass;
                }
                if ($clazz == SuiteGenerator::class) {
                    return $mockSuiteClass;
                }
            }]
        )->make();
        // bypass the private constructor
        AspectMock::double(ObjectManagerFactory::class, ['getObjectManager' => $instance]);

        $property = new \ReflectionProperty(SuiteGenerator::class, 'groupClassGenerator');
        $property->setAccessible(true);
        $property->setValue($instance, $instance);

    }
}
