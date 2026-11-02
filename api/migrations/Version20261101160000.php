<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ventes de jetons et de sachets à l\'accueil des soirées (annulées gardées, jamais effacées)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE token_sale (id UUID NOT NULL, lines JSON NOT NULL, total INT NOT NULL, method VARCHAR(10) NOT NULL, cancelled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, sold_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, cancelled_by_id UUID DEFAULT NULL, event_id UUID NOT NULL, sold_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_874FF567187B2D12 ON token_sale (cancelled_by_id)');
        $this->addSql('CREATE INDEX IDX_874FF567148EA8A1 ON token_sale (sold_by_id)');
        $this->addSql('CREATE INDEX idx_token_sale_event_sold_at ON token_sale (event_id, sold_at)');
        $this->addSql('ALTER TABLE token_sale ADD CONSTRAINT FK_874FF567187B2D12 FOREIGN KEY (cancelled_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE token_sale ADD CONSTRAINT FK_874FF56771F7E88B FOREIGN KEY (event_id) REFERENCES club_event (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE token_sale ADD CONSTRAINT FK_874FF567148EA8A1 FOREIGN KEY (sold_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE token_sale');
    }
}
