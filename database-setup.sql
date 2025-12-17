CREATE TABLE IF NOT EXISTS search_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    highest_customer_number INT NOT NULL,
    customer_type TINYINT NOT NULL COMMENT '1 for Aktiv, 2 for Temporär',
    search_date DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Índices para optimizar las consultas
CREATE INDEX idx_customer_type ON search_history(customer_type);
CREATE INDEX idx_search_date ON search_history(search_date);
