<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261024100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Bilan des soirées : articles de la buvette, autres dépenses et recettes, justificatifs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE event_accounts (id UUID NOT NULL, temporary_bar BOOLEAN NOT NULL, tax_exempt BOOLEAN NOT NULL, closed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, event_id UUID NOT NULL, closed_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8C24777C71F7E88B ON event_accounts (event_id)');
        $this->addSql('CREATE INDEX IDX_8C24777CE1FA7797 ON event_accounts (closed_by_id)');
        $this->addSql('CREATE TABLE event_bar_item (id UUID NOT NULL, label VARCHAR(80) NOT NULL, planned INT NOT NULL, bought INT NOT NULL, returned INT NOT NULL, sold INT NOT NULL, purchase_price INT NOT NULL, sale_price INT NOT NULL, position INT NOT NULL, accounts_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_5E37D8FCCC5E8CE8 ON event_bar_item (accounts_id)');
        $this->addSql('CREATE TABLE event_accounts_entry (id UUID NOT NULL, kind VARCHAR(10) NOT NULL, label VARCHAR(80) NOT NULL, planned INT NOT NULL, actual INT DEFAULT NULL, position INT NOT NULL, accounts_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_51745A04CC5E8CE8 ON event_accounts_entry (accounts_id)');
        $this->addSql('CREATE TABLE event_receipt (id UUID NOT NULL, label VARCHAR(80) NOT NULL, amount INT DEFAULT NULL, credit BOOLEAN NOT NULL, file_id VARCHAR(40) NOT NULL, media_type VARCHAR(30) NOT NULL, uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, accounts_id UUID NOT NULL, uploaded_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_D6CE1FEACC5E8CE8 ON event_receipt (accounts_id)');
        $this->addSql('CREATE INDEX IDX_D6CE1FEAA2B28FE8 ON event_receipt (uploaded_by_id)');
        $this->addSql('ALTER TABLE event_accounts ADD CONSTRAINT FK_8C24777C71F7E88B FOREIGN KEY (event_id) REFERENCES club_event (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE event_accounts ADD CONSTRAINT FK_8C24777CE1FA7797 FOREIGN KEY (closed_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE event_bar_item ADD CONSTRAINT FK_5E37D8FCCC5E8CE8 FOREIGN KEY (accounts_id) REFERENCES event_accounts (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE event_accounts_entry ADD CONSTRAINT FK_51745A04CC5E8CE8 FOREIGN KEY (accounts_id) REFERENCES event_accounts (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE event_receipt ADD CONSTRAINT FK_D6CE1FEACC5E8CE8 FOREIGN KEY (accounts_id) REFERENCES event_accounts (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE event_receipt ADD CONSTRAINT FK_D6CE1FEAA2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE event_receipt');
        $this->addSql('DROP TABLE event_accounts_entry');
        $this->addSql('DROP TABLE event_bar_item');
        $this->addSql('DROP TABLE event_accounts');
    }
}
