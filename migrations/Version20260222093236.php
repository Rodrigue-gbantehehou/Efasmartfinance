<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260222093236 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user DROP document_front, DROP selfie, DROP identity_document, DROP verification_statut, DROP verification_submitted_at, DROP verification_rejection_reason');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `user` ADD document_front VARCHAR(255) DEFAULT NULL, ADD selfie VARCHAR(255) DEFAULT NULL, ADD identity_document VARCHAR(255) DEFAULT NULL, ADD verification_statut VARCHAR(20) DEFAULT NULL, ADD verification_submitted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD verification_rejection_reason LONGTEXT DEFAULT NULL');
    }
}
