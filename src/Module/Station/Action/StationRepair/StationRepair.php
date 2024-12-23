<?php

declare(strict_types=1);

namespace Stu\Module\Station\Action\StationRepair;

use Override;
use request;
use Stu\Component\Ship\Repair\RepairUtilInterface;
use Stu\Component\Ship\ShipStateEnum;
use Stu\Module\Control\ActionControllerInterface;
use Stu\Module\Control\GameControllerInterface;
use Stu\Module\Ship\Lib\ShipLoaderInterface;
use Stu\Module\Ship\View\ShowShip\ShowShip;
use Stu\Orm\Repository\ShipRepositoryInterface;

final class StationRepair implements ActionControllerInterface
{
    public const string ACTION_IDENTIFIER = 'B_STATION_REPAIR';

    public function __construct(
        private ShipLoaderInterface $shipLoader,
        private ShipRepositoryInterface $shipRepository,
        private RepairUtilInterface $repairUtil
    ) {}

    #[Override]
    public function handle(GameControllerInterface $game): void
    {
        $game->setView(ShowShip::VIEW_IDENTIFIER);

        $userId = $game->getUser()->getId();

        $wrapper = $this->shipLoader->getWrapperByIdAndUser(
            request::postIntFatal('id'),
            $userId
        );

        $station = $wrapper->get();
        if (!$station->hasEnoughCrew($game)) {
            return;
        }

        if (!$station->isAlertGreen()) {
            return;
        }

        if ($station->isUnderRepair()) {
            $game->addInformation(_('Die Station wird bereits repariert.'));
            return;
        }

        $station->setState(ShipStateEnum::SHIP_STATE_REPAIR_PASSIVE);

        $this->shipRepository->save($station);

        $duration = $this->repairUtil->getRepairDuration($wrapper);

        $game->addInformationf(
            'Die Station wird repariert. Fertigstellung in %s Ticks.',
            $duration
        );
    }

    #[Override]
    public function performSessionCheck(): bool
    {
        return true;
    }
}
