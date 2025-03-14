<?php

declare(strict_types=1);

namespace Stu\Module\Admin\View\Map\Noop;

use Override;
use Stu\Module\Control\GameControllerInterface;
use Stu\Module\Control\ViewControllerInterface;

final class Noop implements ViewControllerInterface
{
	public const string VIEW_IDENTIFIER = 'NOOP';

	#[Override]
	public function handle(GameControllerInterface $game): void
	{
		$game->showMacro('html/empty.twig');
	}
}