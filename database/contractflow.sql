DROP DATABASE IF EXISTS contractflow;

CREATE DATABASE contractflow
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE contractflow;

DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS approvals;
DROP TABLE IF EXISTS contract_documents;
DROP TABLE IF EXISTS contracts;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS departments;

-- departments, each one has a short code used in contract references
CREATE TABLE departments (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    code        VARCHAR(10)  NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_departments_name (name)
) ENGINE=InnoDB;

-- users, one row per person who can log in
CREATE TABLE users (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name            VARCHAR(100) NOT NULL,
    email                VARCHAR(150) NOT NULL,
    phone                VARCHAR(20)  NULL,
    password_hash        VARCHAR(255) NOT NULL,
    role                 ENUM('admin',
                              'department_officer',
                              'procurement_officer',
                              'legal_officer',
                              'head_of_legal',
                              'ceo',
                              'contractor') NOT NULL,
    department_id        INT UNSIGNED NULL,
    company_name         VARCHAR(150) NULL,
    is_active            TINYINT(1)   NOT NULL DEFAULT 1,
    must_change_password TINYINT(1)   NOT NULL DEFAULT 0,
    permissions          TEXT         NULL,
    last_login           DATETIME     NULL,
    created_at           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role),
    CONSTRAINT fk_users_department
        FOREIGN KEY (department_id) REFERENCES departments (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- contracts, the main table
CREATE TABLE contracts (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reference_no     VARCHAR(30)  NOT NULL,
    title            VARCHAR(200) NOT NULL,
    contract_type    VARCHAR(50)  NOT NULL,
    description      TEXT         NULL,
    estimated_budget DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    needed_by        DATE         NULL,
    contract_value   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    currency         CHAR(3)      NOT NULL DEFAULT 'KES',
    start_date       DATE         NULL,
    end_date         DATE         NULL,
    status           ENUM('draft',
                          'procurement_review',
                          'legal_review',
                          'legal_approval',
                          'executive_approval',
                          'contractor_signing',
                          'active',
                          'returned',
                          'rejected',
                          'expired',
                          'terminated') NOT NULL DEFAULT 'draft',
    department_id    INT UNSIGNED NOT NULL,
    created_by       INT UNSIGNED NOT NULL,
    contractor_id    INT UNSIGNED NULL,
    renewed_from     INT UNSIGNED NULL,
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_contracts_reference (reference_no),
    KEY idx_contracts_status (status),
    KEY idx_contracts_end_date (end_date),
    CONSTRAINT fk_contracts_department
        FOREIGN KEY (department_id) REFERENCES departments (id)
        ON UPDATE CASCADE,
    CONSTRAINT fk_contracts_creator
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON UPDATE CASCADE,
    CONSTRAINT fk_contracts_contractor
        FOREIGN KEY (contractor_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_contracts_renewed_from
        FOREIGN KEY (renewed_from) REFERENCES contracts (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- files uploaded for a contract, the file itself is kept outside the web folder
CREATE TABLE contract_documents (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contract_id    INT UNSIGNED NOT NULL,
    version_no     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    doc_type       VARCHAR(30)  NOT NULL DEFAULT 'request',
    original_name  VARCHAR(255) NOT NULL,
    stored_name    VARCHAR(255) NOT NULL,
    file_size      INT UNSIGNED NOT NULL,
    mime_type      VARCHAR(100) NOT NULL,
    uploaded_by    INT UNSIGNED NOT NULL,
    uploaded_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_documents_stored (stored_name),
    KEY idx_documents_contract (contract_id),
    CONSTRAINT fk_documents_contract
        FOREIGN KEY (contract_id) REFERENCES contracts (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_documents_uploader
        FOREIGN KEY (uploaded_by) REFERENCES users (id)
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- every approve, return or reject decision
CREATE TABLE approvals (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contract_id  INT UNSIGNED NOT NULL,
    stage        ENUM('procurement_review',
                      'legal_review',
                      'legal_approval',
                      'executive_approval',
                      'contractor_signing') NOT NULL,
    approver_id  INT UNSIGNED NOT NULL,
    decision     ENUM('approved','rejected','returned') NOT NULL,
    comments     TEXT NULL,
    decided_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_approvals_contract (contract_id),
    CONSTRAINT fk_approvals_contract
        FOREIGN KEY (contract_id) REFERENCES contracts (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_approvals_approver
        FOREIGN KEY (approver_id) REFERENCES users (id)
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- in-app notifications
CREATE TABLE notifications (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,
    contract_id  INT UNSIGNED NULL,
    message      VARCHAR(255) NOT NULL,
    is_read      TINYINT(1)   NOT NULL DEFAULT 0,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notifications_user (user_id, is_read),
    CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_notifications_contract
        FOREIGN KEY (contract_id) REFERENCES contracts (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- a record of what people did
CREATE TABLE activity_logs (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NULL,
    contract_id  INT UNSIGNED NULL,
    action       VARCHAR(60)  NOT NULL,
    details      VARCHAR(255) NULL,
    ip_address   VARCHAR(45)  NULL,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_logs_user (user_id),
    KEY idx_logs_created (created_at),
    CONSTRAINT fk_logs_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- starting departments
INSERT INTO departments (name, code) VALUES
    ('Procurement',      'PRC'),
    ('Legal',            'LGL'),
    ('Finance',          'FIN'),
    ('ICT',              'ICT'),
    ('Human Resources',  'HR'),
    ('Operations',       'OPS');

-- default administrator: admin@contractflow.com / Admin@1234, the password must be changed at first login
INSERT INTO users (full_name, email, password_hash, role, must_change_password) VALUES
    ('System Administrator',
     'admin@contractflow.com',
     '$2y$10$np90gwy3WrG0XhLxdJMCd.Gq5bj1jf8qnpwolg7gUsWLyqBkvr9Ie',
     'admin',
     1);
