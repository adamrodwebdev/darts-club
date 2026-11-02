<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Carte de la soirée (formats, contenants, garnitures), encaissement par jetons et équipes de la soirée';
    }

    public function up(Schema $schema): void
    {
        // Articles du bilan devenus la carte : les anciens restent « à l'unité », un seul format « Unité »
        // à leur prix de vente, mêmes quantités, même recette
        $this->addSql("ALTER TABLE event_bar_item ADD station VARCHAR(10) DEFAULT 'bar' NOT NULL");
        $this->addSql("ALTER TABLE event_bar_item ADD stock_unit VARCHAR(10) DEFAULT 'unite' NOT NULL");
        $this->addSql("ALTER TABLE event_bar_item ADD garnishes JSON DEFAULT '[]' NOT NULL");
        $this->addSql('ALTER TABLE event_bar_item ADD alcoholic BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE event_bar_item ADD container_name VARCHAR(40) DEFAULT NULL');
        $this->addSql('ALTER TABLE event_bar_item ADD container_size INT DEFAULT 1 NOT NULL');
        $this->addSql("ALTER TABLE event_bar_item ADD formats JSON DEFAULT '[]' NOT NULL");
        $this->addSql("UPDATE event_bar_item SET formats = json_build_array(json_build_object('name', 'Unité', 'volume', 1, 'price', sale_price, 'sold', sold))");
        $this->addSql('ALTER TABLE event_bar_item DROP sale_price');
        $this->addSql('ALTER TABLE event_bar_item DROP sold');
        foreach (['station', 'stock_unit', 'garnishes', 'alcoholic', 'container_size', 'formats'] as $column) {
            $this->addSql("ALTER TABLE event_bar_item ALTER {$column} DROP DEFAULT");
        }

        // Encaissement : les bilans existants (avant les jetons) gardent un encaissement direct
        $this->addSql('ALTER TABLE event_accounts ADD tokens_enabled BOOLEAN DEFAULT false NOT NULL');
        $this->addSql("ALTER TABLE event_accounts ADD tokens JSON DEFAULT '[]' NOT NULL");
        $this->addSql('ALTER TABLE event_accounts ADD cash_float INT DEFAULT 0 NOT NULL');
        foreach (['tokens_enabled', 'tokens', 'cash_float'] as $column) {
            $this->addSql("ALTER TABLE event_accounts ALTER {$column} DROP DEFAULT");
        }

        $this->addSql('CREATE TABLE evening_team_member (id UUID NOT NULL, team VARCHAR(10) NOT NULL, assigned_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, event_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_F58FAB99A76ED395 ON evening_team_member (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_evening_team_member ON evening_team_member (event_id, user_id, team)');
        $this->addSql('ALTER TABLE evening_team_member ADD CONSTRAINT FK_F58FAB99A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE evening_team_member ADD CONSTRAINT FK_F58FAB9971F7E88B FOREIGN KEY (event_id) REFERENCES club_event (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE evening_team_member');
        $this->addSql('ALTER TABLE event_accounts DROP tokens_enabled');
        $this->addSql('ALTER TABLE event_accounts DROP tokens');
        $this->addSql('ALTER TABLE event_accounts DROP cash_float');
        $this->addSql('ALTER TABLE event_bar_item ADD sale_price INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE event_bar_item ADD sold INT DEFAULT 0 NOT NULL');
        $this->addSql("UPDATE event_bar_item SET sale_price = COALESCE((formats->0->>'price')::int, 0), sold = COALESCE((formats->0->>'sold')::int, 0)");
        foreach (['station', 'stock_unit', 'garnishes', 'alcoholic', 'container_name', 'container_size', 'formats'] as $column) {
            $this->addSql("ALTER TABLE event_bar_item DROP {$column}");
        }
    }
}
