CREATE DATABASE IF NOT EXISTS spendee;
USE spendee;

-- 1) USERS
CREATE TABLE users (
  user_id INT PRIMARY KEY AUTO_INCREMENT,
  first_name VARCHAR(100) NOT NULL,
  last_name  VARCHAR(100) NOT NULL,
  username   VARCHAR(100) UNIQUE,
  birth_date DATE NULL,
  gender ENUM('Male','Female','Other') NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  membership ENUM('general','premium') NOT NULL DEFAULT 'general',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2) ROLES (optional, you can even hardcode roles and drop this)
CREATE TABLE roles (
  role_id SMALLINT PRIMARY KEY AUTO_INCREMENT,
  role_name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE user_roles (
  user_id INT NOT NULL,
  role_id SMALLINT NOT NULL,
  PRIMARY KEY (user_id, role_id),
  FOREIGN KEY (user_id) REFERENCES users(user_id),
  FOREIGN KEY (role_id) REFERENCES roles(role_id)
) ENGINE=InnoDB;

-- Seed basic roles
INSERT IGNORE INTO roles (role_name) VALUES ('user'), ('support');

-- 3) WALLETS
CREATE TABLE wallets (
  wallet_id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  wallet_name VARCHAR(120) NOT NULL,
  wallet_type ENUM('Cash','Bank','Mobile','Card','Savings','Other') NOT NULL,
  currency_code CHAR(3) NOT NULL DEFAULT 'BDT',
  card_brand VARCHAR(20) NULL,
  card_last4 VARCHAR(4) NULL,
  balance DECIMAL(12,2) NOT NULL DEFAULT 0, -- cached balance
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id),
  UNIQUE (user_id, wallet_name)
) ENGINE=InnoDB;

-- 4) CATEGORIES
CREATE TABLE categories (
  category_id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  category_name VARCHAR(120) NOT NULL,
  category_type ENUM('Income','Expense') NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(user_id),
  UNIQUE (user_id, category_name, category_type)
) ENGINE=InnoDB;

-- 5) TRANSACTIONS
CREATE TABLE transactions (
  transaction_id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  wallet_id INT NOT NULL,
  category_id INT NULL,
  transaction_type ENUM('Income','Expense','Transfer') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency_code CHAR(3) NOT NULL DEFAULT 'BDT',
  transaction_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  note VARCHAR(255) NULL,
  FOREIGN KEY (user_id) REFERENCES users(user_id),
  FOREIGN KEY (wallet_id) REFERENCES wallets(wallet_id),
  FOREIGN KEY (category_id) REFERENCES categories(category_id),
  INDEX (user_id, transaction_time),
  INDEX (wallet_id, transaction_time)
) ENGINE=InnoDB;

-- optional: proper transfer relation (keeps transfers clean)
CREATE TABLE transfers (
  transfer_id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  from_wallet_id INT NOT NULL,
  to_wallet_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  transfer_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  note VARCHAR(255) NULL,
  FOREIGN KEY (user_id) REFERENCES users(user_id),
  FOREIGN KEY (from_wallet_id) REFERENCES wallets(wallet_id),
  FOREIGN KEY (to_wallet_id) REFERENCES wallets(wallet_id)
) ENGINE=InnoDB;

-- 6) BUDGET (simple)
CREATE TABLE budgets (
  budget_id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id),
  CHECK (end_date >= start_date)
) ENGINE=InnoDB;

CREATE TABLE budget_items (
  budget_item_id INT PRIMARY KEY AUTO_INCREMENT,
  budget_id INT NOT NULL,
  category_id INT NOT NULL,
  amount_limit DECIMAL(12,2) NOT NULL,
  currency_code CHAR(3) NOT NULL DEFAULT 'BDT',
  FOREIGN KEY (budget_id) REFERENCES budgets(budget_id),
  FOREIGN KEY (category_id) REFERENCES categories(category_id),
  UNIQUE (budget_id, category_id)
) ENGINE=InnoDB;

-- 7) NOTIFICATIONS
CREATE TABLE notifications (
  notification_id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  message VARCHAR(255) NOT NULL,
  is_read BOOLEAN NOT NULL DEFAULT FALSE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id),
  INDEX (user_id, created_at)
) ENGINE=InnoDB;

-- 7b) SUPPORT REPORTS (user reports + support solutions)
CREATE TABLE support_reports (
  report_id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NULL,
  username VARCHAR(100) NULL,
  email VARCHAR(255) NULL,
  report_type ENUM('bug','feature','security','account','other') NOT NULL DEFAULT 'other',
  message TEXT NOT NULL,
  status ENUM('Open','In Progress','Resolved') NOT NULL DEFAULT 'Open',
  solution TEXT NULL,
  handled_by INT NULL,
  handled_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
  FOREIGN KEY (handled_by) REFERENCES users(user_id) ON DELETE SET NULL,
  INDEX (status, created_at),
  INDEX (user_id, created_at)
) ENGINE=InnoDB;

-- 8) BANKING (reduced)
CREATE TABLE banks (
  bank_id INT PRIMARY KEY AUTO_INCREMENT,
  bank_name VARCHAR(255) NOT NULL,
  branch_name VARCHAR(255) NOT NULL,
  routing_number VARCHAR(50) NOT NULL,
  UNIQUE (bank_name, branch_name)
) ENGINE=InnoDB;

CREATE TABLE bank_accounts (
  bank_account_id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  bank_id INT NOT NULL,
  account_number VARCHAR(30) NOT NULL UNIQUE,
  account_type VARCHAR(25) NOT NULL,
  balance DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('Active','Frozen','Closed') NOT NULL DEFAULT 'Active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id),
  FOREIGN KEY (bank_id) REFERENCES banks(bank_id)
) ENGINE=InnoDB;

CREATE TABLE bank_cards (
  card_id BIGINT PRIMARY KEY AUTO_INCREMENT,
  bank_account_id BIGINT NOT NULL,
  card_number VARCHAR(30) NOT NULL UNIQUE,
  card_type VARCHAR(50) NOT NULL,
  expiry_date DATE NOT NULL,
  FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(bank_account_id)
) ENGINE=InnoDB;

CREATE TABLE atm_transactions (
  atm_txn_id BIGINT PRIMARY KEY AUTO_INCREMENT,
  card_id BIGINT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  atm_location VARCHAR(255) NOT NULL,
  txn_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (card_id) REFERENCES bank_cards(card_id)
) ENGINE=InnoDB;

-- 9) KYC
CREATE TABLE kyc (
  kyc_id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL UNIQUE,
  nid_number VARCHAR(30) NOT NULL UNIQUE,
  status ENUM('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- 10) SUBSCRIPTIONS (reduced)
CREATE TABLE subscription_services (
  service_id INT PRIMARY KEY AUTO_INCREMENT,
  service_name VARCHAR(255) NOT NULL UNIQUE,
  service_type VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE subscriptions (
  subscription_id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  wallet_id INT NOT NULL,
  service_id INT NOT NULL,
  start_date DATE NOT NULL,
  renewal_date DATE NOT NULL,
  billing_cycle VARCHAR(50) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  status ENUM('Active','Paused','Cancelled') NOT NULL DEFAULT 'Active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id),
  FOREIGN KEY (wallet_id) REFERENCES wallets(wallet_id),
  FOREIGN KEY (service_id) REFERENCES subscription_services(service_id)
) ENGINE=InnoDB;

CREATE TABLE subscription_payments (
  subscription_payment_id BIGINT PRIMARY KEY AUTO_INCREMENT,
  subscription_id BIGINT NOT NULL,
  transaction_id BIGINT NULL,
  payment_date DATE NOT NULL,
  paid_amount DECIMAL(12,2) NOT NULL,
  payment_status ENUM('Pending','Paid','Failed') NOT NULL DEFAULT 'Paid',
  FOREIGN KEY (subscription_id) REFERENCES subscriptions(subscription_id),
  FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id)
) ENGINE=InnoDB;

-- 11) LOGIN + OTP + DEVICES (optional but useful)
CREATE TABLE login_sessions (
  session_id CHAR(36) PRIMARY KEY,
  user_id INT NOT NULL,
  login_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  logout_time DATETIME NULL,
  FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE devices (
  device_id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  device_type ENUM('Android','iOS','Web','Other') NOT NULL,
  last_login DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE otps (
  otp_id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  otp_code INT NOT NULL,
  purpose ENUM('Login','Register','ResetPassword','VerifyTransaction') NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- 12) BILL PAYMENTS (reduced)
CREATE TABLE utility_billers (
  biller_id INT PRIMARY KEY AUTO_INCREMENT,
  biller_name VARCHAR(255) NOT NULL UNIQUE,
  bill_type ENUM('Electricity','Gas','Internet','Water','Other') NOT NULL
) ENGINE=InnoDB;

CREATE TABLE bill_payments (
  bill_payment_id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  biller_id INT NOT NULL,
  wallet_id INT NULL,
  amount DECIMAL(12,2) NOT NULL,
  payment_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status ENUM('Pending','Paid','Failed') NOT NULL DEFAULT 'Paid',
  FOREIGN KEY (user_id) REFERENCES users(user_id),
  FOREIGN KEY (biller_id) REFERENCES utility_billers(biller_id),
  FOREIGN KEY (wallet_id) REFERENCES wallets(wallet_id)
) ENGINE=InnoDB;
