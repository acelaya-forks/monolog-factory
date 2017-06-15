<?php

declare(strict_types=1);

namespace MonologFactory\Tests;

use Interop\Container\ContainerInterface;
use Monolog\Formatter\HtmlFormatter;
use Monolog\Handler\NativeMailerHandler;
use Monolog\Handler\NullHandler;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\PsrLogMessageProcessor;
use MonologFactory\ContainerInteropLoggerFactory;
use MonologFactory\Exception\InvalidArgumentException;
use MonologFactory\Exception\LoggerComponentNotResolvedException;
use MonologFactory\Tests\TestAsset\ContainerAsset;
use MonologFactory\Tests\TestAsset\Logger\ProcessorFactoryAsset;
use PHPUnit\Framework\TestCase;

class ContainerInteropLoggerFactoryTest extends TestCase
{
    /**
     * @var ContainerInterface
     */
    protected $container;
    
    protected function setUp()
    {
        $this->container = new ContainerAsset([
            'Config' => [
                'logger' => [
                    'logger1' => [
                        'name' => 'logger1',
                        'handlers' => [
                            [
                                'name' => NativeMailerHandler::class,
                                'options' => [
                                    'to' => 'test@example.com',
                                    'subject' => 'Test',
                                    'from' => 'noreply@example.com',
                                    'level' => Logger::ALERT,
                                    'formatter' => [
                                        'name' => HtmlFormatter::class,
                                    ],
                                ],
                            ],
                        ],
                        'processors' => [
                            [
                                'name' => PsrLogMessageProcessor::class,
                            ],
                        ],
                    ],
                    'logger2' => [
                        'name' => 'logger2',
                        'handlers' => [
                            'DefaultLoggerHandler',
                            [
                                'name' => NativeMailerHandler::class,
                                'options' => [
                                    'to' => 'test@example.com',
                                    'subject' => 'Test',
                                    'from' => 'noreply@example.com',
                                    'level' => Logger::ALERT,
                                    'formatter' => 'HtmlLoggerFormatter',
                                ],
                            ],
                        ],
                        'processors' => [
                            ProcessorFactoryAsset::class,
                        ],
                    ],
                    'invalid_handler_logger' => [
                        'name' => 'invalid_handler_logger',
                        'handlers' => [
                            'NonExistingHandler',
                        ],
                    ],
                    'invalid_formatter_logger' => [
                        'name' => 'invalid_handler_logger',
                        'handlers' => [
                            [
                                'name' => NullHandler::class,
                                'options' => [
                                    'formatter' => 'NonExistingFormatter',
                                ],
                            ],
                        ],
                    ],
                    'invalid_processor_logger' => [
                        'name' => 'invalid_processor_logger',
                        'handlers' => [
                            [
                                'name' => NullHandler::class,
                            ],
                        ],
                        'processors' => [
                            'NonExistingProcessor',
                        ],
                    ]
                ],
            ],
            'DefaultLoggerHandler' => new NullHandler(),
            'HtmlLoggerFormatter' => new HtmlFormatter(),
            'MemoryUsageLoggerProcessor' => new MemoryUsageProcessor(),
        ]);
    }

    /**
     * @test
     */
    public function it_creates_logger_from_configuration()
    {
        $factory = new ContainerInteropLoggerFactory('logger1');

        /* @var $logger Logger */
        $logger = $factory($this->container);

        $this->assertInstanceOf(Logger::class, $logger);
        $this->assertEquals('logger1', $logger->getName());
        $this->assertCount(1, $logger->getHandlers());
        $this->assertCount(1, $logger->getProcessors());
    }

    /**
     * @test
     */
    public function it_creates_logger_from_alias_configuration_service()
    {
        $factory = new ContainerInteropLoggerFactory('logger3');

        /* @var $logger Logger */
        $logger = $factory(new ContainerAsset([
            'config' => [
                'logger' => [
                    'logger3' => [
                        'name' => 'logger3',
                        'handlers' => [
                            [
                                'name' => TestHandler::class,
                            ],
                        ],
                    ],
                ],
            ],
        ]));

        $this->assertInstanceOf(Logger::class, $logger);
        $this->assertEquals('logger3', $logger->getName());
    }

    /**
     * @test
     */
    public function it_creates_empty_logger_if_specified_does_not_exist_in_configuration()
    {
        $factory = new ContainerInteropLoggerFactory();

        /* @var $logger Logger */
        $logger = $factory($this->container);

        $this->assertInstanceOf(Logger::class, $logger);
        $this->assertEquals('default', $logger->getName());
        $this->assertCount(0, $logger->getHandlers());
        $this->assertCount(0, $logger->getProcessors());
    }

    /**
     * @test
     */
    public function it_creates_logger_when_invoked_using_static_variance()
    {
        $factory = [ContainerInteropLoggerFactory::class, 'logger1'];

        /* @var $logger Logger */
        $logger = call_user_func($factory, $this->container);

        $this->assertInstanceOf(Logger::class, $logger);
    }

    /**
     * @test
     */
    public function it_raises_exception_if_container_not_passed_in_arguments_when_invoked_using_static_variance()
    {
        $factory = [ContainerInteropLoggerFactory::class, 'logger1'];

        try {
            call_user_func($factory, 'invalid');

            $this->fail('Exception should have been raised');
        } catch (InvalidArgumentException $ex) {
            $this->assertEquals(
                'The first argument for MonologFactory\\ContainerInteropLoggerFactory::__callStatic method must be of type Interop\\Container\\ContainerInterface',
                $ex->getMessage()
            );
        }
    }

    /**
     * @test
     */
    public function it_creates_logger_by_resolving_handler_from_container()
    {
        $factory = new ContainerInteropLoggerFactory('logger2');

        /* @var $logger Logger */
        $logger = $factory($this->container);

        $this->assertInstanceOf(Logger::class, $logger);
        $this->assertEquals('logger2', $logger->getName());
        $handlers = $logger->getHandlers();
        $this->assertCount(2, $handlers);
        $this->assertInstanceOf(NullHandler::class, $handlers[0]);
        $this->assertInstanceOf(NativeMailerHandler::class, $handlers[1]);
    }

    /**
     * @test
     */
    public function it_raises_exception_if_handler_cannot_be_resolved_from_container()
    {
        $factory = new ContainerInteropLoggerFactory('invalid_handler_logger');

        try {
            $factory($this->container);

            $this->fail('Exception should have been raised');
        } catch (LoggerComponentNotResolvedException $ex) {
            $this->assertContains('Logger component could not be resolved', $ex->getMessage());
        }
    }

    /**
     * @test
     */
    public function it_raises_exception_if_formatter_cannot_be_resolved_from_container()
    {
        $factory = new ContainerInteropLoggerFactory('invalid_formatter_logger');

        try {
            $factory($this->container);

            $this->fail('Exception should have been raised');
        } catch (LoggerComponentNotResolvedException $ex) {
            $this->assertContains('Logger component could not be resolved', $ex->getMessage());
        }
    }

    /**
     * @test
     */
    public function it_raises_exception_if_processor_cannot_be_resolved_from_container()
    {
        $factory = new ContainerInteropLoggerFactory('invalid_processor_logger');

        try {
            $factory($this->container);

            $this->fail('Exception should have been raised');
        } catch (LoggerComponentNotResolvedException $ex) {
            $this->assertContains('Logger component could not be resolved', $ex->getMessage());
        }
    }

    /**
     * @test
     */
    public function it_creates_logger_by_resolving_handler_formatter_from_container()
    {
        $factory = new ContainerInteropLoggerFactory('logger2');

        /* @var $logger Logger */
        $logger = $factory($this->container);

        $this->assertInstanceOf(Logger::class, $logger);
        $this->assertEquals('logger2', $logger->getName());
        $handlers = $logger->getHandlers();
        $this->assertCount(2, $handlers);
        $this->assertInstanceOf(NativeMailerHandler::class, $handlers[1]);
        $this->assertInstanceOf(HtmlFormatter::class, $handlers[1]->getFormatter());
    }

    /**
     * @test
     */
    public function it_creates_logger_by_resolving_processor_from_container()
    {
        $factory = new ContainerInteropLoggerFactory('logger2');

        /* @var $logger Logger */
        $logger = $factory($this->container);

        $this->assertInstanceOf(Logger::class, $logger);
        $this->assertEquals('logger2', $logger->getName());
        $processors = $logger->getProcessors();
        $this->assertCount(1, $logger->getProcessors());
        $this->assertInstanceOf(MemoryUsageProcessor::class, $processors[0]);
    }
}
