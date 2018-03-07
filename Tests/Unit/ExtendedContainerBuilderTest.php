<?php

namespace Oro\Component\DependencyInjection\Tests\Unit;

use Oro\Component\DependencyInjection\ExtendedContainerBuilder;
use Oro\Component\DependencyInjection\Tests\Unit\Fixtures\CompilerPass1;
use Oro\Component\DependencyInjection\Tests\Unit\Fixtures\CompilerPass2;
use Oro\Component\DependencyInjection\Tests\Unit\Fixtures\CompilerPass3;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

class ExtendedContainerBuilderTest extends \PHPUnit_Framework_TestCase
{
    const EXTENSION = 'ext';

    /** @var ExtendedContainerBuilder */
    private $builder;

    public function setUp()
    {
        /** @var ExtensionInterface|\PHPUnit_Framework_MockObject_MockObject $extension */
        $extension = $this->createMock(ExtensionInterface::class);
        $extension
            ->expects($this->any())
            ->method('getAlias')
            ->will($this->returnValue(static::EXTENSION));

        $this->builder = new ExtendedContainerBuilder();
        $this->builder->registerExtension($extension);
    }

    public function testSetExtensionConfigShouldOverwriteCurrentConfig()
    {
        $originalConfig    = ['prop' => 'val'];
        $overwrittenConfig = [['p' => 'v']];

        $this->builder->prependExtensionConfig(static::EXTENSION, $originalConfig);
        $this->assertEquals([$originalConfig], $this->builder->getExtensionConfig(static::EXTENSION));

        $this->builder->setExtensionConfig(static::EXTENSION, $overwrittenConfig);
        $this->assertEquals($overwrittenConfig, $this->builder->getExtensionConfig(static::EXTENSION));
    }

    public function testMoveCompilerPassBefore()
    {
        $srcPass    = new CompilerPass1();
        $targetPass = new CompilerPass2();
        [$resolveClassPass, $resolveInstanceOfConditionalsPass] =
            $this->builder->getCompilerPassConfig()->getBeforeOptimizationPasses();

        $this->builder->addCompilerPass($targetPass);
        $this->builder->addCompilerPass($srcPass);
        $this->builder->moveCompilerPassBefore(get_class($srcPass), get_class($targetPass));
        $this->assertSame(
            [$resolveClassPass, $resolveInstanceOfConditionalsPass, $srcPass, $targetPass],
            $this->builder->getCompilerPassConfig()->getBeforeOptimizationPasses()
        );
    }

    public function testMoveCompilerPassBeforeForNonDefaultPassType()
    {
        $srcPass    = new CompilerPass1();
        $targetPass = new CompilerPass2();
        $this->builder->addCompilerPass($targetPass, PassConfig::TYPE_BEFORE_REMOVING);
        $this->builder->addCompilerPass($srcPass, PassConfig::TYPE_BEFORE_REMOVING);
        $this->builder->moveCompilerPassBefore(
            get_class($srcPass),
            get_class($targetPass),
            PassConfig::TYPE_BEFORE_REMOVING
        );
        $this->assertSame(
            [$srcPass, $targetPass],
            $this->builder->getCompilerPassConfig()->getBeforeRemovingPasses()
        );
    }

    public function testMoveCompilerPassBeforeWhenThereIsAnotherPassExistsBeforeTargetPass()
    {
        [$resolveClassPass, $resolveInstanceOfConditionalsPass] =
            $this->builder->getCompilerPassConfig()->getBeforeOptimizationPasses();

        $srcPass     = new CompilerPass1();
        $targetPass  = new CompilerPass2();
        $anotherPass = new CompilerPass3();
        $this->builder->addCompilerPass($anotherPass);
        $this->builder->addCompilerPass($targetPass);
        $this->builder->addCompilerPass($srcPass);
        $this->builder->moveCompilerPassBefore(get_class($srcPass), get_class($targetPass));
        $this->assertSame(
            [$resolveClassPass, $resolveInstanceOfConditionalsPass, $anotherPass, $srcPass, $targetPass],
            $this->builder->getCompilerPassConfig()->getBeforeOptimizationPasses()
        );
    }

    public function testMoveCompilerPassBeforeWhenThereIsAnotherPassExistsAfterSrcPass()
    {
        [$resolveClassPass, $resolveInstanceOfConditionalsPass] =
            $this->builder->getCompilerPassConfig()->getBeforeOptimizationPasses();

        $srcPass     = new CompilerPass1();
        $targetPass  = new CompilerPass2();
        $anotherPass = new CompilerPass3();
        $this->builder->addCompilerPass($targetPass);
        $this->builder->addCompilerPass($srcPass);
        $this->builder->addCompilerPass($anotherPass);
        $this->builder->moveCompilerPassBefore(get_class($srcPass), get_class($targetPass));
        $this->assertSame(
            [$resolveClassPass, $resolveInstanceOfConditionalsPass, $srcPass, $targetPass, $anotherPass],
            $this->builder->getCompilerPassConfig()->getBeforeOptimizationPasses()
        );
    }

    public function testMoveCompilerPassBeforeWhenSrcPassIsAlreadyBeforeTargetPass()
    {
        [$resolveClassPass, $resolveInstanceOfConditionalsPass] =
            $this->builder->getCompilerPassConfig()->getBeforeOptimizationPasses();

        $srcPass    = new CompilerPass1();
        $targetPass = new CompilerPass2();
        $this->builder->addCompilerPass($srcPass);
        $this->builder->addCompilerPass($targetPass);
        $this->builder->moveCompilerPassBefore(get_class($srcPass), get_class($targetPass));
        $this->assertSame(
            [$resolveClassPass, $resolveInstanceOfConditionalsPass, $srcPass, $targetPass],
            $this->builder->getCompilerPassConfig()->getBeforeOptimizationPasses()
        );
    }

    public function testMoveCompilerPassBeforeWhenDoubleTargetPasses()
    {
        [$resolveClassPass, $resolveInstanceOfConditionalsPass] =
            $this->builder->getCompilerPassConfig()->getBeforeOptimizationPasses();

        $srcPass     = new CompilerPass1();
        $target1Pass = new CompilerPass2();
        $target2Pass = new CompilerPass2();
        $this->builder->addCompilerPass($target1Pass);
        $this->builder->addCompilerPass($target2Pass);
        $this->builder->addCompilerPass($srcPass);
        $this->builder->moveCompilerPassBefore(get_class($srcPass), get_class($target1Pass));
        $this->assertSame(
            [$resolveClassPass, $resolveInstanceOfConditionalsPass, $srcPass, $target1Pass, $target2Pass],
            $this->builder->getCompilerPassConfig()->getBeforeOptimizationPasses()
        );
    }

    public function testMoveCompilerPassBeforeForEmptyPasses()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Unknown compiler pass "%s"', CompilerPass1::class));
        $srcPass    = new CompilerPass1();
        $targetPass = new CompilerPass2();
        $this->builder->moveCompilerPassBefore(get_class($srcPass), get_class($targetPass));
    }

    public function testMoveCompilerPassBeforeWhenSrcPassDoesNotExist()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Unknown compiler pass "%s"', CompilerPass1::class));
        $srcPass    = new CompilerPass1();
        $targetPass = new CompilerPass2();
        $this->builder->addCompilerPass($targetPass);
        $this->builder->moveCompilerPassBefore(get_class($srcPass), get_class($targetPass));
    }

    public function testMoveCompilerPassBeforeWhenTargetPassDoesNotExist()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Unknown compiler pass "%s"', CompilerPass2::class));
        $srcPass    = new CompilerPass1();
        $targetPass = new CompilerPass2();
        $this->builder->addCompilerPass($srcPass);
        $this->builder->moveCompilerPassBefore(get_class($srcPass), get_class($targetPass));
    }

    public function testMoveCompilerPassBeforeWhenTargetPassHasLowerPriority()
    {
        [$resolveClassPass, $resolveInstanceOfConditionalsPass] =
            $this->builder->getCompilerPassConfig()->getBeforeOptimizationPasses();

        $srcPass    = new CompilerPass1();
        $targetPass = new CompilerPass2();
        $this->builder->addCompilerPass($srcPass, PassConfig::TYPE_BEFORE_OPTIMIZATION, 10);
        $this->builder->addCompilerPass($targetPass, PassConfig::TYPE_BEFORE_OPTIMIZATION, 5);
        $this->builder->moveCompilerPassBefore(get_class($srcPass), get_class($targetPass));
        $this->assertSame(
            [$resolveClassPass, $resolveInstanceOfConditionalsPass, $srcPass, $targetPass],
            $this->builder->getCompilerPassConfig()->getBeforeOptimizationPasses()
        );
    }

    public function testMoveCompilerPassBeforeWhenTargetPassHasHigherPriority()
    {
        [$resolveClassPass, $resolveInstanceOfConditionalsPass] =
            $this->builder->getCompilerPassConfig()->getBeforeOptimizationPasses();

        $srcPass    = new CompilerPass1();
        $targetPass = new CompilerPass2();
        $this->builder->addCompilerPass($srcPass, PassConfig::TYPE_BEFORE_OPTIMIZATION, 5);
        $this->builder->addCompilerPass($targetPass, PassConfig::TYPE_BEFORE_OPTIMIZATION, 10);
        $this->builder->moveCompilerPassBefore(get_class($srcPass), get_class($targetPass));
        $this->assertSame(
            [$resolveClassPass, $resolveInstanceOfConditionalsPass, $srcPass, $targetPass],
            $this->builder->getCompilerPassConfig()->getBeforeOptimizationPasses()
        );
    }

    public function testAddCompilerPassAfterTargetPass()
    {
        [$resolveClassPass, $resolveInstanceOfConditionalsPass] =
            $this->builder->getCompilerPassConfig()->getBeforeOptimizationPasses();

        $srcPass    = new CompilerPass1();
        $targetPass = new CompilerPass2();
        $this->builder->addCompilerPass($srcPass, PassConfig::TYPE_BEFORE_OPTIMIZATION, 5);
        $this->builder->addCompilerPass($targetPass, PassConfig::TYPE_BEFORE_OPTIMIZATION, 10);
        $this->builder->moveCompilerPassBefore(get_class($srcPass), get_class($targetPass));

        $beforeTargetPass = new CompilerPass3();
        $this->builder->addCompilerPass($beforeTargetPass, PassConfig::TYPE_BEFORE_OPTIMIZATION, 17);

        $this->assertSame(
            [$resolveClassPass, $resolveInstanceOfConditionalsPass, $beforeTargetPass, $srcPass, $targetPass],
            $this->builder->getCompilerPassConfig()->getBeforeOptimizationPasses()
        );
    }

    public function testAddCompilerPassBeforeTargetPass()
    {
        [$resolveClassPass, $resolveInstanceOfConditionalsPass] =
            $this->builder->getCompilerPassConfig()->getBeforeOptimizationPasses();

        $srcPass    = new CompilerPass1();
        $targetPass = new CompilerPass2();
        $this->builder->addCompilerPass($srcPass, PassConfig::TYPE_BEFORE_OPTIMIZATION, 5);
        $this->builder->addCompilerPass($targetPass, PassConfig::TYPE_BEFORE_OPTIMIZATION, 10);
        $this->builder->moveCompilerPassBefore(get_class($srcPass), get_class($targetPass));

        $afterTargetPass = new CompilerPass3();
        $this->builder->addCompilerPass($afterTargetPass, PassConfig::TYPE_BEFORE_OPTIMIZATION, 7);

        $this->assertSame(
            [$resolveClassPass, $resolveInstanceOfConditionalsPass, $srcPass, $targetPass, $afterTargetPass],
            $this->builder->getCompilerPassConfig()->getBeforeOptimizationPasses()
        );
    }
}
