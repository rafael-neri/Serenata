<?php

namespace Serenata\Tests\Integration\UserInterface\JsonRpcQueueItemHandler;

use Serenata\Analysis\Visiting\UseStatementKind;

use Serenata\Common\Position;

use Serenata\Indexing\FileNotFoundStorageException;
use Serenata\Tests\Integration\AbstractIntegrationTest;

final class LocalizeTypeJsonRpcQueueItemHandlerTest extends AbstractIntegrationTest
{
    /**
     * @return void
     */
    public function testCorrectlyLocalizesVariousTypes(): void
    {
        $path = __DIR__ . '/LocalizeTypeJsonRpcQueueItemHandlerTest/' . 'LocalizeType.phpt';

        $this->indexTestFile($this->container, $path);

        $command = $this->container->get('localizeTypeJsonRpcQueueItemHandler');

        self::assertSame('C', $command->localizeType('C', $path, new Position(0, 0), UseStatementKind::TYPE_CLASSLIKE));
        self::assertSame('\C', $command->localizeType('\C', $path, new Position(4, 0), UseStatementKind::TYPE_CLASSLIKE));
        self::assertSame('C', $command->localizeType('\A\C', $path, new Position(4, 0), UseStatementKind::TYPE_CLASSLIKE));
        self::assertSame('C', $command->localizeType('\B\C', $path, new Position(9, 0), UseStatementKind::TYPE_CLASSLIKE));
        self::assertSame('DateTime', $command->localizeType('\B\DateTime', $path, new Position(9, 0), UseStatementKind::TYPE_CLASSLIKE));
        self::assertSame('DateTime', $command->localizeType('\DateTime', $path, new Position(10, 0), UseStatementKind::TYPE_CLASSLIKE));
        self::assertSame('DateTime', $command->localizeType('DateTime', $path, new Position(11, 0), UseStatementKind::TYPE_CLASSLIKE));
        self::assertSame('DateTime', $command->localizeType('\DateTime', $path, new Position(11, 0), UseStatementKind::TYPE_CLASSLIKE));
        self::assertSame('D\Test', $command->localizeType('\C\D\Test', $path, new Position(12, 0), UseStatementKind::TYPE_CLASSLIKE));
        self::assertSame('E', $command->localizeType('\C\D\E', $path, new Position(13, 0), UseStatementKind::TYPE_CLASSLIKE));
        self::assertSame('H', $command->localizeType('\F\G\H', $path, new Position(15, 0), UseStatementKind::TYPE_CLASSLIKE));
        self::assertSame('SOME_CONSTANT', $command->localizeType('\A\SOME_CONSTANT', $path, new Position(17, 0), UseStatementKind::TYPE_CONSTANT));
        self::assertSame('some_function', $command->localizeType('\A\some_function', $path, new Position(17, 0), UseStatementKind::TYPE_FUNCTION));
    }

    /**
     * @return void
     */
    public function testCorrectlyIgnoresMismatchedKinds(): void
    {
        $path = __DIR__ . '/LocalizeTypeJsonRpcQueueItemHandlerTest/' . 'LocalizeType.phpt';

        $this->indexTestFile($this->container, $path);

        $command = $this->container->get('localizeTypeJsonRpcQueueItemHandler');

        self::assertSame('\C\D\Test', $command->localizeType('\C\D\Test', $path, new Position(12, 0), UseStatementKind::TYPE_CONSTANT));
        self::assertSame('\SOME_CONSTANT', $command->localizeType('\SOME_CONSTANT', $path, new Position(17, 0), UseStatementKind::TYPE_CLASSLIKE));
        self::assertSame('\some_function', $command->localizeType('\some_function', $path, new Position(17, 0), UseStatementKind::TYPE_CLASSLIKE));
    }

    /**
     * @return void
     */
    public function testThrowsExceptionWhenFileIsNotInIndex(): void
    {
        $command = $this->container->get('localizeTypeJsonRpcQueueItemHandler');

        $this->expectException(FileNotFoundStorageException::class);

        $command->localizeType('A', 'DoesNotExist.phpt', new Position(0, 1), UseStatementKind::TYPE_CLASSLIKE);
    }
}
