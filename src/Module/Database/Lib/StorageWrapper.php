<?php

namespace Stu\Module\Database\Lib;

use Stu\Orm\Entity\ColonyInterface;
use Stu\Orm\Entity\CommodityInterface;
use Stu\Orm\Entity\ShipInterface;
use Stu\Orm\Entity\SpacecraftInterface;
use Stu\Orm\Entity\TradePostInterface;
use Stu\Orm\Repository\ColonyRepositoryInterface;
use Stu\Orm\Repository\CommodityRepositoryInterface;
use Stu\Orm\Repository\SpacecraftRepositoryInterface;
use Stu\Orm\Repository\TradePostRepositoryInterface;

class StorageWrapper
{
    public function __construct(
        private CommodityRepositoryInterface $commodityRepository,
        private SpacecraftRepositoryInterface $spacecraftRepository,
        private ColonyRepositoryInterface $colonyRepository,
        private TradePostRepositoryInterface $tradePostRepository,
        private int $commodityId,
        private int $amount,
        private ?int $entityId
    ) {}

    public function getCommodityId(): int
    {
        return $this->commodityId;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCommodity(): ?CommodityInterface
    {
        return $this->commodityRepository->find($this->commodityId);
    }

    public function getSpacecraft(): ?SpacecraftInterface
    {
        return $this->spacecraftRepository->find((int) $this->entityId);
    }

    public function getColony(): ?ColonyInterface
    {
        return $this->colonyRepository->find((int) $this->entityId);
    }

    public function getTradepost(): ?TradePostInterface
    {
        return $this->tradePostRepository->find((int) $this->entityId);
    }
}
