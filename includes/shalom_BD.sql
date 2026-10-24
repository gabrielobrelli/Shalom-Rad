-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql306.infinityfree.com
-- Tempo de geração: 23/10/2026 às 20:38
-- Versão do servidor: 11.4.13-MariaDB
-- Versão do PHP: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `if0_42757432_shalom_BD`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `admins`
--

CREATE TABLE `admins` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(60) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `admins`
--

INSERT INTO `admins` (`id`, `username`, `password_hash`, `criado_em`) VALUES
(1, 'gabrielobrelli', '$2b$12$Ea8VXeHqJnzaAwX33FR3RO0fBtJ2wRoQ45kkYWOjgfbR0HzVaA0gq', '2026-08-26 15:46:02'),
(2, 'shalomadm', '$2b$12$9.H0Vy0ESogK84VTEmSsaeEQNLkFrysa3AidPSG1xHy/oQ9bbND3G', '2026-08-26 15:46:02');

-- --------------------------------------------------------

--
-- Estrutura para tabela `estoque_categorias`
--

CREATE TABLE `estoque_categorias` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `estoque_categorias`
--

INSERT INTO `estoque_categorias` (`id`, `nome`) VALUES
(5, 'Caixa Inferior'),
(4, 'Caixa Superior'),
(1, 'Mangueiras'),
(3, 'Reservatório'),
(2, 'Tampas');

-- --------------------------------------------------------

--
-- Estrutura para tabela `estoque_itens`
--

CREATE TABLE `estoque_itens` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(150) NOT NULL,
  `marca` varchar(100) DEFAULT NULL,
  `categoria_id` int(10) UNSIGNED DEFAULT NULL,
  `quantidade` int(11) NOT NULL DEFAULT 0,
  `valor_unitario` decimal(10,2) NOT NULL DEFAULT 0.00,
  `numero_serie` varchar(20) DEFAULT NULL,
  `imagem_arquivo` varchar(255) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `estoque_itens`
--

INSERT INTO `estoque_itens` (`id`, `nome`, `marca`, `categoria_id`, `quantidade`, `valor_unitario`, `numero_serie`, `imagem_arquivo`, `criado_em`, `atualizado_em`) VALUES
(2, 'Mangueira Superior Corsa', 'Boshe', 1, 6, '35.00', '01010', '29051da7b5963530c863e413.png', '2026-09-08 07:05:16', '2026-09-08 17:51:24');

-- --------------------------------------------------------

--
-- Estrutura para tabela `estoque_movimentacoes`
--

CREATE TABLE `estoque_movimentacoes` (
  `id` int(10) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `tipo` enum('cadastro','entrada','saida') NOT NULL,
  `quantidade` int(11) NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `estoque_movimentacoes`
--

INSERT INTO `estoque_movimentacoes` (`id`, `item_id`, `tipo`, `quantidade`, `criado_em`) VALUES
(3, 2, 'cadastro', 6, '2026-09-08 07:05:16');

-- --------------------------------------------------------

--
-- Estrutura para tabela `lembretes`
--

CREATE TABLE `lembretes` (
  `id` int(10) UNSIGNED NOT NULL,
  `titulo` varchar(150) DEFAULT NULL,
  `texto` text NOT NULL,
  `urgencia` enum('normal','importante','urgente') NOT NULL DEFAULT 'normal',
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `orders`
--

CREATE TABLE `orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `token` char(48) NOT NULL,
  `cliente_nome` varchar(150) NOT NULL,
  `status` enum('novo','em_andamento','parcial','concluido') NOT NULL DEFAULT 'novo',
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `orders`
--

INSERT INTO `orders` (`id`, `token`, `cliente_nome`, `status`, `criado_em`, `atualizado_em`) VALUES
(4, '0255569f69056a4e783b3903643463828597644371dad807', 'Digão', 'concluido', '2026-08-27 09:43:24', '2026-09-18 11:56:54'),
(8, '61c36c3079bd9b8333d8f8292c46e200375e48e9971b75bc', 'Geraldo', 'novo', '2026-09-28 10:00:08', '2026-09-28 10:00:08');

-- --------------------------------------------------------

--
-- Estrutura para tabela `order_items`
--

CREATE TABLE `order_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `modelo_nome` varchar(150) NOT NULL,
  `numero_serie` varchar(100) DEFAULT NULL,
  `quantidade` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `situacao` enum('normal','importante','urgente') NOT NULL DEFAULT 'normal',
  `quantidade_feita` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `observacao_adm` text DEFAULT NULL,
  `feito` tinyint(1) NOT NULL DEFAULT 0,
  `profissional_id` int(10) UNSIGNED DEFAULT NULL,
  `feito_em` datetime DEFAULT NULL,
  `carcaca_utilizada` varchar(255) DEFAULT NULL,
  `caixas_utilizadas` varchar(255) DEFAULT NULL,
  `aneis` varchar(255) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `modelo_nome`, `numero_serie`, `quantidade`, `situacao`, `quantidade_feita`, `observacao_adm`, `feito`, `profissional_id`, `feito_em`, `carcaca_utilizada`, `caixas_utilizadas`, `aneis`, `criado_em`, `atualizado_em`) VALUES
(6, 4, 'Astra', NULL, 1, 'normal', 1, NULL, 1, 2, '2026-08-28 13:37:03', NULL, NULL, NULL, '2026-08-27 09:43:24', '2026-08-28 13:37:03'),
(7, 4, 'Celta s/ar', NULL, 1, 'normal', 1, NULL, 1, 2, '2026-09-02 09:33:05', NULL, NULL, NULL, '2026-08-27 09:43:24', '2026-09-02 09:33:05'),
(8, 4, 'Classic 2010', NULL, 1, 'normal', 1, NULL, 1, 2, '2026-08-28 02:23:25', NULL, NULL, NULL, '2026-08-27 09:43:24', '2026-09-18 11:58:33'),
(9, 4, 'Corsa s/ar', NULL, 2, 'normal', 2, NULL, 1, 2, '2026-09-02 09:33:13', NULL, NULL, NULL, '2026-08-27 09:43:24', '2026-09-02 09:33:13'),
(10, 4, 'Corsa c/ar', NULL, 1, 'normal', 1, NULL, 1, 2, '2026-09-18 11:58:04', NULL, NULL, NULL, '2026-08-27 09:43:24', '2026-09-18 11:58:04'),
(11, 4, 'Montana', NULL, 1, 'normal', 1, NULL, 1, 2, '2026-08-28 13:37:30', NULL, NULL, NULL, '2026-08-27 09:43:24', '2026-08-28 13:37:30'),
(12, 4, 'Escort 89', NULL, 1, 'normal', 1, NULL, 1, 2, '2026-09-03 09:05:56', NULL, NULL, NULL, '2026-08-27 09:43:24', '2026-09-03 09:05:56'),
(13, 4, 'Fiesta Endura', NULL, 1, 'normal', 1, NULL, 1, 5, '2026-08-28 02:23:48', NULL, NULL, NULL, '2026-08-27 09:43:24', '2026-08-28 02:23:48'),
(14, 4, 'Gol AP', NULL, 2, 'normal', 2, NULL, 1, NULL, '2026-09-12 14:07:05', NULL, NULL, NULL, '2026-08-27 09:43:24', '2026-09-12 14:07:05'),
(15, 4, 'Gol AT', NULL, 4, 'normal', 4, NULL, 1, NULL, '2026-09-18 11:56:09', NULL, NULL, NULL, '2026-08-27 09:43:24', '2026-09-18 11:56:09'),
(16, 4, 'G5 s/ar', NULL, 2, 'normal', 2, NULL, 1, NULL, '2026-09-18 11:56:24', NULL, NULL, NULL, '2026-08-27 09:43:24', '2026-09-18 11:56:24'),
(17, 4, 'G5 c/ar', NULL, 2, 'normal', 2, NULL, 1, NULL, '2026-09-12 14:07:16', NULL, NULL, NULL, '2026-08-27 09:43:24', '2026-09-12 14:07:16'),
(18, 4, 'Gol quadrado', NULL, 2, 'normal', 2, NULL, 1, NULL, '2026-09-12 14:07:21', NULL, NULL, NULL, '2026-08-27 09:43:24', '2026-09-12 14:07:21'),
(19, 4, 'Ka 98', NULL, 1, 'normal', 1, NULL, 1, 2, '2026-09-03 09:06:24', NULL, NULL, NULL, '2026-08-27 09:43:24', '2026-09-03 09:06:24'),
(20, 4, 'Monza', NULL, 1, 'normal', 1, NULL, 1, 4, '2026-09-03 09:06:31', NULL, NULL, NULL, '2026-08-27 09:43:24', '2026-09-03 09:06:31'),
(21, 4, 'Palio 98 1.5', NULL, 1, 'normal', 1, NULL, 1, 2, '2026-09-08 17:47:18', NULL, NULL, NULL, '2026-08-27 09:43:24', '2026-09-08 17:47:18'),
(22, 4, 'Palio Castelo', NULL, 1, 'normal', 1, NULL, 1, 2, '2026-08-28 02:24:21', NULL, NULL, NULL, '2026-08-27 09:43:24', '2026-08-28 02:24:21'),
(23, 4, 'Palio Valeo', NULL, 1, 'normal', 1, NULL, 1, NULL, '2026-09-12 14:07:26', NULL, NULL, NULL, '2026-08-27 09:43:24', '2026-09-12 14:07:26'),
(24, 4, 'Pampa 1.8', NULL, 1, 'normal', 1, NULL, 1, 4, '2026-09-08 17:47:11', NULL, NULL, NULL, '2026-08-27 09:43:24', '2026-09-08 17:47:11'),
(25, 4, 'Uno Medio', NULL, 2, 'normal', 2, NULL, 1, NULL, '2026-09-18 11:56:54', NULL, NULL, NULL, '2026-08-27 09:43:24', '2026-09-18 11:56:54'),
(26, 4, 'Vectra 98', NULL, 1, 'normal', 1, NULL, 1, 2, '2026-09-03 09:06:46', NULL, NULL, NULL, '2026-08-27 09:43:24', '2026-09-03 09:06:46'),
(27, 4, 'Fiesta novo', NULL, 1, 'normal', 1, NULL, 1, 2, '2026-09-03 09:07:00', NULL, NULL, NULL, '2026-08-27 09:43:24', '2026-09-03 09:07:00'),
(33, 8, 'Agile', NULL, 1, 'normal', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-09-28 10:00:08', '2026-09-28 10:00:08'),
(34, 8, 'Astra Encaixe', NULL, 1, 'normal', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-09-28 10:00:08', '2026-09-28 10:00:08'),
(35, 8, 'Astra Parafuso', NULL, 1, 'normal', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-09-28 10:00:08', '2026-09-28 10:00:08'),
(36, 8, 'Passat', NULL, 2, 'normal', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-09-28 10:00:08', '2026-09-28 10:00:08'),
(37, 8, 'Gol 87', NULL, 1, 'normal', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-09-28 10:00:08', '2026-09-28 10:00:08'),
(38, 8, 'G5 c/ar', NULL, 3, 'normal', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-09-28 10:00:08', '2026-09-28 10:00:08'),
(39, 8, 'G5 s/ar', NULL, 1, 'normal', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-09-28 10:00:08', '2026-09-28 10:00:08'),
(40, 8, 'Focus antigo', NULL, 1, 'normal', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-09-28 10:00:08', '2026-09-28 10:00:08'),
(41, 8, 'Focus 2019 Aut.', NULL, 1, 'normal', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-09-28 10:00:08', '2026-09-28 10:00:08'),
(42, 8, 'Ka curto bico', NULL, 2, 'normal', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-09-28 10:00:08', '2026-09-28 10:00:08'),
(43, 8, 'Escort 89', NULL, 1, 'normal', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-09-28 10:00:08', '2026-09-28 10:00:08'),
(44, 8, 'Corolla 2010 Aut.', NULL, 1, 'normal', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-09-28 10:00:08', '2026-09-28 10:00:08'),
(45, 8, 'Peugeot universal', NULL, 1, 'normal', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-09-28 10:00:08', '2026-09-28 10:00:08'),
(46, 8, 'Palio 1.0', NULL, 1, 'normal', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-09-28 10:00:08', '2026-09-28 10:00:08'),
(47, 8, 'Uno curto', NULL, 1, 'normal', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-09-28 10:00:08', '2026-09-28 10:00:08'),
(48, 8, 'Uno grande', NULL, 1, 'normal', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-09-28 10:00:08', '2026-09-28 10:00:08'),
(49, 8, 'Corsa c/ar', NULL, 1, 'normal', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-09-28 10:00:08', '2026-09-28 10:00:08'),
(50, 8, 'Monza antigo', NULL, 2, 'normal', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-09-28 10:00:08', '2026-09-28 10:00:08'),
(51, 8, 'Kadet', NULL, 2, 'normal', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-09-28 10:00:08', '2026-09-28 10:00:08'),
(52, 8, 'S-10 flex', NULL, 1, 'normal', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-09-28 10:00:08', '2026-09-28 10:00:08'),
(53, 8, 'Meriva', NULL, 1, 'normal', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-09-28 10:00:08', '2026-09-28 10:00:08'),
(54, 8, 'S-10 Gasolina', NULL, 1, 'normal', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-09-28 10:00:08', '2026-09-28 10:00:08'),
(55, 8, 'Kwid', NULL, 1, 'normal', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-09-28 10:00:08', '2026-09-28 10:00:08');

-- --------------------------------------------------------

--
-- Estrutura para tabela `order_item_profissionais`
--

CREATE TABLE `order_item_profissionais` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_item_id` int(10) UNSIGNED NOT NULL,
  `profissional_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `order_item_profissionais`
--

INSERT INTO `order_item_profissionais` (`id`, `order_item_id`, `profissional_id`) VALUES
(1, 6, 2),
(2, 7, 2),
(3, 8, 2),
(4, 9, 2),
(6, 11, 2),
(7, 12, 2),
(15, 13, 5),
(16, 14, 2),
(21, 15, 2),
(20, 15, 4),
(23, 16, 2),
(22, 16, 4),
(17, 17, 4),
(18, 18, 2),
(8, 19, 2),
(13, 20, 4),
(9, 21, 2),
(10, 22, 2),
(19, 23, 2),
(14, 24, 4),
(24, 25, 4),
(11, 26, 2),
(12, 27, 2);

-- --------------------------------------------------------

--
-- Estrutura para tabela `profissionais`
--

CREATE TABLE `profissionais` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(120) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `profissionais`
--

INSERT INTO `profissionais` (`id`, `nome`, `ativo`, `criado_em`) VALUES
(1, 'João', 1, '2026-08-27 22:21:12'),
(2, 'Gabriel', 1, '2026-08-27 22:21:16'),
(3, 'Leila', 1, '2026-08-27 22:21:20'),
(4, 'Cristiano', 1, '2026-08-27 22:21:25'),
(5, 'Murilo', 1, '2026-08-27 22:21:29');

-- --------------------------------------------------------

--
-- Estrutura para tabela `seminovos_items`
--

CREATE TABLE `seminovos_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `seminovos_order_id` int(10) UNSIGNED NOT NULL,
  `modelo_nome` varchar(150) NOT NULL,
  `quantidade_feita` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `nossas_pecas_usadas` text DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `seminovos_items`
--

INSERT INTO `seminovos_items` (`id`, `seminovos_order_id`, `modelo_nome`, `quantidade_feita`, `nossas_pecas_usadas`, `criado_em`, `atualizado_em`) VALUES
(1, 1, 'G5 C/AR', 14, 'caixa superior; caixa baixarorioir', '2026-08-26 16:47:14', '2026-08-27 07:39:20');

-- --------------------------------------------------------

--
-- Estrutura para tabela `seminovos_orders`
--

CREATE TABLE `seminovos_orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `titulo` varchar(150) NOT NULL DEFAULT 'Concerto de Seminovos Marcio',
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `seminovos_orders`
--

INSERT INTO `seminovos_orders` (`id`, `titulo`, `criado_em`, `atualizado_em`) VALUES
(1, 'Concerto de Seminovos Marcio', '2026-08-26 15:26:03', '2026-08-26 15:26:03');

-- --------------------------------------------------------

--
-- Estrutura para tabela `servicos`
--

CREATE TABLE `servicos` (
  `id` int(10) UNSIGNED NOT NULL,
  `data_servico` date NOT NULL,
  `carro_cliente` varchar(150) NOT NULL,
  `descricao` text DEFAULT NULL,
  `observacao_adm` text DEFAULT NULL,
  `profissional_id` int(10) UNSIGNED DEFAULT NULL,
  `valor_cobrado` decimal(10,2) NOT NULL DEFAULT 0.00,
  `desconto` decimal(10,2) NOT NULL DEFAULT 0.00,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `servicos`
--

INSERT INTO `servicos` (`id`, `data_servico`, `carro_cliente`, `descricao`, `observacao_adm`, `profissional_id`, `valor_cobrado`, `desconto`, `criado_em`) VALUES
(1, '2026-08-28', 'Onix - Leo', 'Limpeza de sistema primeira etapa', NULL, 1, '220.00', '20.00', '2026-08-27 22:22:55'),
(2, '2026-08-28', 'Leo', 'VENDA DE AGUA E PRODUTO', NULL, 3, '120.00', '20.00', '2026-08-28 02:38:35'),
(3, '2026-08-28', 'Uno', 'Limpeza', NULL, 1, '110.00', '10.00', '2026-08-28 13:38:17'),
(4, '2026-09-08', 'onix leo', 'troca de liquufiop', NULL, 1, '220.00', '10.00', '2026-09-08 14:58:59'),
(5, '2026-09-12', 'onix leo', 'lavagem', 'retorno da lavagem pois a agua continuou suja', NULL, '110.00', '10.00', '2026-09-12 14:05:03');

-- --------------------------------------------------------

--
-- Estrutura para tabela `servico_pagamentos`
--

CREATE TABLE `servico_pagamentos` (
  `id` int(10) UNSIGNED NOT NULL,
  `servico_id` int(10) UNSIGNED NOT NULL,
  `forma` enum('pix','dinheiro','debito','credito','outro') NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `parcelas` tinyint(3) UNSIGNED DEFAULT NULL,
  `conta` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `servico_pagamentos`
--

INSERT INTO `servico_pagamentos` (`id`, `servico_id`, `forma`, `valor`, `parcelas`, `conta`) VALUES
(2, 1, 'pix', '200.00', NULL, NULL),
(4, 2, 'dinheiro', '100.00', NULL, NULL),
(5, 3, 'pix', '50.00', NULL, NULL),
(6, 3, 'dinheiro', '50.00', NULL, NULL),
(7, 4, 'pix', '210.00', NULL, NULL),
(10, 5, 'debito', '50.00', NULL, NULL),
(11, 5, 'credito', '50.00', 1, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `servico_profissionais`
--

CREATE TABLE `servico_profissionais` (
  `id` int(10) UNSIGNED NOT NULL,
  `servico_id` int(10) UNSIGNED NOT NULL,
  `profissional_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `servico_profissionais`
--

INSERT INTO `servico_profissionais` (`id`, `servico_id`, `profissional_id`) VALUES
(1, 1, 1),
(4, 2, 3),
(2, 3, 1),
(3, 4, 1),
(6, 5, 1);

--
-- Índices de tabelas apagadas
--

--
-- Índices de tabela `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_admins_username` (`username`);

--
-- Índices de tabela `estoque_categorias`
--
ALTER TABLE `estoque_categorias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_estoque_categorias_nome` (`nome`);

--
-- Índices de tabela `estoque_itens`
--
ALTER TABLE `estoque_itens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_estoque_itens_numero_serie` (`numero_serie`),
  ADD KEY `idx_estoque_itens_categoria` (`categoria_id`),
  ADD KEY `idx_estoque_itens_marca` (`marca`);

--
-- Índices de tabela `estoque_movimentacoes`
--
ALTER TABLE `estoque_movimentacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_estoque_mov_item` (`item_id`);

--
-- Índices de tabela `lembretes`
--
ALTER TABLE `lembretes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_lembretes_urgencia` (`urgencia`);

--
-- Índices de tabela `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_orders_token` (`token`),
  ADD KEY `idx_orders_cliente` (`cliente_nome`);

--
-- Índices de tabela `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_items_order` (`order_id`),
  ADD KEY `fk_order_items_profissional` (`profissional_id`);

--
-- Índices de tabela `order_item_profissionais`
--
ALTER TABLE `order_item_profissionais`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_oip_item_profissional` (`order_item_id`,`profissional_id`),
  ADD KEY `idx_oip_item` (`order_item_id`),
  ADD KEY `idx_oip_profissional` (`profissional_id`);

--
-- Índices de tabela `profissionais`
--
ALTER TABLE `profissionais`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `seminovos_items`
--
ALTER TABLE `seminovos_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_seminovos_items_order` (`seminovos_order_id`);

--
-- Índices de tabela `seminovos_orders`
--
ALTER TABLE `seminovos_orders`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `servicos`
--
ALTER TABLE `servicos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_servicos_data` (`data_servico`),
  ADD KEY `idx_servicos_profissional` (`profissional_id`);

--
-- Índices de tabela `servico_pagamentos`
--
ALTER TABLE `servico_pagamentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_servico_pagamentos_servico` (`servico_id`);

--
-- Índices de tabela `servico_profissionais`
--
ALTER TABLE `servico_profissionais`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sp_servico_profissional` (`servico_id`,`profissional_id`),
  ADD KEY `idx_sp_servico` (`servico_id`),
  ADD KEY `idx_sp_profissional` (`profissional_id`);

--
-- AUTO_INCREMENT de tabelas apagadas
--

--
-- AUTO_INCREMENT de tabela `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `estoque_categorias`
--
ALTER TABLE `estoque_categorias`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `estoque_itens`
--
ALTER TABLE `estoque_itens`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `estoque_movimentacoes`
--
ALTER TABLE `estoque_movimentacoes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `lembretes`
--
ALTER TABLE `lembretes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT de tabela `order_item_profissionais`
--
ALTER TABLE `order_item_profissionais`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT de tabela `profissionais`
--
ALTER TABLE `profissionais`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `seminovos_items`
--
ALTER TABLE `seminovos_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `seminovos_orders`
--
ALTER TABLE `seminovos_orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `servicos`
--
ALTER TABLE `servicos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `servico_pagamentos`
--
ALTER TABLE `servico_pagamentos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de tabela `servico_profissionais`
--
ALTER TABLE `servico_profissionais`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Restrições para dumps de tabelas
--

--
-- Restrições para tabelas `estoque_itens`
--
ALTER TABLE `estoque_itens`
  ADD CONSTRAINT `fk_estoque_itens_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `estoque_categorias` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `estoque_movimentacoes`
--
ALTER TABLE `estoque_movimentacoes`
  ADD CONSTRAINT `fk_estoque_mov_item` FOREIGN KEY (`item_id`) REFERENCES `estoque_itens` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_order_items_profissional` FOREIGN KEY (`profissional_id`) REFERENCES `profissionais` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `order_item_profissionais`
--
ALTER TABLE `order_item_profissionais`
  ADD CONSTRAINT `fk_oip_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_oip_profissional` FOREIGN KEY (`profissional_id`) REFERENCES `profissionais` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `seminovos_items`
--
ALTER TABLE `seminovos_items`
  ADD CONSTRAINT `fk_seminovos_items_order` FOREIGN KEY (`seminovos_order_id`) REFERENCES `seminovos_orders` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `servicos`
--
ALTER TABLE `servicos`
  ADD CONSTRAINT `fk_servicos_profissional` FOREIGN KEY (`profissional_id`) REFERENCES `profissionais` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `servico_pagamentos`
--
ALTER TABLE `servico_pagamentos`
  ADD CONSTRAINT `fk_servico_pagamentos_servico` FOREIGN KEY (`servico_id`) REFERENCES `servicos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `servico_profissionais`
--
ALTER TABLE `servico_profissionais`
  ADD CONSTRAINT `fk_sp_profissional` FOREIGN KEY (`profissional_id`) REFERENCES `profissionais` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sp_servico` FOREIGN KEY (`servico_id`) REFERENCES `servicos` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
