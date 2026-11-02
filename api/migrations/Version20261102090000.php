<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Commandes du bar et de la cuisine : numéro par soirée, articles et garnitures épuisés';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_accounts ADD last_order_number INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE event_accounts ALTER last_order_number DROP DEFAULT');
        $this->addSql('ALTER TABLE event_bar_item ADD sold_out BOOLEAN DEFAULT false NOT NULL');
        $this->addSql("ALTER TABLE event_bar_item ADD garnishes_out JSON DEFAULT '[]' NOT NULL");
        $this->addSql('ALTER TABLE event_bar_item ALTER sold_out DROP DEFAULT');
        $this->addSql('ALTER TABLE event_bar_item ALTER garnishes_out DROP DEFAULT');

        $this->addSql('CREATE TABLE evening_order (id UUID NOT NULL, number INT NOT NULL, note VARCHAR(40) DEFAULT NULL, lines JSON NOT NULL, total INT NOT NULL, kitchen_status VARCHAR(12) DEFAULT NULL, taken_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ready_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, cancelled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, taken_by_id UUID DEFAULT NULL, delivered_by_id UUID DEFAULT NULL, event_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_8C112CD817F014F6 ON evening_order (taken_by_id)');
        $this->addSql('CREATE INDEX IDX_8C112CD8BFBEE0DA ON evening_order (delivered_by_id)');
        $this->addSql('CREATE INDEX idx_evening_order_taken_at ON evening_order (event_id, taken_at)');
        $this->addSql('CREATE UNIQUE INDEX uniq_evening_order_number ON evening_order (event_id, number)');
        $this->addSql('ALTER TABLE evening_order ADD CONSTRAINT FK_8C112CD817F014F6 FOREIGN KEY (taken_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE evening_order ADD CONSTRAINT FK_8C112CD8BFBEE0DA FOREIGN KEY (delivered_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE evening_order ADD CONSTRAINT FK_8C112CD871F7E88B FOREIGN KEY (event_id) REFERENCES club_event (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE evening_order');
        $this->addSql('ALTER TABLE event_bar_item DROP sold_out');
        $this->addSql('ALTER TABLE event_bar_item DROP garnishes_out');
        $this->addSql('ALTER TABLE event_accounts DROP last_order_number');
    }
}
