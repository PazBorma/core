<?php

declare(strict_types=1);

namespace Stu\Module\Colony\View\ShowModuleFab;

use Override;
use request;
use Stu\Component\Colony\ColonyMenuEnum;
use Stu\Component\Ship\ShipModuleTypeEnum;
use Stu\Component\Building\BuildingEnum;
use Stu\Component\Game\GameEnum;
use Stu\Module\Colony\Lib\ColonyLoaderInterface;
use Stu\Module\Control\GameControllerInterface;
use Stu\Module\Control\ViewControllerInterface;
use Stu\Orm\Repository\BuildingFunctionRepositoryInterface;
use Stu\Orm\Repository\BuildplanModuleRepositoryInterface;
use Stu\Orm\Repository\ModuleRepositoryInterface;
use Stu\Orm\Repository\ModuleBuildingFunctionRepositoryInterface;
use Stu\Orm\Repository\ModuleQueueRepositoryInterface;
use Stu\Orm\Repository\ShipBuildplanRepositoryInterface;
use Stu\Orm\Repository\ShipRumpModuleLevelRepositoryInterface;
use Stu\Orm\Repository\ShipRumpRepositoryInterface;

final class ShowModuleFab implements ViewControllerInterface
{
    public const string VIEW_IDENTIFIER = 'SHOW_MODULEFAB';

    public function __construct(private ColonyLoaderInterface $colonyLoader, private ShowModuleFabRequestInterface $showModuleFabRequest, private ModuleBuildingFunctionRepositoryInterface $moduleBuildingFunctionRepository, private BuildingFunctionRepositoryInterface $buildingFunctionRepository, private ModuleQueueRepositoryInterface $moduleQueueRepository, private ShipRumpRepositoryInterface $shipRumpRepository, private ShipRumpModuleLevelRepositoryInterface $shipRumpModuleLevelRepository, private ShipBuildplanRepositoryInterface $shipBuildplanRepository, private BuildplanModuleRepositoryInterface $buildplanModuleRepository, private ModuleRepositoryInterface $moduleRepository) {}

    #[Override]
    public function handle(GameControllerInterface $game): void
    {
        $userId = $game->getUser()->getId();

        $colony = $this->colonyLoader->loadWithOwnerValidation(
            $this->showModuleFabRequest->getColonyId(),
            $userId,
            false
        );

        $func = $this->buildingFunctionRepository->find(request::getIntFatal('func'));

        if ($func === null) {
            return;
        }

        $modules = $this->moduleBuildingFunctionRepository->getByBuildingFunctionAndUser(
            $func->getFunction(),
            $userId
        );

        $template = match ($func->getFunction()) {
            BuildingEnum::BUILDING_FUNCTION_FABRICATION_HALL => ColonyMenuEnum::MENU_FAB_HALL->getTemplate(),
            BuildingEnum::BUILDING_FUNCTION_TECH_CENTER => ColonyMenuEnum::MENU_TECH_CENTER->getTemplate(),
            default => ColonyMenuEnum::MENU_MODULEFAB->getTemplate(),
        };

        $sortedModules = [];
        foreach ($modules as $module) {
            $moduleType = $module->getModule()->getType()->value;
            $moduleLevel = $module->getModule()->getLevel();
            if (!isset($sortedModules[$moduleType])) {
                $sortedModules[$moduleType] = [];
            }
            if (!isset($sortedModules[$moduleType][$moduleLevel])) {
                $sortedModules[$moduleType][$moduleLevel] = [];
            }
            $sortedModules[$moduleType][$moduleLevel][] = new ModuleFabricationListItem(
                $this->moduleQueueRepository,
                $module->getModule(),
                $colony
            );
        }

        $shipRumps = $this->shipRumpRepository->getBuildableByUser($userId);

        $moduleTypes = [];
        foreach (ShipModuleTypeEnum::cases() as $moduleType) {
            $moduleTypes[$moduleType->value] = [
                'name' => $moduleType->getDescription(),
                'image' => "/assets/buttons/modul_screen_{$moduleType->value}.png"
            ];
        }
        $rumpModules = [];
        $rumpModules[0] = $sortedModules;

        foreach ($shipRumps as $rump) {
            $rumpId = $rump->getId();
            $rumpModules[$rumpId] = [];

            foreach ($sortedModules as $type => $levels) {
                $mod_level = $this->shipRumpModuleLevelRepository->getByShipRump($rumpId);
                $rumpRoleId = $rump->getRoleId();
                $hasMatchingRoleModule = false;

                foreach ($levels as $level => $modules) {
                    foreach ($modules as $module) {
                        if ($module->getModule()->getShipRumpRoleId() === $rumpRoleId) {
                            $hasMatchingRoleModule = true;
                            break 2;
                        }
                    }
                }

                if ($type === ShipModuleTypeEnum::SPECIAL->value) {
                    $addedModules = [];

                    foreach ($levels as $level => $modules) {
                        if (!isset($rumpModules[$rumpId][$type])) {
                            $rumpModules[$rumpId][$type] = [];
                        }

                        if ($rumpRoleId !== null) {
                            $specialModules = $this->moduleRepository->getBySpecialTypeAndRump(
                                $colony,
                                ShipModuleTypeEnum::SPECIAL,
                                $rumpId,
                                $rumpRoleId
                            );

                            foreach ($specialModules as $specialModule) {
                                $moduleName = $specialModule->getName();
                                $matchingModule = null;

                                foreach ($sortedModules[$type][$level] as $sortedModule) {
                                    if ($sortedModule->getModule()->getName() === $moduleName) {
                                        $matchingModule = $sortedModule;
                                        break;
                                    }
                                }

                                if ($matchingModule !== null && !in_array($matchingModule->getModule()->getId(), $addedModules)) {
                                    $rumpModules[$rumpId][$type][$level][] = $matchingModule;
                                    $addedModules[] = $matchingModule->getModule()->getId();
                                }
                            }
                        }
                    }
                    if (empty($rumpModules[$rumpId][$type])) {
                        unset($rumpModules[$rumpId][$type]);
                    }
                } else {
                    $min_level_method = 'getModuleLevel' . $type . 'Min';
                    $max_level_method = 'getModuleLevel' . $type . 'Max';

                    if ($mod_level !== null && method_exists($mod_level, $min_level_method) && method_exists($mod_level, $max_level_method)) {
                        $min_level = $mod_level->$min_level_method();
                        $max_level = $mod_level->$max_level_method();

                        foreach ($levels as $level => $modules) {
                            if ($level >= $min_level && $level <= $max_level) {
                                $filteredModules = [];

                                foreach ($modules as $module) {
                                    if ($hasMatchingRoleModule) {
                                        if ($module->getModule()->getShipRumpRoleId() === $rumpRoleId) {
                                            $filteredModules[] = $module;
                                        }
                                    } else {
                                        if ($module->getModule()->getShipRumpRoleId() === null) {
                                            $filteredModules[] = $module;
                                        }
                                    }
                                }

                                if (!empty($filteredModules)) {
                                    if (!isset($rumpModules[$rumpId][$type])) {
                                        $rumpModules[$rumpId][$type] = [];
                                    }
                                    $rumpModules[$rumpId][$type][$level] = $filteredModules;
                                }
                            }
                        }
                    }
                    if (empty($rumpModules[$rumpId][$type])) {
                        unset($rumpModules[$rumpId][$type]);
                    }
                }
            }
        }

        $buildplans = [];
        $buildplanModules = [];
        foreach ($shipRumps as $rump) {
            $rumpId = $rump->getId();
            $buildplans[$rumpId] = $this->shipBuildplanRepository->getByUserAndRump($userId, $rumpId);

            foreach ($buildplans[$rumpId] as $buildplan) {
                $buildplanId = $buildplan->getId();
                $buildplanModules[$buildplanId] = [];

                foreach ($this->buildplanModuleRepository->getByBuildplan($buildplanId) as $buildplanModule) {
                    $moduleType = $buildplanModule->getModuleType()->value;
                    $moduleLevel = $buildplanModule->getModule()->getLevel();
                    $moduleId = $buildplanModule->getModule()->getId();

                    if (!isset($buildplanModules[$buildplanId][$moduleType])) {
                        $buildplanModules[$buildplanId][$moduleType] = [];
                    }
                    if (!isset($buildplanModules[$buildplanId][$moduleType][$moduleLevel])) {
                        $buildplanModules[$buildplanId][$moduleType][$moduleLevel] = [];
                    }

                    if (isset($sortedModules[$moduleType][$moduleLevel])) {
                        foreach ($sortedModules[$moduleType][$moduleLevel] as $module) {
                            if ($module->getModuleId() === $moduleId) {
                                $buildplanModules[$buildplanId][$moduleType][$moduleLevel][] = $module;
                            }
                        }
                    }
                }
            }
        }


        foreach ($buildplanModules as $buildplanId => $modulesByType) {
            foreach ($modulesByType as $type => $levels) {
                foreach ($levels as $level => $modules) {
                    if ($modules === []) {
                        unset($buildplanModules[$buildplanId][$type][$level]);
                    }
                }
                if (empty($buildplanModules[$buildplanId][$type])) {
                    unset($buildplanModules[$buildplanId][$type]);
                }
            }
            if (empty($buildplanModules[$buildplanId])) {
                unset($buildplanModules[$buildplanId]);
            }
        }

        $combinedModules = [];
        $combinedModules[0] = [
            'no_buildplan' => $sortedModules,
            'buildplans' => []
        ];
        foreach ($shipRumps as $rump) {
            $rumpId = $rump->getId();
            $combinedModules[$rumpId] = [
                'no_buildplan' => $rumpModules[$rumpId] ?? [],
                'buildplans' => []
            ];

            if (isset($buildplans[$rumpId])) {
                foreach ($buildplans[$rumpId] as $buildplan) {
                    $buildplanId = $buildplan->getId();
                    if (isset($buildplanModules[$buildplanId])) {
                        $combinedModules[$rumpId]['buildplans'][$buildplanId] = $buildplanModules[$buildplanId];
                    }
                }
            }
        }


        $game->showMacro($template);
        $game->setTemplateVar('CURRENT_MENU', ColonyMenuEnum::MENU_MODULEFAB);

        $game->setTemplateVar('HOST', $colony);
        $game->setTemplateVar('FUNC', $func);
        $game->setTemplateVar('SORTED_MODULES', $sortedModules);
        $game->setTemplateVar('SHIP_RUMPS', $shipRumps);
        $game->setTemplateVar('MODULE_TYPES', $moduleTypes);
        $game->setTemplateVar('RUMP_MODULES', $rumpModules);
        $game->setTemplateVar('BUILDPLANS', $buildplans);
        $game->setTemplateVar('BUILDPLAN_MODULES', $buildplanModules);
        $game->setTemplateVar('COMBINED_MODULES', $combinedModules);

        $game->addExecuteJS('clearModuleInputs();', GameEnum::JS_EXECUTION_AFTER_RENDER);
    }
}
