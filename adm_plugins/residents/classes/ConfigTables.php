<?php
/**
 ***********************************************************************************************
 * Creator for Residents plugin tables (multi-DB: MySQL/PostgreSQL)
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../common_function.php');

class ConfigTables
{
    private const TABLE_DEFINITION_MYSQL_RE_INVOICES = '
    riv_id INT NOT NULL AUTO_INCREMENT,
    riv_org_id INT NULL,
    riv_status VARCHAR(30) NOT NULL DEFAULT \'O\',
    riv_is_paid TINYINT(1) NOT NULL DEFAULT 0,
    riv_number_index INT NOT NULL,
    riv_number VARCHAR(50) NOT NULL,
    riv_date DATE NULL,
    riv_type VARCHAR(30) NOT NULL DEFAULT \'I\',
    riv_usr_id INT NULL,
    riv_start_date DATE NULL,
    riv_end_date DATE NULL,
    riv_due_date DATE NULL,
    riv_notes TEXT NULL,
    riv_usr_id_create INT DEFAULT NULL,
    riv_timestamp_create TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    riv_usr_id_change INT DEFAULT NULL,
    riv_timestamp_change TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (riv_id)
    ';

    private const TABLE_DEFINITION_MYSQL_RE_INVOICES_HIST = '
    rih_id INT NOT NULL AUTO_INCREMENT,
    riv_id INT NOT NULL,
    riv_org_id INT NULL,
    riv_status VARCHAR(30) NOT NULL DEFAULT \'O\',
    riv_is_paid TINYINT(1) NOT NULL DEFAULT 0,
    riv_number_index INT NOT NULL,
    riv_number VARCHAR(50) NOT NULL,
    riv_date DATE NULL,
    riv_type VARCHAR(30) NOT NULL DEFAULT \'I\',
    riv_usr_id INT NULL,
    riv_start_date DATE NULL,
    riv_end_date DATE NULL,
    riv_due_date DATE NULL,
    riv_notes TEXT NULL,
    riv_usr_id_create INT DEFAULT NULL,
    riv_timestamp_create TIMESTAMP NULL DEFAULT NULL,
    riv_usr_id_change INT DEFAULT NULL,
    riv_timestamp_change TIMESTAMP NULL DEFAULT NULL,
    rih_action VARCHAR(20) NOT NULL,
    rih_usr_id INT DEFAULT NULL,
    rih_timestamp TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (rih_id)
    ';

    private const TABLE_DEFINITION_PGSQL_RE_INVOICES = '
    riv_id SERIAL PRIMARY KEY,
    riv_org_id INTEGER NULL,
    riv_status VARCHAR(30) NOT NULL DEFAULT \'O\',
    riv_is_paid INTEGER NOT NULL DEFAULT 0,
    riv_number_index INTEGER NOT NULL,
    riv_number VARCHAR(50) NOT NULL,
    riv_date DATE NULL,
    riv_type VARCHAR(30) NOT NULL DEFAULT \'I\',
    riv_usr_id INTEGER NULL,
    riv_start_date DATE NULL,
    riv_end_date DATE NULL,
    riv_due_date DATE NULL,
    riv_notes TEXT NULL,
    riv_usr_id_create INTEGER DEFAULT NULL,
    riv_timestamp_create TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    riv_usr_id_change INTEGER DEFAULT NULL,
    riv_timestamp_change TIMESTAMP DEFAULT NULL
    ';

    private const TABLE_DEFINITION_PGSQL_RE_INVOICES_HIST = '
    rih_id SERIAL PRIMARY KEY,
    riv_id INTEGER NOT NULL,
    riv_org_id INTEGER NULL,
    riv_status VARCHAR(30) NOT NULL DEFAULT \'O\',
    riv_is_paid INTEGER NOT NULL DEFAULT 0,
    riv_number_index INTEGER NOT NULL,
    riv_number VARCHAR(50) NOT NULL,
    riv_date DATE NULL,
    riv_type VARCHAR(30) NOT NULL DEFAULT \'I\',
    riv_usr_id INTEGER NULL,
    riv_start_date DATE NULL,
    riv_end_date DATE NULL,
    riv_due_date DATE NULL,
    riv_notes TEXT NULL,
    riv_usr_id_create INTEGER DEFAULT NULL,
    riv_timestamp_create TIMESTAMP DEFAULT NULL,
    riv_usr_id_change INTEGER DEFAULT NULL,
    riv_timestamp_change TIMESTAMP DEFAULT NULL,
    rih_action VARCHAR(20) NOT NULL,
    rih_usr_id INTEGER DEFAULT NULL,
    rih_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ';

    private const TABLE_DEFINITION_MYSQL_RE_INVOICE_ITEMS = '
    rii_id INT NOT NULL AUTO_INCREMENT,
    rii_org_id INT NULL,
    rii_inv_id INT NOT NULL,
    rii_chg_id INT NOT NULL DEFAULT 0,
    rii_name VARCHAR(255) NOT NULL,
    rii_start_date DATE NULL,
    rii_end_date DATE NULL,
    rii_type VARCHAR(30) NULL,
    rii_currency VARCHAR(8) NULL,
    rii_rate DECIMAL(12,2) NULL,
    rii_quantity DECIMAL(12,2) NULL,
    rii_amount DECIMAL(12,2) NULL,
    rii_usr_id_create INT DEFAULT NULL,
    rii_timestamp_create TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    rii_usr_id_change INT DEFAULT NULL,
    rii_timestamp_change TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (rii_id)
    ';

    private const TABLE_DEFINITION_MYSQL_RE_INVOICE_ITEMS_HIST = '
    riih_id INT NOT NULL AUTO_INCREMENT,
    rii_id INT NOT NULL,
    rii_org_id INT NULL,
    rii_inv_id INT NOT NULL,
    rii_chg_id INT NOT NULL DEFAULT 0,
    rii_name VARCHAR(255) NOT NULL,
    rii_start_date DATE NULL,
    rii_end_date DATE NULL,
    rii_type VARCHAR(30) NULL,
    rii_currency VARCHAR(8) NULL,
    rii_rate DECIMAL(12,2) NULL,
    rii_quantity DECIMAL(12,2) NULL,
    rii_amount DECIMAL(12,2) NULL,
    rii_usr_id_create INT DEFAULT NULL,
    rii_timestamp_create TIMESTAMP NULL DEFAULT NULL,
    rii_usr_id_change INT DEFAULT NULL,
    rii_timestamp_change TIMESTAMP NULL DEFAULT NULL,
    riih_action VARCHAR(20) NOT NULL,
    riih_usr_id INT DEFAULT NULL,
    riih_timestamp TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (riih_id)
    ';

    private const TABLE_DEFINITION_PGSQL_RE_INVOICE_ITEMS = '
    rii_id SERIAL PRIMARY KEY,
    rii_org_id INTEGER NULL,
    rii_inv_id INTEGER NOT NULL,
    rii_chg_id INTEGER NOT NULL DEFAULT 0,
    rii_name VARCHAR(255) NOT NULL,
    rii_start_date DATE NULL,
    rii_end_date DATE NULL,
    rii_type VARCHAR(30) NULL,
    rii_currency VARCHAR(8) NULL,
    rii_rate NUMERIC(12,2) NULL,
    rii_quantity NUMERIC(12,2) NULL,
    rii_amount NUMERIC(12,2) NULL,
    rii_usr_id_create INTEGER DEFAULT NULL,
    rii_timestamp_create TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    rii_usr_id_change INTEGER DEFAULT NULL,
    rii_timestamp_change TIMESTAMP DEFAULT NULL
    ';

    private const TABLE_DEFINITION_PGSQL_RE_INVOICE_ITEMS_HIST = '
    riih_id SERIAL PRIMARY KEY,
    rii_id INTEGER NOT NULL,
    rii_org_id INTEGER NULL,
    rii_inv_id INTEGER NOT NULL,
    rii_chg_id INTEGER NOT NULL DEFAULT 0,
    rii_name VARCHAR(255) NOT NULL,
    rii_start_date DATE NULL,
    rii_end_date DATE NULL,
    rii_type VARCHAR(30) NULL,
    rii_currency VARCHAR(8) NULL,
    rii_rate NUMERIC(12,2) NULL,
    rii_quantity NUMERIC(12,2) NULL,
    rii_amount NUMERIC(12,2) NULL,
    rii_usr_id_create INTEGER DEFAULT NULL,
    rii_timestamp_create TIMESTAMP DEFAULT NULL,
    rii_usr_id_change INTEGER DEFAULT NULL,
    rii_timestamp_change TIMESTAMP DEFAULT NULL,
    riih_action VARCHAR(20) NOT NULL,
    riih_usr_id INTEGER DEFAULT NULL,
    riih_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ';

    private const TABLE_DEFINITION_MYSQL_RE_PAYMENTS = '
    rpa_id INT NOT NULL AUTO_INCREMENT,
    rpa_status VARCHAR(30) NOT NULL,
    rpa_date TIMESTAMP NULL,
    rpa_pay_type VARCHAR(30) NULL,
    rpa_pg_pay_method VARCHAR(30) NULL,
    rpa_trans_id VARCHAR(30) NULL,
    rpa_bank_ref_no VARCHAR(255) NULL,
    rpa_usr_id INT NULL,
    rpa_org_id INT NULL,
    rpa_usr_id_create INT DEFAULT NULL,
    rpa_timestamp_create TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    rpa_usr_id_change INT DEFAULT NULL,
    rpa_timestamp_change TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (rpa_id)
    ';

    private const TABLE_DEFINITION_MYSQL_RE_PAYMENTS_HIST = '
    rpah_id INT NOT NULL AUTO_INCREMENT,
    rpa_id INT NOT NULL,
    rpa_status VARCHAR(30) NOT NULL,
    rpa_date TIMESTAMP NULL,
    rpa_pay_type VARCHAR(30) NULL,
    rpa_pg_pay_method VARCHAR(30) NULL,
    rpa_trans_id VARCHAR(30) NULL,
    rpa_bank_ref_no VARCHAR(255) NULL,
    rpa_usr_id INT NULL,
    rpa_org_id INT NULL,
    rpa_usr_id_create INT DEFAULT NULL,
    rpa_timestamp_create TIMESTAMP NULL DEFAULT NULL,
    rpa_usr_id_change INT DEFAULT NULL,
    rpa_timestamp_change TIMESTAMP NULL DEFAULT NULL,
    rpah_action VARCHAR(20) NOT NULL,
    rpah_usr_id INT DEFAULT NULL,
    rpah_timestamp TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (rpah_id)
    ';

    private const TABLE_DEFINITION_PGSQL_RE_PAYMENTS = '
    rpa_id SERIAL PRIMARY KEY,
    rpa_status VARCHAR(30) NOT NULL,
    rpa_date TIMESTAMP NULL,
    rpa_pay_type VARCHAR(30) NULL,
    rpa_pg_pay_method VARCHAR(30) NULL,
    rpa_trans_id VARCHAR(30) NULL,
    rpa_bank_ref_no VARCHAR(255) NULL,
    rpa_usr_id INTEGER NULL,
    rpa_org_id INTEGER NULL,
    rpa_usr_id_create INTEGER DEFAULT NULL,
    rpa_timestamp_create TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    rpa_usr_id_change INTEGER DEFAULT NULL,
    rpa_timestamp_change TIMESTAMP DEFAULT NULL
    ';

    private const TABLE_DEFINITION_PGSQL_RE_PAYMENTS_HIST = '
    rpah_id SERIAL PRIMARY KEY,
    rpa_id INTEGER NOT NULL,
    rpa_status VARCHAR(30) NOT NULL,
    rpa_date TIMESTAMP NULL,
    rpa_pay_type VARCHAR(30) NULL,
    rpa_pg_pay_method VARCHAR(30) NULL,
    rpa_trans_id VARCHAR(30) NULL,
    rpa_bank_ref_no VARCHAR(255) NULL,
    rpa_usr_id INTEGER NULL,
    rpa_org_id INTEGER NULL,
    rpa_usr_id_create INTEGER DEFAULT NULL,
    rpa_timestamp_create TIMESTAMP DEFAULT NULL,
    rpa_usr_id_change INTEGER DEFAULT NULL,
    rpa_timestamp_change TIMESTAMP DEFAULT NULL,
    rpah_action VARCHAR(20) NOT NULL,
    rpah_usr_id INTEGER DEFAULT NULL,
    rpah_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ';

    private const TABLE_DEFINITION_MYSQL_RE_PAYMENT_ITEMS = '
    rpi_id INT NOT NULL AUTO_INCREMENT,
    rpi_payment_id INT NULL,
    rpi_inv_id INT NULL,
    rpi_amount DECIMAL(16,2) NULL,
    rpi_currency VARCHAR(5) NULL,
    rpi_usr_id INT NULL,
    rpi_org_id INT NULL,
    rpi_usr_id_create INT NULL,
    rpi_usr_id_change INT NULL,
    rpi_timestamp_create TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    rpi_timestamp_change TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (rpi_id)
    ';

    private const TABLE_DEFINITION_MYSQL_RE_PAYMENT_ITEMS_HIST = '
    rpih_id INT NOT NULL AUTO_INCREMENT,
    rpi_id INT NOT NULL,
    rpi_payment_id INT NULL,
    rpi_inv_id INT NULL,
    rpi_amount DECIMAL(16,2) NULL,
    rpi_currency VARCHAR(5) NULL,
    rpi_usr_id INT NULL,
    rpi_org_id INT NULL,
    rpi_usr_id_create INT NULL,
    rpi_usr_id_change INT NULL,
    rpi_timestamp_create TIMESTAMP NULL DEFAULT NULL,
    rpi_timestamp_change TIMESTAMP NULL DEFAULT NULL,
    rpih_action VARCHAR(20) NOT NULL,
    rpih_usr_id INT DEFAULT NULL,
    rpih_timestamp TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (rpih_id)
    ';

    private const TABLE_DEFINITION_PGSQL_RE_PAYMENT_ITEMS = '
    rpi_id SERIAL PRIMARY KEY,
    rpi_payment_id INTEGER NULL,
    rpi_inv_id INTEGER NULL,
    rpi_amount NUMERIC(16,2) NULL,
    rpi_currency VARCHAR(5) NULL,
    rpi_usr_id INTEGER NULL,
    rpi_org_id INTEGER NULL,
    rpi_usr_id_create INTEGER NULL,
    rpi_usr_id_change INTEGER NULL,
    rpi_timestamp_create TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    rpi_timestamp_change TIMESTAMP DEFAULT NULL
    ';

    private const TABLE_DEFINITION_PGSQL_RE_PAYMENT_ITEMS_HIST = '
    rpih_id SERIAL PRIMARY KEY,
    rpi_id INTEGER NOT NULL,
    rpi_payment_id INTEGER NULL,
    rpi_inv_id INTEGER NULL,
    rpi_amount NUMERIC(16,2) NULL,
    rpi_currency VARCHAR(5) NULL,
    rpi_usr_id INTEGER NULL,
    rpi_org_id INTEGER NULL,
    rpi_usr_id_create INTEGER DEFAULT NULL,
    rpi_usr_id_change INTEGER DEFAULT NULL,
    rpi_timestamp_create TIMESTAMP DEFAULT NULL,
    rpi_timestamp_change TIMESTAMP DEFAULT NULL,
    rpih_action VARCHAR(20) NOT NULL,
    rpih_usr_id INTEGER DEFAULT NULL,
    rpih_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ';

    private const TABLE_DEFINITION_MYSQL_RE_TRANS = '
    rtr_id INT NOT NULL AUTO_INCREMENT,
    rtr_pg_id VARCHAR(30) NULL,
    rtr_bank_ref_no VARCHAR(255) NULL,
    rtr_status VARCHAR(5) NULL,
    rtr_amount DECIMAL(16,2) NULL,
    rtr_currency VARCHAR(5) NULL,
    rtr_payment_id INT NULL,
    rtr_usr_id INT NULL,
    rtr_org_id INT NULL,
    rtr_pg_pay_method VARCHAR(255) NULL,
    rtr_pg_msg VARCHAR(255) NULL,
    rtr_pg_trans_date TIMESTAMP NULL,
    rtr_pg_request TEXT NULL,
    rtr_pg_response TEXT NULL,
    rtr_usr_id_create INT NULL,
    rtr_usr_id_change INT NULL,
    rtr_timestamp_create TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    rtr_timestamp_change TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (rtr_id)
    ';

    private const TABLE_DEFINITION_PGSQL_RE_TRANS = '
    rtr_id SERIAL PRIMARY KEY,
    rtr_pg_id VARCHAR(30) NULL,
    rtr_bank_ref_no VARCHAR(255) NULL,
    rtr_status VARCHAR(5) NULL,
    rtr_amount NUMERIC(16,2) NULL,
    rtr_currency VARCHAR(5) NULL,
    rtr_payment_id INTEGER NULL,
    rtr_usr_id INTEGER NULL,
    rtr_org_id INTEGER NULL,
    rtr_pg_pay_method VARCHAR(255) NULL,
    rtr_pg_msg VARCHAR(255) NULL,
    rtr_pg_trans_date TIMESTAMP NULL,
    rtr_pg_request TEXT NULL,
    rtr_pg_response TEXT NULL,
    rtr_usr_id_create INTEGER NULL,
    rtr_usr_id_change INTEGER NULL,
    rtr_timestamp_create TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    rtr_timestamp_change TIMESTAMP DEFAULT NULL
    ';

    private const TABLE_DEFINITION_MYSQL_RE_TRANS_ITEMS = '
    rti_id INT NOT NULL AUTO_INCREMENT,
    rti_pg_payment_id INT NULL,
    rti_inv_id INT NULL,
    rti_amount DECIMAL(16,2) NULL,
    rti_currency VARCHAR(5) NULL,
    rti_usr_id INT NULL,
    rti_org_id INT NULL,
    rti_usr_id_create INT NULL,
    rti_usr_id_change INT NULL,
    rti_timestamp_create TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    rti_timestamp_change TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (rti_id)
    ';

    private const TABLE_DEFINITION_PGSQL_RE_TRANS_ITEMS = '
    rti_id SERIAL PRIMARY KEY,
    rti_pg_payment_id INTEGER NULL,
    rti_inv_id INTEGER NULL,
    rti_amount NUMERIC(16,2) NULL,
    rti_currency VARCHAR(5) NULL,
    rti_usr_id INTEGER NULL,
    rti_org_id INTEGER NULL,
    rti_usr_id_create INTEGER NULL,
    rti_usr_id_change INTEGER NULL,
    rti_timestamp_create TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    rti_timestamp_change TIMESTAMP DEFAULT NULL
    ';

    private const TABLE_DEFINITION_MYSQL_RE_CHARGES = '
    rch_id INT NOT NULL AUTO_INCREMENT,
    rch_org_id INT NULL,
    rch_name VARCHAR(150) NOT NULL,
    rch_period VARCHAR(50) NOT NULL,
    rch_amount DECIMAL(12,2) NOT NULL,
    rch_role_ids TEXT NULL,
    rch_usr_id_create INT DEFAULT NULL,
    rch_timestamp_create TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    rch_usr_id_change INT DEFAULT NULL,
    rch_timestamp_change TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (rch_id)
    ';

    private const TABLE_DEFINITION_MYSQL_RE_CHARGES_HIST = '
    rchh_id INT NOT NULL AUTO_INCREMENT,
    rch_id INT NOT NULL,
    rch_org_id INT NULL,
    rch_name VARCHAR(150) NOT NULL,
    rch_period VARCHAR(50) NOT NULL,
    rch_amount DECIMAL(12,2) NOT NULL,
    rch_role_ids TEXT NULL,
    rch_usr_id_create INT DEFAULT NULL,
    rch_timestamp_create TIMESTAMP NULL DEFAULT NULL,
    rch_usr_id_change INT DEFAULT NULL,
    rch_timestamp_change TIMESTAMP NULL DEFAULT NULL,
    rchh_action VARCHAR(20) NOT NULL,
    rchh_usr_id INT DEFAULT NULL,
    rchh_timestamp TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (rchh_id)
    ';

    private const TABLE_DEFINITION_PGSQL_RE_CHARGES = '
    rch_id SERIAL PRIMARY KEY,
    rch_org_id INTEGER NULL,
    rch_name VARCHAR(150) NOT NULL,
    rch_period VARCHAR(50) NOT NULL,
    rch_amount NUMERIC(12,2) NOT NULL,
    rch_role_ids TEXT NULL,
    rch_usr_id_create INTEGER DEFAULT NULL,
    rch_timestamp_create TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    rch_usr_id_change INTEGER DEFAULT NULL,
    rch_timestamp_change TIMESTAMP DEFAULT NULL
    ';

    private const TABLE_DEFINITION_PGSQL_RE_CHARGES_HIST = '
    rchh_id SERIAL PRIMARY KEY,
    rch_id INTEGER NOT NULL,
    rch_org_id INTEGER NULL,
    rch_name VARCHAR(150) NOT NULL,
    rch_period VARCHAR(50) NOT NULL,
    rch_amount NUMERIC(12,2) NOT NULL,
    rch_role_ids TEXT NULL,
    rch_usr_id_create INTEGER DEFAULT NULL,
    rch_timestamp_create TIMESTAMP DEFAULT NULL,
    rch_usr_id_change INTEGER DEFAULT NULL,
    rch_timestamp_change TIMESTAMP DEFAULT NULL,
    rchh_action VARCHAR(20) NOT NULL,
    rchh_usr_id INTEGER DEFAULT NULL,
    rchh_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ';
    
    private const TABLE_DEFINITION_MYSQL_RE_DEVICES = '
    rde_id INT NOT NULL AUTO_INCREMENT,
    rde_org_id INT NULL,
    rde_device_id VARCHAR(100) NOT NULL,
    rde_usr_id INT NULL,
    rde_is_active TINYINT(1) NOT NULL DEFAULT 0,
    rde_active_date DATETIME NULL,
    rde_api_key VARCHAR(50) NULL,
    rde_platform VARCHAR(50) NOT NULL,
    rde_brand VARCHAR(50) NOT NULL,
    rde_model VARCHAR(50) NOT NULL,
    rde_usr_id_create INT DEFAULT NULL,
    rde_timestamp_create TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    rde_usr_id_change INT DEFAULT NULL,
    rde_timestamp_change TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (rde_id)
    ';
    
    private const TABLE_DEFINITION_PGSQL_RE_DEVICES = '
    rde_id SERIAL PRIMARY KEY,
    rde_org_id INTEGER NULL,
    rde_device_id VARCHAR(100) NOT NULL,
    rde_usr_id INTEGER NULL,
    rde_is_active INTEGER NOT NULL DEFAULT 0,
    rde_active_date TIMESTAMP NULL,
    rde_api_key VARCHAR(50) NULL,
    rde_platform VARCHAR(50) NOT NULL,
    rde_brand VARCHAR(50) NOT NULL,
    rde_model VARCHAR(50) NOT NULL,
    rde_usr_id_create INTEGER DEFAULT NULL,
    rde_timestamp_create TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    rde_usr_id_change INTEGER DEFAULT NULL,
    rde_timestamp_change TIMESTAMP DEFAULT NULL
    ';

    private const TABLE_DEFINITION_MYSQL_RE_DEVICES_HIST = '
    rdeh_id INT NOT NULL AUTO_INCREMENT,
    rde_id INT NOT NULL,
    rde_org_id INT NULL,
    rde_device_id VARCHAR(100) NOT NULL,
    rde_usr_id INT NULL,
    rde_is_active TINYINT(1) NOT NULL DEFAULT 0,
    rde_active_date DATETIME NULL,
    rde_api_key VARCHAR(50) NULL,
    rde_platform VARCHAR(50) NOT NULL,
    rde_brand VARCHAR(50) NOT NULL,
    rde_model VARCHAR(50) NOT NULL,
    rde_usr_id_create INT DEFAULT NULL,
    rde_timestamp_create TIMESTAMP NULL DEFAULT NULL,
    rde_usr_id_change INT DEFAULT NULL,
    rde_timestamp_change TIMESTAMP NULL DEFAULT NULL,
    rdeh_action VARCHAR(20) NOT NULL,
    rdeh_usr_id INT DEFAULT NULL,
    rdeh_timestamp TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (rdeh_id)
    ';

    private const TABLE_DEFINITION_PGSQL_RE_DEVICES_HIST = '
    rdeh_id SERIAL PRIMARY KEY,
    rde_id INTEGER NOT NULL,
    rde_org_id INTEGER NULL,
    rde_device_id VARCHAR(100) NOT NULL,
    rde_usr_id INTEGER NULL,
    rde_is_active INTEGER NOT NULL DEFAULT 0,
    rde_active_date TIMESTAMP NULL,
    rde_api_key VARCHAR(50) NULL,
    rde_platform VARCHAR(50) NOT NULL,
    rde_brand VARCHAR(50) NOT NULL,
    rde_model VARCHAR(50) NOT NULL,
    rde_usr_id_create INTEGER DEFAULT NULL,
    rde_timestamp_create TIMESTAMP DEFAULT NULL,
    rde_usr_id_change INTEGER DEFAULT NULL,
    rde_timestamp_change TIMESTAMP DEFAULT NULL,
    rdeh_action VARCHAR(20) NOT NULL,
    rdeh_usr_id INTEGER DEFAULT NULL,
    rdeh_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ';

    private const INVOICES_UNIQUE_INDEX_NUMBER = '
    CREATE UNIQUE INDEX ' . TABLE_PREFIX . '_idx_riv_number ON ' . TBL_RE_INVOICES . ' (riv_org_id, riv_number)
    ';

    private const INVOICES_UNIQUE_INDEX_NUMBER_INDEX = '
    CREATE UNIQUE INDEX ' . TABLE_PREFIX . '_idx_riv_number_index ON ' . TBL_RE_INVOICES . ' (riv_org_id, riv_number_index)
    ';

    private const INVOICES_CONSTRAINTS = '
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_riv_usr           FOREIGN KEY (riv_usr_id)          REFERENCES ' . TBL_USERS . ' (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_riv_usr_create    FOREIGN KEY (riv_usr_id_create)   REFERENCES ' . TBL_USERS . ' (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_riv_usr_change    FOREIGN KEY (riv_usr_id_change)   REFERENCES ' . TBL_USERS . ' (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT
    ';

    private const ITEMS_INDEXES = '
    CREATE INDEX ' . TABLE_PREFIX . '_idx_rii_inv_id ON ' . TBL_RE_INVOICE_ITEMS . ' (rii_inv_id)
    ';

    private const ITEMS_INDEX_CHG_ID = '
    CREATE INDEX ' . TABLE_PREFIX . '_idx_rii_chg_id ON ' . TBL_RE_INVOICE_ITEMS . ' (rii_chg_id)
    ';

    private const ITEMS_CONSTRAINTS = '
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_rii_inv          FOREIGN KEY (rii_inv_id)        REFERENCES ' . TBL_RE_INVOICES . ' (riv_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_rii_usr_create   FOREIGN KEY (rii_usr_id_create)  REFERENCES ' . TBL_USERS . ' (usr_id)   ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_rii_usr_change   FOREIGN KEY (rii_usr_id_change)  REFERENCES ' . TBL_USERS . ' (usr_id)   ON DELETE SET NULL ON UPDATE RESTRICT
    ';

    private const PAYMENTS_CONSTRAINTS = '
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_rpa_usr           FOREIGN KEY (rpa_usr_id)          REFERENCES ' . TBL_USERS . ' (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_rpa_usr_create    FOREIGN KEY (rpa_usr_id_create)   REFERENCES ' . TBL_USERS . ' (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_rpa_usr_change    FOREIGN KEY (rpa_usr_id_change)   REFERENCES ' . TBL_USERS . ' (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT
    ';

    private const PAYMENT_ITEMS_CONSTRAINTS = '
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_rpi_payment          FOREIGN KEY (rpi_payment_id)        REFERENCES ' . TBL_RE_PAYMENTS . ' (rpa_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_rpi_usr_create   FOREIGN KEY (rpi_usr_id_create)  REFERENCES ' . TBL_USERS . ' (usr_id)   ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_rpi_usr_change   FOREIGN KEY (rpi_usr_id_change)  REFERENCES ' . TBL_USERS . ' (usr_id)   ON DELETE SET NULL ON UPDATE RESTRICT
    ';

    private const PG_PAYMENTS_CONSTRAINTS = '
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_rtr_usr           FOREIGN KEY (rtr_usr_id)          REFERENCES ' . TBL_USERS . ' (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_rtr_usr_create    FOREIGN KEY (rtr_usr_id_create)   REFERENCES ' . TBL_USERS . ' (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_rtr_usr_change    FOREIGN KEY (rtr_usr_id_change)   REFERENCES ' . TBL_USERS . ' (usr_id) ON DELETE SET NULL ON UPDATE RESTRICT
    ';

    private const TRANS_ITEMS_CONSTRAINTS = '
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_rti_pg_payment          FOREIGN KEY (rti_pg_payment_id)        REFERENCES ' . TBL_RE_TRANS . ' (rtr_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_rti_usr_create   FOREIGN KEY (rti_usr_id_create)  REFERENCES ' . TBL_USERS . ' (usr_id)   ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT ' . TABLE_PREFIX . '_fk_rti_usr_change   FOREIGN KEY (rti_usr_id_change)  REFERENCES ' . TBL_USERS . ' (usr_id)   ON DELETE SET NULL ON UPDATE RESTRICT
    ';

    private const PAYMENT_ITEMS_INDEXES = '
    CREATE INDEX ' . TABLE_PREFIX . '_idx_rpi_payment_id ON ' . TBL_RE_PAYMENT_ITEMS . ' (rpi_payment_id)
    ';

    private const TRANS_ITEMS_INDEXES = '
    CREATE INDEX ' . TABLE_PREFIX . '_idx_rti_pg_payment_id ON ' . TBL_RE_TRANS_ITEMS . ' (rti_pg_payment_id)
    ';

    public function init(): void
    {
        $this->createTablesIfNotExist();
        $this->applySchemaUpgrades();
    }

    private function applySchemaUpgrades(): void
    {
        global $gDb, $gDbType;

        // vNext: Make invoice numbering unique per organization.
        // Older installations created unique indexes only on riv_number and riv_number_index,
        // which prevents generating invoices in a second organization (numbers collide).
        $idxNumber = TABLE_PREFIX . '_idx_riv_number';
        $idxNumberIndex = TABLE_PREFIX . '_idx_riv_number_index';

        $fetchIndexColumns = static function (string $indexName) use ($gDb, $gDbType): array {
            if ($gDbType === 'pgsql') {
                $stmt = $gDb->queryPrepared(
                    'SELECT indexdef FROM pg_indexes WHERE schemaname = current_schema() AND indexname = ?',
                    array($indexName),
                    false
                );
                if ($stmt === false) {
                    return array();
                }
                $indexDef = (string)($stmt->fetchColumn() ?? '');
                if ($indexDef === '') {
                    return array();
                }
                // Extract columns between parentheses at end of CREATE INDEX ... (..)
                if (preg_match('/\(([^)]+)\)\s*$/', $indexDef, $m) !== 1) {
                    return array();
                }
                $cols = array_map('trim', explode(',', (string)$m[1]));
                return array_values(array_filter($cols, static fn($c) => $c !== ''));
            }

            // MySQL
            $stmt = $gDb->queryPrepared(
                'SELECT column_name FROM information_schema.statistics
                 WHERE table_schema = ? AND table_name = ? AND index_name = ?
                 ORDER BY seq_in_index',
                array(DB_NAME, TBL_RE_INVOICES, $indexName),
                false
            );
            if ($stmt === false) {
                return array();
            }
            $cols = array();
            while ($row = $stmt->fetch()) {
                $cols[] = (string)$row['column_name'];
            }
            return $cols;
        };

        $dropIndex = static function (string $indexName) use ($gDb, $gDbType): void {
            if (!indexExistsRE(TBL_RE_INVOICES, $indexName)) {
                return;
            }
            if ($gDbType === 'pgsql') {
                $gDb->queryPrepared('DROP INDEX IF EXISTS ' . $indexName, array(), false);
            } else {
                $gDb->queryPrepared('DROP INDEX ' . $indexName . ' ON ' . TBL_RE_INVOICES, array(), false);
            }
        };

        // Upgrade riv_number unique index
        $colsNumber = $fetchIndexColumns($idxNumber);
        if (!empty($colsNumber) && !in_array('riv_org_id', $colsNumber, true)) {
            $dropIndex($idxNumber);
            $this->createUniqueIndexIfNotExist(TBL_RE_INVOICES, self::INVOICES_UNIQUE_INDEX_NUMBER);
        }

        // Upgrade riv_number_index unique index
        $colsNumberIndex = $fetchIndexColumns($idxNumberIndex);
        if (!empty($colsNumberIndex) && !in_array('riv_org_id', $colsNumberIndex, true)) {
            $dropIndex($idxNumberIndex);
            $this->createUniqueIndexIfNotExist(TBL_RE_INVOICES, self::INVOICES_UNIQUE_INDEX_NUMBER_INDEX);
        }
    }

    public function uninstall(): void
    {
        $tables = array(
            TBL_RE_CHARGES,
            TBL_RE_CHARGES_HIST,
            TBL_RE_PAYMENT_ITEMS,
            TBL_RE_PAYMENT_ITEMS_HIST,
            TBL_RE_PAYMENTS,
            TBL_RE_PAYMENTS_HIST,
            TBL_RE_INVOICE_ITEMS,
            TBL_RE_INVOICE_ITEMS_HIST,
            TBL_RE_INVOICES,
            TBL_RE_INVOICES_HIST,
            TBL_RE_TRANS,
            TBL_RE_TRANS_ITEMS,
            TBL_RE_DEVICES,
            TBL_RE_DEVICES_HIST
        );

        foreach ($tables as $table) {
            $this->dropTableIfExists($table);
    }
    }

    private function createTablesIfNotExist(): void
    {
        global $gDbType;

        switch ($gDbType) {
            case 'pgsql':
        $this->createTableIfNotExist(TBL_RE_INVOICES, self::TABLE_DEFINITION_PGSQL_RE_INVOICES);
        $this->createUniqueIndexIfNotExist(TBL_RE_INVOICES, self::INVOICES_UNIQUE_INDEX_NUMBER);
        $this->createUniqueIndexIfNotExist(TBL_RE_INVOICES, self::INVOICES_UNIQUE_INDEX_NUMBER_INDEX);
        $this->createConstraintsIfNotExist(TBL_RE_INVOICES, self::INVOICES_CONSTRAINTS);

        $this->createTableIfNotExist(TBL_RE_INVOICES_HIST, self::TABLE_DEFINITION_PGSQL_RE_INVOICES_HIST);

        $this->createTableIfNotExist(TBL_RE_INVOICE_ITEMS, self::TABLE_DEFINITION_PGSQL_RE_INVOICE_ITEMS);
        $this->createIndexIfNotExist(TBL_RE_INVOICE_ITEMS, self::ITEMS_INDEXES);
        $this->createIndexIfNotExist(TBL_RE_INVOICE_ITEMS, self::ITEMS_INDEX_CHG_ID);
        $this->createConstraintsIfNotExist(TBL_RE_INVOICE_ITEMS, self::ITEMS_CONSTRAINTS);

        $this->createTableIfNotExist(TBL_RE_INVOICE_ITEMS_HIST, self::TABLE_DEFINITION_PGSQL_RE_INVOICE_ITEMS_HIST);
        $this->createIndexIfNotExist(TBL_RE_INVOICE_ITEMS_HIST, self::ITEMS_INDEX_CHG_ID);

        $this->createTableIfNotExist(TBL_RE_PAYMENTS, self::TABLE_DEFINITION_PGSQL_RE_PAYMENTS);
        $this->createConstraintsIfNotExist(TBL_RE_PAYMENTS, self::PAYMENTS_CONSTRAINTS);

        $this->createTableIfNotExist(TBL_RE_PAYMENTS_HIST, self::TABLE_DEFINITION_PGSQL_RE_PAYMENTS_HIST);

        $this->createTableIfNotExist(TBL_RE_PAYMENT_ITEMS, self::TABLE_DEFINITION_PGSQL_RE_PAYMENT_ITEMS);
        $this->createIndexIfNotExist(TBL_RE_PAYMENT_ITEMS, self::PAYMENT_ITEMS_INDEXES);
        $this->createConstraintsIfNotExist(TBL_RE_PAYMENT_ITEMS, self::PAYMENT_ITEMS_CONSTRAINTS);

        $this->createTableIfNotExist(TBL_RE_PAYMENT_ITEMS_HIST, self::TABLE_DEFINITION_PGSQL_RE_PAYMENT_ITEMS_HIST);

        $this->createTableIfNotExist(TBL_RE_TRANS, self::TABLE_DEFINITION_PGSQL_RE_TRANS);
        $this->createConstraintsIfNotExist(TBL_RE_TRANS, self::PG_PAYMENTS_CONSTRAINTS);

        $this->createTableIfNotExist(TBL_RE_TRANS_ITEMS, self::TABLE_DEFINITION_PGSQL_RE_TRANS_ITEMS);
        $this->createIndexIfNotExist(TBL_RE_TRANS_ITEMS, self::TRANS_ITEMS_INDEXES);
        $this->createConstraintsIfNotExist(TBL_RE_TRANS_ITEMS, self::TRANS_ITEMS_CONSTRAINTS);

        $this->createTableIfNotExist(TBL_RE_CHARGES, self::TABLE_DEFINITION_PGSQL_RE_CHARGES);
        $this->createTableIfNotExist(TBL_RE_CHARGES_HIST, self::TABLE_DEFINITION_PGSQL_RE_CHARGES_HIST);

        $this->createTableIfNotExist(TBL_RE_DEVICES, self::TABLE_DEFINITION_PGSQL_RE_DEVICES);
        $this->createTableIfNotExist(TBL_RE_DEVICES_HIST, self::TABLE_DEFINITION_PGSQL_RE_DEVICES_HIST);
        break;

            case 'mysql':
            default:
        $this->createTableIfNotExist(TBL_RE_INVOICES, self::TABLE_DEFINITION_MYSQL_RE_INVOICES);
        $this->createUniqueIndexIfNotExist(TBL_RE_INVOICES, self::INVOICES_UNIQUE_INDEX_NUMBER);
        $this->createUniqueIndexIfNotExist(TBL_RE_INVOICES, self::INVOICES_UNIQUE_INDEX_NUMBER_INDEX);
        $this->createConstraintsIfNotExist(TBL_RE_INVOICES, self::INVOICES_CONSTRAINTS);

        $this->createTableIfNotExist(TBL_RE_INVOICES_HIST, self::TABLE_DEFINITION_MYSQL_RE_INVOICES_HIST);

        $this->createTableIfNotExist(TBL_RE_INVOICE_ITEMS, self::TABLE_DEFINITION_MYSQL_RE_INVOICE_ITEMS);
        $this->createIndexIfNotExist(TBL_RE_INVOICE_ITEMS, self::ITEMS_INDEXES);
        $this->createIndexIfNotExist(TBL_RE_INVOICE_ITEMS, self::ITEMS_INDEX_CHG_ID);
        $this->createConstraintsIfNotExist(TBL_RE_INVOICE_ITEMS, self::ITEMS_CONSTRAINTS);

        $this->createTableIfNotExist(TBL_RE_INVOICE_ITEMS_HIST, self::TABLE_DEFINITION_MYSQL_RE_INVOICE_ITEMS_HIST);
        $this->createIndexIfNotExist(TBL_RE_INVOICE_ITEMS_HIST, self::ITEMS_INDEX_CHG_ID);

        $this->createTableIfNotExist(TBL_RE_PAYMENTS, self::TABLE_DEFINITION_MYSQL_RE_PAYMENTS);
        $this->createConstraintsIfNotExist(TBL_RE_PAYMENTS, self::PAYMENTS_CONSTRAINTS);

        $this->createTableIfNotExist(TBL_RE_PAYMENTS_HIST, self::TABLE_DEFINITION_MYSQL_RE_PAYMENTS_HIST);

        $this->createTableIfNotExist(TBL_RE_PAYMENT_ITEMS, self::TABLE_DEFINITION_MYSQL_RE_PAYMENT_ITEMS);
        $this->createIndexIfNotExist(TBL_RE_PAYMENT_ITEMS, self::PAYMENT_ITEMS_INDEXES);
        $this->createConstraintsIfNotExist(TBL_RE_PAYMENT_ITEMS, self::PAYMENT_ITEMS_CONSTRAINTS);

        $this->createTableIfNotExist(TBL_RE_PAYMENT_ITEMS_HIST, self::TABLE_DEFINITION_MYSQL_RE_PAYMENT_ITEMS_HIST);

        $this->createTableIfNotExist(TBL_RE_TRANS, self::TABLE_DEFINITION_MYSQL_RE_TRANS);
        $this->createConstraintsIfNotExist(TBL_RE_TRANS, self::PG_PAYMENTS_CONSTRAINTS);

        $this->createTableIfNotExist(TBL_RE_TRANS_ITEMS, self::TABLE_DEFINITION_MYSQL_RE_TRANS_ITEMS);
        $this->createIndexIfNotExist(TBL_RE_TRANS_ITEMS, self::TRANS_ITEMS_INDEXES);
        $this->createConstraintsIfNotExist(TBL_RE_TRANS_ITEMS, self::TRANS_ITEMS_CONSTRAINTS);

        $this->createTableIfNotExist(TBL_RE_CHARGES, self::TABLE_DEFINITION_MYSQL_RE_CHARGES);
        $this->createTableIfNotExist(TBL_RE_CHARGES_HIST, self::TABLE_DEFINITION_MYSQL_RE_CHARGES_HIST);

        $this->createTableIfNotExist(TBL_RE_DEVICES, self::TABLE_DEFINITION_MYSQL_RE_DEVICES);
        $this->createTableIfNotExist(TBL_RE_DEVICES_HIST, self::TABLE_DEFINITION_MYSQL_RE_DEVICES_HIST);
    }
    }

    private function createTableIfNotExist(string $tableName, string $tableDefinition): void
    {
        global $gDb, $gDbType;

        if (!tableExistsRE($tableName)) {
            if ($gDbType === 'pgsql') {
                $sql = 'CREATE TABLE ' . $tableName . ' (' . $tableDefinition . ');';
            } else {
                $sql = 'CREATE TABLE ' . $tableName . ' (' . $tableDefinition . ') ENGINE = InnoDB DEFAULT CHARACTER SET = utf8 COLLATE = utf8_unicode_ci;';
            }

            $gDb->query($sql);
    }
    }

    private function createUniqueIndexIfNotExist(string $tableName, string $indexDefinition): void
    {
        global $gDb;
        $indexName = '';

        if (preg_match('/CREATE UNIQUE INDEX (\S+) ON/', $indexDefinition, $matches)) {
            $indexName = $matches[1];
    }

        if ($indexName !== '' && !indexExistsRE($tableName, $indexName)) {
            $gDb->query($indexDefinition);
    }
    }

    private function createIndexIfNotExist(string $tableName, string $indexDefinition): void
    {
        global $gDb;
        $indexName = '';

        if (preg_match('/CREATE INDEX (\S+) ON/', $indexDefinition, $matches)) {
            $indexName = $matches[1];
    }

        if ($indexName !== '' && !indexExistsRE($tableName, $indexName)) {
            $gDb->query($indexDefinition);
    }
    }

    private function createConstraintsIfNotExist(string $tableName, string $constraintsDefinition): void
    {
        global $gDb;
        $constraints = array_filter(array_map('trim', explode(',', $constraintsDefinition)));

        foreach ($constraints as $constraint) {
            if (preg_match('/ADD CONSTRAINT (\S+) FOREIGN KEY/', $constraint, $matches)) {
                $name = $matches[1];

                if (!constraintExistsRE($tableName, $name)) {
                    $gDb->query('ALTER TABLE ' . $tableName . ' ' . $constraint);
    }
            }
    }
    }

    private function dropTableIfExists(string $tableName): void
    {
        global $gDb;

        if (tableExistsRE($tableName)) {
            $gDb->query('DROP TABLE ' . $tableName);
    }
    }
}
