<?php
declare(strict_types=1);

namespace Tests\JeckelLab\CommandDispatcher;

use JeckelLab\CommandDispatcher\CommandDispatcher;
use JeckelLab\CommandDispatcher\CommandHandler\CommandHandlerInterface;
use JeckelLab\CommandDispatcher\Command\CommandInterface;
use JeckelLab\CommandDispatcher\CommandResponse\CommandResponseInterface;
use JeckelLab\CommandDispatcher\Resolver\CommandHandlerResolverInterface;
use JeckelLab\CommandDispatcher\Resolver\HandlerNotFoundException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use stdClass;

/**
 * Class CommandDispatcherTest
 */
final class CommandDispatcherTest extends TestCase
{
    /**
     * @var CommandInterface|MockObject
     */
    protected $command;

    /**
     * @var CommandResponseInterface|MockObject
     */
    protected $response;

    /**
     * @var CommandHandlerInterface|MockObject
     */
    protected $handler;

    /**
     * @var CommandHandlerResolverInterface|MockObject
     */
    protected $resolver;

    /**
     * @var MockObject|EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * setUp
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->command = $this->createMock(CommandInterface::class);
        $this->response = $this->createMock(CommandResponseInterface::class);
        $this->handler = $this->createMock(CommandHandlerInterface::class);
        $this->resolver = $this->createMock(CommandHandlerResolverInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    }

    /**
     * Test dispatch with no event dispatcher configured
     */
    public function testDispatchWoEventDispatcher(): void
    {
        $this->response->expects($this->never())
            ->method('getEvents');

        $this->handler->expects($this->once())
            ->method('__invoke')
            ->with($this->command)
            ->willReturn($this->response);

        $this->resolver->expects($this->once())
            ->method('resolve')
            ->with($this->command)
            ->willReturn($this->handler);

        $dispatcher = new CommandDispatcher($this->resolver);

        $this->assertSame($this->response, $dispatcher->dispatch($this->command));
    }

    /**
     * Test dispatch with event dispatcher but no events returned in response
     */
    public function testDispatchWEventDispatcherWOEvents(): void
    {
        $this->response->expects($this->once())
            ->method('getEvents')
            ->willReturn(null);
        $this->handler->expects($this->once())
            ->method('__invoke')
            ->with($this->command)
            ->willReturn($this->response);
        $this->resolver->expects($this->once())
            ->method('resolve')
            ->with($this->command)
            ->willReturn($this->handler);
        $this->eventDispatcher->expects($this->never())
            ->method('dispatch');

        $dispatcher = new CommandDispatcher($this->resolver, $this->eventDispatcher);
        $this->assertSame($this->response, $dispatcher->dispatch($this->command));
    }

    /**
     * Test dispatch with event dispatcher and on event returned in response
     */
    public function testDispatchWEventDispatcherWEvent(): void
    {
        $event = new stdClass();

        $this->response->expects($this->once())
            ->method('getEvents')
            ->willReturn([$event]);
        $this->handler->expects($this->once())
            ->method('__invoke')
            ->with($this->command)
            ->willReturn($this->response);
        $this->resolver->expects($this->once())
            ->method('resolve')
            ->with($this->command)
            ->willReturn($this->handler);
        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($event);

        $dispatcher = new CommandDispatcher($this->resolver, $this->eventDispatcher);
        $this->assertSame($this->response, $dispatcher->dispatch($this->command));
    }

    /**
     * Test dispatch with event dispatcher and multiple event returned in response
     */
    public function testDispatchWEventDispatcherWMultipleEvents(): void
    {
        $event1 = new stdClass();
        $event2 = new stdClass();

        $this->response->expects($this->once())
            ->method('getEvents')
            ->willReturn([$event1, $event2]);
        $this->handler->expects($this->once())
            ->method('__invoke')
            ->with($this->command)
            ->willReturn($this->response);
        $this->resolver->expects($this->once())
            ->method('resolve')
            ->with($this->command)
            ->willReturn($this->handler);
        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive([$event1], [$event2]);

        $dispatcher = new CommandDispatcher($this->resolver, $this->eventDispatcher);
        $this->assertSame($this->response, $dispatcher->dispatch($this->command));
    }

    /**
     * Test dispatch when resolver throw an Exception (no handler founds)
     */
    public function testDispatchWithErrorResolver(): void
    {
        $exception = new HandlerNotFoundException('foo bar');

        $this->resolver->expects($this->once())
            ->method('resolve')
            ->with($this->command)
            ->willThrowException($exception);

        $this->expectException(HandlerNotFoundException::class);

        (new CommandDispatcher($this->resolver))->dispatch($this->command);
    }
}
