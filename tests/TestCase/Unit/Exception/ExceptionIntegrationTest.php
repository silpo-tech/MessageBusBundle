<?php

declare(strict_types=1);

namespace MessageBusBundle\Tests\TestCase\Unit\Exception;

use MessageBusBundle\Exception\CompressException;
use MessageBusBundle\Exception\InterruptProcessingException;
use MessageBusBundle\Exception\MessageBusException;
use MessageBusBundle\Exception\NonBatchProcessorException;
use MessageBusBundle\Exception\RejectException;
use MessageBusBundle\Exception\RequeueException;
use MessageBusBundle\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

class ExceptionIntegrationTest extends TestCase
{
    public function testRejectException(): void
    {
        $rejectException = new RejectException('Message rejected');

        $this->assertEquals('Message rejected', $rejectException->getMessage());
        $this->assertInstanceOf(MessageBusException::class, $rejectException);
    }

    public function testRequeueException(): void
    {
        $requeueException = new RequeueException('Message requeued', 3);

        $this->assertEquals('Message requeued', $requeueException->getMessage());
        $this->assertEquals(3, $requeueException->getCount());
        $this->assertInstanceOf(MessageBusException::class, $requeueException);
    }

    public function testRequeueExceptionWithDefaults(): void
    {
        $requeueException = new RequeueException('Default count');

        $this->assertEquals('Default count', $requeueException->getMessage());
        $this->assertEquals(5, $requeueException->getCount()); // Default count
    }

    public function testValidationException(): void
    {
        $violations = ['field1' => 'error1', 'field2' => 'error2'];
        $validationException = new ValidationException('Validation failed', $violations);

        $this->assertEquals('Validation failed', $validationException->getMessage());
        $this->assertEquals($violations, $validationException->getViolations());
    }

    public function testValidationExceptionWithoutViolations(): void
    {
        $validationException = new ValidationException('Validation failed');

        $this->assertEquals('Validation failed', $validationException->getMessage());
        $this->assertEquals([], $validationException->getViolations());
    }

    public function testInterruptProcessingException(): void
    {
        $result = ['status' => 'interrupted', 'data' => 'test'];
        $interruptException = new InterruptProcessingException('Processing interrupted');
        $interruptException->setResult($result);

        $this->assertEquals('Processing interrupted', $interruptException->getMessage());
        $this->assertEquals($result, $interruptException->getResult());
    }

    public function testInterruptProcessingExceptionChaining(): void
    {
        $result1 = ['step1' => 'complete'];
        $result2 = ['step2' => 'complete'];

        $interruptException = new InterruptProcessingException();
        $chainedResult = $interruptException->setResult($result1)->setResult($result2);

        $this->assertSame($interruptException, $chainedResult);
        $this->assertEquals($result2, $interruptException->getResult());
    }

    public function testCompressException(): void
    {
        $compressException = new CompressException('Compression failed');

        $this->assertEquals('Compression failed', $compressException->getMessage());
        $this->assertInstanceOf(\RuntimeException::class, $compressException);
    }

    public function testCompressExceptionWithDefaults(): void
    {
        $compressException = new CompressException();

        $this->assertEquals('zlib extension required for messages compression and decompression.', $compressException->getMessage());
        $this->assertInstanceOf(\RuntimeException::class, $compressException);
    }

    public function testNonBatchProcessorException(): void
    {
        $nonBatchException = new NonBatchProcessorException();

        $this->assertInstanceOf(MessageBusException::class, $nonBatchException);
    }

    public function testMessageBusException(): void
    {
        $messageBusException = new MessageBusException('Base exception');

        $this->assertEquals('Base exception', $messageBusException->getMessage());
        $this->assertInstanceOf(\Exception::class, $messageBusException);
    }

    public function testExceptionInheritance(): void
    {
        $rejectException = new RejectException('test');
        $requeueException = new RequeueException('test');
        $nonBatchException = new NonBatchProcessorException();

        // These inherit from MessageBusException
        $this->assertInstanceOf(MessageBusException::class, $rejectException);
        $this->assertInstanceOf(MessageBusException::class, $requeueException);
        $this->assertInstanceOf(MessageBusException::class, $nonBatchException);

        // CompressException inherits from RuntimeException
        $compressException = new CompressException('test');
        $this->assertInstanceOf(\RuntimeException::class, $compressException);

        // ValidationException and InterruptProcessingException have different inheritance
        $validationException = new ValidationException('test');
        $interruptException = new InterruptProcessingException('test');

        $this->assertInstanceOf(\Exception::class, $validationException);
        $this->assertInstanceOf(\Exception::class, $interruptException);
    }
}
