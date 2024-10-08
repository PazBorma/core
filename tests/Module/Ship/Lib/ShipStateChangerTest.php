<?php

declare(strict_types=1);

namespace Stu\Module\Ship\Lib;

use Mockery\MockInterface;
use Override;
use Stu\Component\Ship\Repair\CancelRepairInterface;
use Stu\Component\Ship\ShipAlertStateEnum;
use Stu\Component\Ship\ShipStateEnum;
use Stu\Component\Ship\System\Data\EpsSystemData;
use Stu\Component\Ship\System\Exception\InsufficientEnergyException;
use Stu\Module\Ship\Lib\Interaction\ShipTakeoverManagerInterface;
use Stu\Module\Ship\Lib\Interaction\TholianWebUtilInterface;
use Stu\Orm\Entity\ShipInterface;
use Stu\Orm\Repository\ShipRepositoryInterface;
use Stu\StuTestCase;

class ShipStateChangerTest extends StuTestCase
{
    /** @var MockInterface|CancelRepairInterface */
    private MockInterface $cancelRepair;

    /** @var MockInterface|AstroEntryLibInterface */
    private MockInterface $astroEntryLib;

    /** @var MockInterface|ShipRepositoryInterface */
    private MockInterface $shipRepository;

    /** @var MockInterface|TholianWebUtilInterface */
    private MockInterface $tholianWebUtil;

    /** @var MockInterface|ShipTakeoverManagerInterface */
    private MockInterface $shipTakeoverManager;

    /** @var MockInterface|ShipWrapperInterface */
    private MockInterface $wrapper;

    /** @var MockInterface|ShipInterface */
    private ShipInterface $ship;

    private ShipStateChangerInterface $subject;

    #[Override]
    public function setUp(): void
    {
        //injected
        $this->cancelRepair = $this->mock(CancelRepairInterface::class);
        $this->astroEntryLib = $this->mock(AstroEntryLibInterface::class);
        $this->shipRepository = $this->mock(ShipRepositoryInterface::class);
        $this->tholianWebUtil = $this->mock(TholianWebUtilInterface::class);
        $this->shipTakeoverManager = $this->mock(ShipTakeoverManagerInterface::class);

        //params
        $this->wrapper = $this->mock(ShipWrapperInterface::class);

        //other
        $this->ship = $this->mock(ShipInterface::class);

        $this->wrapper->shouldReceive('get')
            ->withNoArgs()
            ->zeroOrMoreTimes()
            ->andReturn($this->ship);

        $this->subject = new ShipStateChanger(
            $this->cancelRepair,
            $this->astroEntryLib,
            $this->shipRepository,
            $this->tholianWebUtil,
            $this->shipTakeoverManager
        );
    }

    public function testChangeShipStateExpectNothingWhenDestroyed(): void
    {
        $this->ship->shouldReceive('getState')
            ->withNoArgs()
            ->once()
            ->andReturn(ShipStateEnum::SHIP_STATE_DESTROYED);

        $this->subject->changeShipState($this->wrapper, ShipStateEnum::SHIP_STATE_NONE);
    }

    public function testChangeShipStateExpectNothingWhenStateUnchanged(): void
    {
        $this->ship->shouldReceive('getState')
            ->withNoArgs()
            ->once()
            ->andReturn(ShipStateEnum::SHIP_STATE_NONE);

        $this->subject->changeShipState($this->wrapper, ShipStateEnum::SHIP_STATE_NONE);
    }

    public function testChangeShipStateExpectRepairCanceling(): void
    {
        $this->ship->shouldReceive('getState')
            ->withNoArgs()
            ->andReturn(ShipStateEnum::SHIP_STATE_UNDER_SCRAPPING);
        $this->ship->shouldReceive('isUnderRepair')
            ->withNoArgs()
            ->once()
            ->andReturn(true);

        $this->cancelRepair->shouldReceive('cancelRepair')
            ->with($this->ship)
            ->once();

        $this->ship->shouldReceive('setState')
            ->with(ShipStateEnum::SHIP_STATE_NONE)
            ->once();

        $this->shipRepository->shouldReceive('save')
            ->with($this->ship)
            ->once();

        $this->subject->changeShipState($this->wrapper, ShipStateEnum::SHIP_STATE_NONE);
    }

    public function testChangeShipStateExpectAstroCanceling(): void
    {
        $this->ship->shouldReceive('getState')
            ->withNoArgs()
            ->once()
            ->andReturn(ShipStateEnum::SHIP_STATE_ASTRO_FINALIZING);
        $this->ship->shouldReceive('isUnderRepair')
            ->withNoArgs()
            ->once()
            ->andReturn(false);

        $this->astroEntryLib->shouldReceive('cancelAstroFinalizing')
            ->with($this->wrapper)
            ->once();

        $this->ship->shouldReceive('setState')
            ->with(ShipStateEnum::SHIP_STATE_NONE)
            ->once();

        $this->shipRepository->shouldReceive('save')
            ->with($this->ship)
            ->once();

        $this->subject->changeShipState($this->wrapper, ShipStateEnum::SHIP_STATE_NONE);
    }

    public function testChangeShipStateExpectWebRelease(): void
    {
        $this->ship->shouldReceive('getState')
            ->withNoArgs()
            ->once()
            ->andReturn(ShipStateEnum::SHIP_STATE_WEB_SPINNING);
        $this->ship->shouldReceive('isUnderRepair')
            ->withNoArgs()
            ->once()
            ->andReturn(false);

        $this->tholianWebUtil->shouldReceive('releaseWebHelper')
            ->with($this->wrapper)
            ->once();

        $this->ship->shouldReceive('setState')
            ->with(ShipStateEnum::SHIP_STATE_NONE)
            ->once();

        $this->shipRepository->shouldReceive('save')
            ->with($this->ship)
            ->once();

        $this->subject->changeShipState($this->wrapper, ShipStateEnum::SHIP_STATE_NONE);
    }


    //ALERT STATE

    public function testChangeAlertStateExpectNothingWhenAlertStateUnchanged(): void
    {
        $this->ship->shouldReceive('getAlertState')
            ->withNoArgs()
            ->once()
            ->andReturn(ShipAlertStateEnum::ALERT_GREEN);

        $msg = $this->subject->changeAlertState($this->wrapper, ShipAlertStateEnum::ALERT_GREEN);

        $this->assertNull($msg);
    }

    public function testChangeAlertStateExpectNothingWhenChangedToGreen(): void
    {
        $this->ship->shouldReceive('getAlertState')
            ->withNoArgs()
            ->once()
            ->andReturn(ShipAlertStateEnum::ALERT_YELLOW);
        $this->ship->shouldReceive('setAlertState')
            ->with(ShipAlertStateEnum::ALERT_GREEN)
            ->once();

        $msg = $this->subject->changeAlertState($this->wrapper, ShipAlertStateEnum::ALERT_GREEN);

        $this->assertNull($msg);
    }

    public function testChangeAlertStateExpectErrorWhenNotEnoughEnergyForYellow(): void
    {
        static::expectException(InsufficientEnergyException::class);

        $this->ship->shouldReceive('getAlertState')
            ->withNoArgs()
            ->once()
            ->andReturn(ShipAlertStateEnum::ALERT_GREEN);

        $this->wrapper->shouldReceive('getEpsSystemData')
            ->withNoArgs()
            ->once()
            ->andReturn(null);

        $this->subject->changeAlertState($this->wrapper, ShipAlertStateEnum::ALERT_YELLOW);
    }

    public function testChangeAlertStateExpectChangeToYellow(): void
    {
        $epsSystemData = $this->mock(EpsSystemData::class);

        $epsSystemData->shouldReceive('getEps')
            ->withNoArgs()
            ->once()
            ->andReturn(1);
        $epsSystemData->shouldReceive('lowerEps')
            ->with(1)
            ->once()
            ->andReturnSelf();
        $epsSystemData->shouldReceive('update')
            ->withNoArgs()
            ->once();

        $this->ship->shouldReceive('getAlertState')
            ->withNoArgs()
            ->once()
            ->andReturn(ShipAlertStateEnum::ALERT_GREEN);
        $this->ship->shouldReceive('setAlertState')
            ->with(ShipAlertStateEnum::ALERT_YELLOW)
            ->once();

        $this->wrapper->shouldReceive('getEpsSystemData')
            ->withNoArgs()
            ->once()
            ->andReturn($epsSystemData);

        $this->cancelRepair->shouldReceive('cancelRepair')
            ->with($this->ship)
            ->once()
            ->andReturn(false);

        $msg = $this->subject->changeAlertState($this->wrapper, ShipAlertStateEnum::ALERT_YELLOW);

        $this->assertNull($msg);
    }

    public function testChangeAlertStateExpectErrorWhenNotEnoughEnergyForRed(): void
    {
        static::expectException(InsufficientEnergyException::class);

        $epsSystemData = $this->mock(EpsSystemData::class);
        $epsSystemData->shouldReceive('getEps')
            ->withNoArgs()
            ->once()
            ->andReturn(1);

        $this->ship->shouldReceive('getAlertState')
            ->withNoArgs()
            ->once()
            ->andReturn(ShipAlertStateEnum::ALERT_GREEN);

        $this->wrapper->shouldReceive('getEpsSystemData')
            ->withNoArgs()
            ->once()
            ->andReturn($epsSystemData);

        $this->subject->changeAlertState($this->wrapper, ShipAlertStateEnum::ALERT_RED);
    }

    public function testChangeAlertStateExpectChangeToRed(): void
    {
        $epsSystemData = $this->mock(EpsSystemData::class);

        $epsSystemData->shouldReceive('getEps')
            ->withNoArgs()
            ->once()
            ->andReturn(2);
        $epsSystemData->shouldReceive('lowerEps')
            ->with(2)
            ->once()
            ->andReturnSelf();
        $epsSystemData->shouldReceive('update')
            ->withNoArgs()
            ->once();

        $this->ship->shouldReceive('getAlertState')
            ->withNoArgs()
            ->once()
            ->andReturn(ShipAlertStateEnum::ALERT_GREEN);
        $this->ship->shouldReceive('setAlertState')
            ->with(ShipAlertStateEnum::ALERT_RED)
            ->once();

        $this->wrapper->shouldReceive('getEpsSystemData')
            ->withNoArgs()
            ->once()
            ->andReturn($epsSystemData);

        $this->cancelRepair->shouldReceive('cancelRepair')
            ->with($this->ship)
            ->once()
            ->andReturn(true);

        $msg = $this->subject->changeAlertState($this->wrapper, ShipAlertStateEnum::ALERT_RED);

        $this->assertEquals('Die Reparatur wurde abgebrochen', $msg);
    }
}
