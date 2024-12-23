<?php

declare(strict_types=1);

namespace Stu\Module\Game\View\ShowPadd;

use Override;
use Stu\Module\Control\GameControllerInterface;
use Stu\Module\Control\ViewControllerInterface;
use Stu\Component\Faction\FactionEnum;

final class ShowPadd implements ViewControllerInterface
{
    public const string VIEW_IDENTIFIER = 'SHOW_PADD';

    #[Override]
    public function handle(GameControllerInterface $game): void
    {
        if ($game->getUser()->getFactionId() === FactionEnum::FACTION_ROMULAN) {
            $game->setTemplateFile('html/tutorial/padd2.twig');
        } elseif ($game->getUser()->getFactionId() === FactionEnum::FACTION_KLINGON) {
            $game->setTemplateFile('html/tutorial/padd3.twig');
        } elseif ($game->getUser()->getFactionId() === FactionEnum::FACTION_CARDASSIAN) {
            $game->setTemplateFile('html/tutorial/padd4.twig');
        } else {
            $game->setTemplateFile('html/tutorial/padd1.twig');
        }
    }
}
