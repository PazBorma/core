<?php

declare(strict_types=1);

namespace Stu\Component\StarSystem;

use Override;
use RuntimeException;
use Stu\Component\Colony\ColonyCreationInterface;
use Stu\Component\Colony\ColonyTypeEnum;
use Stu\Module\Control\StuRandom;
use Stu\Module\Logging\LoggerUtilFactoryInterface;
use Stu\Module\Logging\LoggerUtilInterface;
use Stu\Orm\Entity\MapFieldTypeInterface;
use Stu\Orm\Entity\MapInterface;
use Stu\Orm\Entity\MassCenterTypeInterface;
use Stu\Orm\Entity\StarSystemInterface;
use Stu\Orm\Entity\StarSystemMapInterface;
use Stu\Orm\Entity\StarSystemTypeInterface;
use Stu\Orm\Repository\MapFieldTypeRepositoryInterface;
use Stu\Orm\Repository\MapRepositoryInterface;
use Stu\Orm\Repository\StarSystemMapRepositoryInterface;
use Stu\Orm\Repository\StarSystemRepositoryInterface;
use Stu\StarsystemGenerator\StarsystemGeneratorInterface;
use Stu\StarsystemGenerator\SystemMapDataInterface;

//TODO unit tests
final class StarSystemCreation implements StarSystemCreationInterface
{
    private LoggerUtilInterface $loggerUtil;

    /** @var array<int, MapFieldTypeInterface> */
    private array $fieldTypeCache = [];

    public function __construct(
        private StarSystemRepositoryInterface $starSystemRepository,
        private MapRepositoryInterface $mapRepository,
        private StarSystemMapRepositoryInterface $starSystemMapRepository,
        private MapFieldTypeRepositoryInterface $mapFieldTypeRepository,
        private StarsystemGeneratorInterface $starsystemGenerator,
        private ColonyCreationInterface $colonyCreation,
        private StuRandom $stuRandom,
        LoggerUtilFactoryInterface $loggerUtilFactory
    ) {
        $this->loggerUtil = $loggerUtilFactory->getLoggerUtil();
    }

    #[Override]
    public function recreateStarSystem(MapInterface $map, string $randomSystemName): ?StarSystemInterface
    {
        //$this->loggerUtil->init('SysGen', LoggerEnum::LEVEL_ERROR);

        $this->loggerUtil->log(sprintf('recreating for map: %s', $map->getSectorString()));

        $systemType = $map->getStarSystemType();

        if ($systemType === null) {
            throw new RuntimeException(sprintf('no system type configured on mapId %d', $map->getId()));
        }

        $this->loggerUtil->log(sprintf('systemType: %d, isGenerateable: %s', $systemType->getId(), $systemType->getIsGenerateable() ? 'true' : 'false'));

        if (
            $systemType->getIsGenerateable() === null
            || $systemType->getIsGenerateable() === false
        ) {
            return null;
        }

        $firstMassCenterType = $systemType->getFirstMassCenterType();
        $secondMassCenterType = $systemType->getSecondMassCenterType();

        if ($firstMassCenterType === null) {
            throw new RuntimeException(sprintf('first mass center is null, systemTypeId %d', $systemType->getId()));
        }

        $systemMapData = $this->starsystemGenerator->generate(
            $systemType->getId(),
            $this->getMassCenterFields($firstMassCenterType),
            $secondMassCenterType === null ? null : $this->getMassCenterFields($secondMassCenterType)
        );

        $starSystem = $this->getStarSystem($map);
        $this->initializeStarSystem($systemType, $starSystem, $systemMapData, $randomSystemName);
        $this->starSystemRepository->save($starSystem);

        return $starSystem;
    }

    private function initializeStarSystem(
        StarSystemTypeInterface $systemType,
        StarSystemInterface $starSystem,
        SystemMapDataInterface $mapData,
        string $randomSystemName
    ): void {
        $starSystem->setType($systemType);
        $starSystem->setName($randomSystemName);
        $starSystem->setMaxX($mapData->getWidth());
        $starSystem->setMaxY($mapData->getHeight());
        $starSystem->setBonusFieldAmount($this->stuRandom->rand(0, 3, true, 2));

        $planetMoonIdentifiers = $this->createSystemMapEntries($starSystem, $mapData);

        $this->createColonies($starSystem, $planetMoonIdentifiers);
    }

    /**
     * @return array<string, string>
     */
    private function createSystemMapEntries(
        StarSystemInterface $starSystem,
        SystemMapDataInterface $mapData
    ): array {
        $fieldData = $mapData->getFieldData();

        $planetMoonIdentifiers = [];

        for ($y = 1; $y <= $mapData->getHeight(); $y++) {
            for ($x = 1; $x <= $mapData->getWidth(); $x++) {
                $index = $x + ($y - 1) * $mapData->getWidth();

                $identifier = $this->createSystemMap(
                    $index,
                    $x,
                    $y,
                    $fieldData[$index],
                    $starSystem,
                    $mapData
                );

                if ($identifier !== null) {
                    $planetMoonIdentifiers[sprintf('%d_%d', $x, $y)] = $identifier;
                }
            }
        }

        return $planetMoonIdentifiers;
    }

    /**
     * @param array<string, string> $planetMoonIdentifiers
     */
    private function createColonies(StarSystemInterface $starSystem, array $planetMoonIdentifiers): void
    {
        /**
         * @var array<StarSystemMapInterface>
         */
        $systemMapsWithoutColony = array_filter(
            $starSystem->getFields()->toArray(),
            fn (StarSystemMapInterface $systemMap): bool => $systemMap->getFieldType()->getColonyClass() !== null
        );

        foreach ($systemMapsWithoutColony as $systemMap) {
            $identifier = $planetMoonIdentifiers[sprintf('%d_%d', $systemMap->getSx(), $systemMap->getSy())];
            $this->colonyCreation->create($systemMap, $identifier);
        }
    }

    private function createSystemMap(
        int $index,
        int $x,
        int $y,
        int $fieldId,
        StarSystemInterface $starSystem,
        SystemMapDataInterface $mapData
    ): ?string {

        $systemMap = $this->starSystemMapRepository->prototype();
        $systemMap->setSx($x);
        $systemMap->setSy($y);
        $systemMap->setSystem($starSystem);
        $systemMap->setFieldType($this->getFieldType($fieldId));

        $this->starSystemMapRepository->save($systemMap);
        $starSystem->getFields()->add($systemMap);

        $colonyClass = $systemMap->getFieldType()->getColonyClass();
        if ($colonyClass !== null) {
            if ($colonyClass->getType() === ColonyTypeEnum::COLONY_TYPE_ASTEROID) {
                $identifer = sprintf('%s %s', $colonyClass->getName(), $starSystem->getName());
            } else {
                $identifer = sprintf('%s %s', $starSystem->getName(), $mapData->getIdentifier($index));
            }

            return $identifer;
        }

        return null;
    }

    private function getFieldType(int $fieldId): MapFieldTypeInterface
    {
        if (!array_key_exists($fieldId, $this->fieldTypeCache)) {
            $fieldType = $this->mapFieldTypeRepository->find($fieldId === 0 ? 1 : $fieldId);

            if ($fieldType === null) {
                throw new RuntimeException(sprintf('fieldId %d does not exist', $fieldId));
            }
            $this->fieldTypeCache[$fieldId] = $fieldType;
        }

        return $this->fieldTypeCache[$fieldId];
    }

    private function getStarSystem(MapInterface $map): StarSystemInterface
    {
        $starSystem = $map->getSystem();
        if ($starSystem === null) {
            $starSystem = $this->starSystemRepository->prototype();
            $map->setSystem($starSystem);
            $this->mapRepository->save($map);
        } else {
            $this->starSystemMapRepository->truncateByStarSystem($starSystem);
            $starSystem->getFields()->clear();
        }

        return $starSystem;
    }

    /**
     * @return array<int, int>
     */
    private function getMassCenterFields(MassCenterTypeInterface $massCenterType): array
    {
        $result = [];

        $firstId = $massCenterType->getFirstFieldType()->getId();

        for ($i = 0; $i < (int)$massCenterType->getSize() ** 2; $i++) {
            $result[] = $firstId + $i;
        }

        return $result;
    }
}
