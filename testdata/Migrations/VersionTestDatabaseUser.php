<?php

declare(strict_types=1);

namespace Stu\Testdata;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class VersionTestDatabaseUser extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds a database user entry.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('INSERT INTO stu_database_user (database_id,user_id,date) VALUES (6501001,101,1732214228);');
    }
}
