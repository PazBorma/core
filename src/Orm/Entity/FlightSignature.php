<?php

declare(strict_types=1);

namespace Stu\Orm\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Index;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;
use Override;
use Stu\Orm\Repository\FlightSignatureRepository;

#[Table(name: 'stu_flight_sig')]
#[Index(name: 'flight_sig_user_idx', columns: ['user_id'])]
#[Index(name: 'flight_sig_sensor_result_idx', columns: ['from_direction', 'to_direction', 'time'])]
#[Entity(repositoryClass: FlightSignatureRepository::class)]
class FlightSignature implements FlightSignatureInterface
{
    #[Id]
    #[Column(type: 'integer')]
    #[GeneratedValue(strategy: 'IDENTITY')]
    private int $id;

    #[Column(type: 'integer')]
    private int $user_id = 0;

    #[Column(type: 'integer')]
    private int $ship_id = 0;

    #[Column(type: 'integer')]
    private int $rump_id = 0;

    #[Column(type: 'integer')]
    private int $time = 0;

    #[Column(type: 'integer')]
    private int $location_id = 0;

    #[Column(type: 'smallint', length: 1)]
    private int $from_direction = 0;

    #[Column(type: 'smallint', length: 1)]
    private int $to_direction = 0;

    #[Column(type: 'string')]
    private string $ship_name;

    #[Column(type: 'boolean')]
    private bool $is_cloaked = false;

    #[ManyToOne(targetEntity: 'ShipRump')]
    #[JoinColumn(name: 'rump_id', referencedColumnName: 'id')]
    private ShipRumpInterface $rump;

    #[ManyToOne(targetEntity: 'Location')]
    #[JoinColumn(name: 'location_id', referencedColumnName: 'id')]
    private LocationInterface $location;

    #[Override]
    public function getId(): int
    {
        return $this->id;
    }

    #[Override]
    public function setUserId(int $userId): FlightSignatureInterface
    {
        $this->user_id = $userId;
        return $this;
    }

    #[Override]
    public function getShipId(): int
    {
        return $this->ship_id;
    }

    #[Override]
    public function setShipId(int $shipId): FlightSignatureInterface
    {
        $this->ship_id = $shipId;
        return $this;
    }

    #[Override]
    public function getShipName(): string
    {
        return $this->ship_name;
    }

    #[Override]
    public function setShipName(string $name): FlightSignatureInterface
    {
        $this->ship_name = $name;
        return $this;
    }

    #[Override]
    public function isCloaked(): bool
    {
        return $this->is_cloaked;
    }

    #[Override]
    public function setIsCloaked(bool $isCloaked): FlightSignatureInterface
    {
        $this->is_cloaked = $isCloaked;
        return $this;
    }

    #[Override]
    public function getRump(): ShipRumpInterface
    {
        return $this->rump;
    }

    #[Override]
    public function setRump(ShipRumpInterface $shipRump): FlightSignatureInterface
    {
        $this->rump = $shipRump;
        return $this;
    }

    #[Override]
    public function getTime(): int
    {
        return $this->time;
    }
    #[Override]
    public function setTime(int $time): FlightSignatureInterface
    {
        $this->time = $time;
        return $this;
    }

    #[Override]
    public function getLocation(): LocationInterface
    {
        return $this->location;
    }

    #[Override]
    public function setLocation(LocationInterface $location): FlightSignatureInterface
    {
        $this->location = $location;

        return $this;
    }

    #[Override]
    public function getFromDirection(): int
    {
        return $this->from_direction;
    }

    #[Override]
    public function setFromDirection(int $direction): FlightSignatureInterface
    {
        $this->from_direction = $direction;
        return $this;
    }

    #[Override]
    public function getToDirection(): int
    {
        return $this->to_direction;
    }

    #[Override]
    public function setToDirection(int $direction): FlightSignatureInterface
    {
        $this->to_direction = $direction;
        return $this;
    }
}
