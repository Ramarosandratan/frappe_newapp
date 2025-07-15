<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250714150425 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create monthly_percentages table for storing monthly salary adjustment percentages';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE monthly_percentages (id INT AUTO_INCREMENT NOT NULL, month INT NOT NULL, percentage NUMERIC(5, 2) NOT NULL, component VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_MONTHLY_PERC_MONTH_COMPONENT ON monthly_percentages (month, component)');
        $this->addSql('CREATE INDEX IDX_MONTHLY_PERC_COMPONENT ON monthly_percentages (component)');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_MONTHLY_PERC_MONTH_COMPONENT ON monthly_percentages');
        $this->addSql('DROP INDEX IDX_MONTHLY_PERC_COMPONENT ON monthly_percentages');
        $this->addSql('DROP TABLE monthly_percentages');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
