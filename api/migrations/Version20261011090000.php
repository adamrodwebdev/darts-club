<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cotisations et paiements';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE membership (id UUID NOT NULL, holder_name VARCHAR(80) NOT NULL, season VARCHAR(9) NOT NULL, amount_due INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_reminder_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, user_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_membership_user_season ON membership (user_id, season)');
        $this->addSql('CREATE INDEX idx_membership_season ON membership (season)');
        $this->addSql('ALTER TABLE membership ADD CONSTRAINT FK_86FFD285A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');

        $this->addSql('CREATE TABLE payment (id UUID NOT NULL, amount INT NOT NULL, method VARCHAR(20) NOT NULL, paid_at DATE NOT NULL, reference VARCHAR(80) DEFAULT NULL, recorded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, cancelled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, cancel_reason VARCHAR(200) DEFAULT NULL, membership_id UUID NOT NULL, recorded_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_6D28840D1FB354CD ON payment (membership_id)');
        $this->addSql('CREATE INDEX IDX_6D28840DD05A957B ON payment (recorded_by_id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D1FB354CD FOREIGN KEY (membership_id) REFERENCES membership (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840DD05A957B FOREIGN KEY (recorded_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE membership');
    }
}
