<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260124082707 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE withdrawals ADD withdrawal_type VARCHAR(50) DEFAULT NULL, ADD withdrawal_method VARCHAR(50) DEFAULT NULL, ADD fee_paid TINYINT(1) DEFAULT NULL, ADD fee_payment_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD transaction_id VARCHAR(100) DEFAULT NULL, CHANGE phone_number phone_number VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE withdrawals DROP withdrawal_type, DROP withdrawal_method, DROP fee_paid, DROP fee_payment_date, DROP transaction_id, CHANGE phone_number phone_number VARCHAR(20) DEFAULT NULL');
    }
}
