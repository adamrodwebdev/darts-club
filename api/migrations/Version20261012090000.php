<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Préférences d\'affichage du compte : langue et thème';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD language VARCHAR(2) DEFAULT \'fr\' NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD theme VARCHAR(6) DEFAULT \'system\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP theme');
        $this->addSql('ALTER TABLE "user" DROP language');
    }
}
