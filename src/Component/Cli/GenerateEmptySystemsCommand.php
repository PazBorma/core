<?php

declare(strict_types=1);

namespace Stu\Component\Cli;

use Ahc\Cli\Input\Command;
use Stu\Component\StarSystem\GenerateEmptySystemsInterface;

/**
 * Provides cli method for generation of empty star systems
 */
final class GenerateEmptySystemsCommand extends Command
{
    public function __construct(
        private GenerateEmptySystemsInterface $component,
    ) {
        parent::__construct(
            'system:generate',
            'Generates empty systems'
        );

        $this
            ->argument(
                '<layerid>',
                'Id of the map layer'
            )
            ->usage(
                '<bold>  $0 system:generate 1</end> <comment></end> ## Generates star systems for the layer<eol/>'
            );
    }

    public function execute(int $layerid): void
    {
        $io = $this->io();

        $count = $this->component->generate($layerid, null);

        $io->ok(
            sprintf('Es wurden %d Systeme generiert.', $count),
            true
        );
    }
}
