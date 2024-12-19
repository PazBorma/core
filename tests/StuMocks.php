<?php

declare(strict_types=1);

namespace Stu;

use Stu\Config\Init;
use Stu\Config\StuContainer;
use Stu\Lib\Component\ComponentEnumInterface;
use Stu\Lib\Component\ComponentLoaderInterface;

class StuMocks
{
    private static ?StuMocks $INSTANCE = null;

    private function __construct() {}

    public static function get(): StuMocks
    {
        if (self::$INSTANCE === null) {
            self::$INSTANCE = new StuMocks();
        }

        return self::$INSTANCE;
    }

    public function mockService(string $id, mixed $serviceMock): StuMocks
    {
        $this->getStuContainer()->setAdditionalService($id, $serviceMock);

        return $this;
    }

    public function registerStubbedComponent(ComponentEnumInterface $componentEnum): StuMocks
    {
        $this->getStuContainer()
            ->get(ComponentLoaderInterface::class)
            ->registerStubbedComponent($componentEnum);

        return $this;
    }

    public function reset(): void
    {
        $this->getStuContainer()->clearAdditionalServices();
        $this->getStuContainer()
            ->get(ComponentLoaderInterface::class)
            ->resetStubbedComponents();
    }

    private function getStuContainer(): StuContainer
    {
        return Init::getContainer();
    }
}