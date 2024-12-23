<?php

declare(strict_types=1);

namespace Stu\Module\Ship\View\ShowTradeMenu;

use Override;
use request;
use Stu\Exception\AccessViolation;
use Stu\Module\Control\GameControllerInterface;
use Stu\Module\Control\ViewContextTypeEnum;
use Stu\Module\Control\ViewControllerInterface;
use Stu\Module\Ship\Lib\Interaction\InteractionCheckerInterface;
use Stu\Module\Ship\Lib\ShipLoaderInterface;
use Stu\Module\Trade\Lib\TradeLibFactoryInterface;
use Stu\Orm\Entity\TradePostInterface;
use Stu\Orm\Repository\CommodityRepositoryInterface;
use Stu\Orm\Repository\TradeLicenseInfoRepositoryInterface;
use Stu\Orm\Repository\TradeLicenseRepositoryInterface;
use Stu\Orm\Repository\TradePostRepositoryInterface;

final class ShowTradeMenu implements ViewControllerInterface
{
    public const string VIEW_IDENTIFIER = 'SHOW_TRADEMENU';

    public function __construct(private ShipLoaderInterface $shipLoader, private TradeLicenseRepositoryInterface $tradeLicenseRepository, private TradeLicenseInfoRepositoryInterface $TradeLicenseInfoRepository, private TradeLibFactoryInterface $tradeLibFactory, private TradePostRepositoryInterface $tradePostRepository, private CommodityRepositoryInterface $commodityRepository, private InteractionCheckerInterface $interactionChecker)
    {
    }

    #[Override]
    public function handle(GameControllerInterface $game): void
    {
        $userId = $game->getUser()->getId();

        $ship = $this->shipLoader->getByIdAndUser(
            request::indInt('id'),
            $userId,
            false,
            false
        );

        /**
         * @var TradePostInterface $tradepost
         */
        $tradepost = $this->tradePostRepository->find(request::indInt('postid'));
        if ($tradepost === null) {
            return;
        }

        if (!$this->interactionChecker->checkPosition($ship, $tradepost->getShip())) {
            new AccessViolation();
        }

        $game->setPageTitle(_('Handelstransfermenü'));
        if ($game->getViewContext(ViewContextTypeEnum::NO_AJAX) === true) {
            $game->showMacro('html/ship/trademenu.twig');
        } else {
            $game->setMacroInAjaxWindow('html/ship/trademenu.twig');
        }

        $databaseEntryId = $tradepost->getShip()->getDatabaseId();

        if ($databaseEntryId > 0) {
            $game->checkDatabaseItem($databaseEntryId);
        }
        $licenseInfo = $this->TradeLicenseInfoRepository->getLatestLicenseInfo($tradepost->getId());

        if ($licenseInfo !== null) {
            $commodityId = $licenseInfo->getCommodityId();
            $commodityName = $this->commodityRepository->find($commodityId)->getName();
            $licensecost = $licenseInfo->getAmount();
            $licensedays = $licenseInfo->getDays();
        } else {
            $commodityId = 1;
            $commodityName = 'Keine Ware';
            $licensecost = 0;
            $licensedays = 0;
        }

        $game->setTemplateVar('TRADEPOST', $this->tradeLibFactory->createTradeAccountWrapper($tradepost, $userId));
        $game->setTemplateVar('SHIP', $ship);
        $game->setTemplateVar(
            'HAS_LICENSE',
            $this->tradeLicenseRepository->hasLicenseByUserAndTradePost($userId, $tradepost->getId())
        );
        $game->setTemplateVar(
            'CAN_BUY_LICENSE',
            $licenseInfo !== null
        );
        $game->setTemplateVar('LICENSECOMMODITY', $commodityId);
        $game->setTemplateVar('LICENSECOMMODITYNAME', $commodityName);
        $game->setTemplateVar('LICENSECOST', $licensecost);
        $game->setTemplateVar('LICENSEDAYS', $licensedays);
    }
}
