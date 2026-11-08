-- Dump de exemplo MySQL/MariaDB
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS loja;
USE loja;

DROP TABLE IF EXISTS `itens_pedido`;
DROP TABLE IF EXISTS `pedidos`;
DROP TABLE IF EXISTS `produtos`;
DROP TABLE IF EXISTS `usuarios`;

CREATE TABLE `usuarios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `perfil` enum('admin','cliente') NOT NULL DEFAULT 'cliente',
  `criado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuarios_email_unique` (`email`),
  KEY `usuarios_nome_index` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `produtos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `nome` varchar(120) NOT NULL,
  `preco` decimal(10,2) NOT NULL DEFAULT 0.00,
  `estoque` int NOT NULL DEFAULT 0,
  `descricao` text NULL,
  UNIQUE (`nome`)
) ENGINE=InnoDB;

CREATE TABLE `pedidos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint unsigned NOT NULL,
  `total` decimal(12,2) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'aberto',
  PRIMARY KEY (`id`),
  KEY `pedidos_usuario_id_foreign` (`usuario_id`),
  CONSTRAINT `pedidos_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE `itens_pedido` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pedido_id` bigint unsigned NOT NULL,
  `produto_id` bigint unsigned NOT NULL,
  `quantidade` int unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  CONSTRAINT `itens_pedido_pedido_id_foreign` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `itens_pedido_produto_id_foreign` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`)
) ENGINE=InnoDB;

CREATE VIEW `vw_pedidos` AS SELECT id, total FROM pedidos;

ALTER TABLE `produtos` ADD COLUMN `sku` varchar(50) NULL;

INSERT INTO `usuarios` (`nome`, `email`, `senha`) VALUES ('Ana', 'ana@teste.com', 'hash');
