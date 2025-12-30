<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251229000737 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tontine CHANGE amount_per_point amount_per_point INT DEFAULT NULL, CHANGE total_points total_points INT DEFAULT NULL, CHANGE total_pay total_pay INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tontine CHANGE amount_per_point amount_per_point VARCHAR(255) DEFAULT NULL, CHANGE total_points total_points VARCHAR(255) DEFAULT NULL, CHANGE total_pay total_pay VARCHAR(255) DEFAULT NULL');
    }
}
