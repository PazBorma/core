<?php

declare(strict_types=1);

namespace Stu\Module\Spacecraft\Lib\Battle;

use Override;
use Stu\Component\Spacecraft\Repair\CancelRepairInterface;
use Stu\Component\Ship\Retrofit\CancelRetrofitInterface;
use Stu\Component\Spacecraft\System\Exception\SpacecraftSystemException;
use Stu\Component\Spacecraft\System\SpacecraftSystemManagerInterface;
use Stu\Component\Spacecraft\System\SpacecraftSystemTypeEnum;
use Stu\Lib\Information\InformationFactoryInterface;
use Stu\Lib\Information\InformationInterface;
use Stu\Module\Spacecraft\Lib\Battle\Party\BattlePartyFactoryInterface;
use Stu\Module\Ship\Lib\FleetWrapperInterface;
use Stu\Module\Spacecraft\Lib\ShipNfsItem;
use Stu\Module\Spacecraft\Lib\SpacecraftWrapperInterface;
use Stu\Orm\Entity\ShipInterface;
use Stu\Orm\Entity\SpacecraftInterface;
use Stu\Orm\Entity\User;

final class FightLib implements FightLibInterface
{
    public function __construct(
        private SpacecraftSystemManagerInterface $spacecraftSystemManager,
        private  CancelRepairInterface $cancelRepair,
        private CancelRetrofitInterface $cancelRetrofit,
        private AlertLevelBasedReactionInterface $alertLevelBasedReaction,
        private InformationFactoryInterface $informationFactory
    ) {}

    #[Override]
    public function ready(SpacecraftWrapperInterface $wrapper, InformationInterface $informations): void
    {
        $spacecraft = $wrapper->get();

        if (
            $spacecraft->isDestroyed()
            || $spacecraft->getRump()->isEscapePods()
        ) {
            return;
        }
        if ($spacecraft->getBuildplan() === null) {
            return;
        }
        if (!$spacecraft->hasEnoughCrew()) {
            return;
        }

        $informationWrapper = $this->informationFactory->createInformationWrapper();

        if ($spacecraft instanceof ShipInterface && $spacecraft->getDockedTo() !== null) {
            $spacecraft->setDockedTo(null);
            $informationWrapper->addInformation("- Das Schiff hat abgedockt");
        }

        try {
            $this->spacecraftSystemManager->deactivate($wrapper, SpacecraftSystemTypeEnum::SYSTEM_WARPDRIVE);
        } catch (SpacecraftSystemException) {
        }
        try {
            $this->spacecraftSystemManager->deactivate($wrapper, SpacecraftSystemTypeEnum::SYSTEM_CLOAK);
        } catch (SpacecraftSystemException) {
        }

        $this->cancelRepair->cancelRepair($spacecraft);

        if ($spacecraft instanceof ShipInterface) {
            $this->cancelRetrofit->cancelRetrofit($spacecraft);
        }

        $this->alertLevelBasedReaction->react($wrapper, $informationWrapper);

        if (!$informationWrapper->isEmpty()) {
            $informations->addInformationf('Aktionen der %s', $spacecraft->getName());
            $informationWrapper->dumpTo($informations);
        }
    }

    #[Override]
    public function canAttackTarget(
        SpacecraftInterface $spacecraft,
        SpacecraftInterface|ShipNfsItem $target,
        bool $checkCloaked = false,
        bool $checkActiveWeapons = true,
        bool $checkWarped = true
    ): bool {
        if ($checkActiveWeapons && !$spacecraft->hasActiveWeapon()) {
            return false;
        }

        //can't attack itself
        if ($target === $spacecraft) {
            return false;
        }

        //can't attack cloaked target
        if ($checkCloaked && $target->getCloakState()) {
            return false;
        }

        //if tractored, can only attack tractoring ship
        $tractoringShip = $spacecraft instanceof ShipInterface ? $spacecraft->getTractoringSpacecraft() : null;
        if ($tractoringShip !== null) {
            return $target->getId() === $tractoringShip->getId();
        }

        //can't attack target under warp
        if ($checkWarped && $target->isWarped()) {
            return false;
        }

        //can't attack own target under cloak
        if (
            $target->getUserId() === $spacecraft->getUserId()
            && $target->getCloakState()
        ) {
            return false;
        }

        //can't attack same fleet
        $ownFleetId = $spacecraft instanceof ShipInterface ? $spacecraft->getFleetId() : null;
        $targetFleetId = ($target instanceof ShipInterface || $target instanceof ShipNfsItem) ? $target->getFleetId() : null;
        if ($ownFleetId === null || $targetFleetId === null) {
            return true;
        }

        return $ownFleetId !== $targetFleetId;
    }

    #[Override]
    public function getAttackersAndDefenders(
        SpacecraftWrapperInterface|FleetWrapperInterface $wrapper,
        SpacecraftWrapperInterface $targetWrapper,
        BattlePartyFactoryInterface $battlePartyFactory
    ): array {
        $attackers = $battlePartyFactory->createAttackingBattleParty($wrapper);
        $defenders = $battlePartyFactory->createAttackedBattleParty($targetWrapper);

        return [
            $attackers,
            $defenders,
            count($attackers) + count($defenders) > 2
        ];
    }

    #[Override]
    public function isTargetOutsideFinishedTholianWeb(SpacecraftInterface $ship, SpacecraftInterface $target): bool
    {
        $web = $ship->getHoldingWeb();
        if ($web === null) {
            return false;
        }

        return $web->isFinished() && ($target->getHoldingWeb() !== $web);
    }

    public static function isBoardingPossible(SpacecraftInterface|ShipNfsItem $object): bool
    {
        $isTrumfield = $object instanceof ShipNfsItem && $object->isTrumfield();

        return !(User::isUserNpc($object->getUserId())
            || $object->isStation()
            || $isTrumfield
            || $object->getCloakState()
            || $object->getShieldState()
            || $object->isWarped());
    }

    #[Override]
    public function calculateHealthPercentage(ShipInterface $target): int
    {
        $shipCount = 0;
        $healthSum = 0;

        $fleet = $target->getFleet();
        if ($fleet !== null) {
            foreach ($fleet->getShips() as $ship) {
                $shipCount++;
                $healthSum += $ship->getHealthPercentage();
            }
        } else {
            $shipCount++;
            $healthSum += $target->getHealthPercentage();
        }

        return (int)($healthSum / $shipCount);
    }
}