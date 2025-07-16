<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250715172834 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE change_history (id INT AUTO_INCREMENT NOT NULL, entity_type VARCHAR(100) NOT NULL, entity_id VARCHAR(255) NOT NULL, field_name VARCHAR(100) NOT NULL, old_value LONGTEXT DEFAULT NULL, new_value LONGTEXT DEFAULT NULL, action VARCHAR(50) NOT NULL, user_id VARCHAR(100) DEFAULT NULL, user_name VARCHAR(255) DEFAULT NULL, changed_at DATETIME NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent LONGTEXT DEFAULT NULL, metadata JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', reason LONGTEXT DEFAULT NULL, INDEX idx_entity (entity_type, entity_id), INDEX idx_changed_at (changed_at), INDEX idx_user (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('DROP INDEX UNIQ_MONTHLY_PERC_MONTH_COMPONENT ON monthly_percentages');
        $this->addSql('DROP INDEX IDX_MONTHLY_PERC_COMPONENT ON monthly_percentages');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE change_history');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_MONTHLY_PERC_MONTH_COMPONENT ON monthly_percentages (month, component)');
        $this->addSql('CREATE INDEX IDX_MONTHLY_PERC_COMPONENT ON monthly_percentages (component)');
    }
}
