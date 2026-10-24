<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Validation des nouveaux membres par le bureau ; les comptes déjà confirmés sont validés d\'office';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD approved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('UPDATE "user" SET approved_at = created_at WHERE verified = true');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP approved_at');
    }
}
