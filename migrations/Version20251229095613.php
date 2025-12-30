<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251229095613 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE wallet_transactions (id INT AUTO_INCREMENT NOT NULL, wallet_id INT DEFAULT NULL, transactions_id INT DEFAULT NULL, amount DOUBLE PRECISION DEFAULT NULL, reason VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_A50205E2712520F3 (wallet_id), INDEX IDX_A50205E277E1607F (transactions_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE wallets (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT DEFAULT NULL, balance DOUBLE PRECISION DEFAULT NULL, updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', auto_pay_enabled TINYINT(1) DEFAULT NULL, INDEX IDX_967AAA6CFB88E14F (utilisateur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE wallet_transactions ADD CONSTRAINT FK_A50205E2712520F3 FOREIGN KEY (wallet_id) REFERENCES wallets (id)');
        $this->addSql('ALTER TABLE wallet_transactions ADD CONSTRAINT FK_A50205E277E1607F FOREIGN KEY (transactions_id) REFERENCES transaction (id)');
        $this->addSql('ALTER TABLE wallets ADD CONSTRAINT FK_967AAA6CFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE wallet_transactions DROP FOREIGN KEY FK_A50205E2712520F3');
        $this->addSql('ALTER TABLE wallet_transactions DROP FOREIGN KEY FK_A50205E277E1607F');
        $this->addSql('ALTER TABLE wallets DROP FOREIGN KEY FK_967AAA6CFB88E14F');
        $this->addSql('DROP TABLE wallet_transactions');
        $this->addSql('DROP TABLE wallets');
    }
}
