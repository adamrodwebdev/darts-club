<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261010180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Profil : pseudo et avatar';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD display_name VARCHAR(40) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD avatar VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP display_name');
        $this->addSql('ALTER TABLE "user" DROP avatar');
    }
}
