<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use PazBorma\StuniverseUtility\Framework;
use Psr\Container\ContainerInterface;
use Stu\Component\Game\ModuleEnum;
use Stu\Config\Init;
use Stu\Module\Control\GameControllerInterface;

/**
 * @deprecated Session handling should be part of the application
 */
@session_start();

require_once __DIR__ . '/../../vendor/autoload.php';

$slugs = explode("/", trim(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH), '/'));

if (strlen($slugs[0]) && $slugs[0] === 'app') {
    Framework::run();
}

Init::run(function (ContainerInterface $dic): void {
    $em = $dic->get(EntityManagerInterface::class);
    $em->beginTransaction();

    $dic->get(GameControllerInterface::class)->main(
        ModuleEnum::INDEX,
        false
    );

    $em->commit();
});
