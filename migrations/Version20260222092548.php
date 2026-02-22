<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260222092548 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_verification (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, status VARCHAR(20) NOT NULL, submitted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', document_front VARCHAR(255) DEFAULT NULL, selfie VARCHAR(255) DEFAULT NULL, identity_data LONGTEXT DEFAULT NULL, rejection_reason LONGTEXT DEFAULT NULL, UNIQUE INDEX UNIQ_DA3DB909A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_verification ADD CONSTRAINT FK_DA3DB909A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');

        // Move existing data
        $this->addSql('INSERT INTO user_verification (user_id, status, submitted_at, document_front, selfie, identity_data, rejection_reason) 
            SELECT id, 
                   IFNULL(verification_statut, "pending"), 
                   verification_submitted_at, 
                   document_front, 
                   selfie, 
                   identity_document, 
                   verification_rejection_reason 
            FROM `user` 
            WHERE verification_statut IS NOT NULL OR verification_submitted_at IS NOT NULL OR document_front IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_verification DROP FOREIGN KEY FK_DA3DB909A76ED395');
        $this->addSql('DROP TABLE user_verification');
    }
}
