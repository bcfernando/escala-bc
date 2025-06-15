SET FOREIGN_KEY_CHECKS=0; -- Desabilita verificação de chaves estrangeiras

-- Apaga tabelas existentes (se existirem) para um setup limpo
DROP TABLE IF EXISTS plantoes;
DROP TABLE IF EXISTS excecoes_ciclo_fixo;
DROP TABLE IF EXISTS bombeiros;
DROP TABLE IF EXISTS configuracoes;

-- Cria a tabela bombeiros com os campos opcionais
CREATE TABLE bombeiros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_completo VARCHAR(255) NOT NULL,
    cpf VARCHAR(14) NULL DEFAULT NULL COMMENT 'Formato 000.000.000-00',
    tipo ENUM('BC', 'Fixo') NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    fixo_ref_data DATE NULL,
    fixo_ref_dia_ciclo TINYINT(1) NULL CHECK (fixo_ref_dia_ciclo BETWEEN 1 AND 4),
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    endereco_rua VARCHAR(255) NULL DEFAULT NULL,
    endereco_numero VARCHAR(20) NULL DEFAULT NULL,
    endereco_bairro VARCHAR(100) NULL DEFAULT NULL,
    endereco_cidade VARCHAR(100) NULL DEFAULT NULL,
    endereco_uf VARCHAR(2) NULL DEFAULT NULL,
    endereco_cep VARCHAR(10) NULL DEFAULT NULL,
    telefone_principal VARCHAR(20) NULL DEFAULT NULL,
    contato_emergencia_nome VARCHAR(255) NULL DEFAULT NULL,
    contato_emergencia_fone VARCHAR(20) NULL DEFAULT NULL,
    dados_bancarios TEXT NULL DEFAULT NULL COMMENT 'Ex: Banco XPTO, Ag: 0001, C/C: 12345-6',
    tamanho_gandola VARCHAR(20) NULL DEFAULT NULL,
    tamanho_camiseta VARCHAR(20) NULL DEFAULT NULL,
    tamanho_calca VARCHAR(20) NULL DEFAULT NULL,
    tamanho_calcado VARCHAR(10) NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cria a tabela plantoes
CREATE TABLE plantoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bombeiro_id INT NOT NULL,
    data DATE NOT NULL,
    turno ENUM('D', 'N', 'I') NOT NULL,
    FOREIGN KEY (bombeiro_id) REFERENCES bombeiros(id) ON DELETE CASCADE,
    UNIQUE KEY idx_bombeiro_data_turno (bombeiro_id, data, turno),
    INDEX idx_data (data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cria a tabela configuracoes
CREATE TABLE configuracoes (
    chave VARCHAR(50) PRIMARY KEY,
    valor VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cria a tabela excecoes_ciclo_fixo
CREATE TABLE excecoes_ciclo_fixo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bombeiro_id INT NOT NULL COMMENT 'ID do bombeiro Fixo que NÃO estará de serviço',
    data DATE NOT NULL COMMENT 'Data da exceção',
    UNIQUE KEY idx_bombeiro_data (bombeiro_id, data),
    FOREIGN KEY (bombeiro_id) REFERENCES bombeiros(id) ON DELETE CASCADE,
    INDEX idx_data (data)
) ENGINE= InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registra dias específicos em que um Fixo NÃO cumprirá seu ciclo';


-- Insere os bombeiros na ordem especificada
INSERT INTO bombeiros (nome_completo, tipo, ativo, fixo_ref_data, fixo_ref_dia_ciclo) VALUES
('ANDREA ZART', 'BC', 1, NULL, NULL),('ANELI MIOTTO TERNUS', 'BC', 1, NULL, NULL),('ANGELICA BOETTCHER', 'BC', 1, NULL, NULL),
('BRIAN DEIV HENRICH COSMAN', 'Fixo', 1, '2025-04-03', 1),('CLEIMAR BOETTCHER', 'Fixo', 1, '2025-04-01', 1),
('CLEIDIVAN IVAN BENEDIX', 'BC', 1, NULL, NULL),('CRISTIAN KONCZIKOSKI', 'Fixo', 1, '2025-04-02', 1),
('CRISTIANE BOETTCHER', 'BC', 1, NULL, NULL),('DOUGLAS LUBENOW', 'BC', 1, NULL, NULL),
('ELDI GELSI NICHTERWITZ PORTELA', 'BC', 1, NULL, NULL),('JOSÉ NELSO BOTT', 'BC', 1, NULL, NULL),
('KELVIN KERKHOFF', 'Fixo', 1, '2025-04-04', 1),('LUIZ FERNANDO HOHN', 'BC', 1, NULL, NULL),
('MAICON MOHR', 'BC', 1, NULL, NULL),('MARCLEI NICHTERVITZ', 'BC', 1, NULL, NULL),
('PATRICIA MARIA BOSING HOFFMANN', 'BC', 1, NULL, NULL),('PATRICIA BERTOLDI', 'BC', 1, NULL, NULL);

-- Insere/Atualiza as configurações iniciais
INSERT INTO configuracoes (chave, valor) VALUES
('ultimo_bc_iniciou_mes', NULL), ('bc_da_vez_id', NULL)
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

SET FOREIGN_KEY_CHECKS=1; -- Reabilita verificação de chaves estrangeiras