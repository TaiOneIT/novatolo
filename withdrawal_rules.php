<?php

const MINIMUM_WITHDRAWAL_RWF = 3000;
const WITHDRAWAL_FEE_PERCENT = 9;

function ensureWithdrawalFeeColumn(PDO $pdo): void
{
    $column = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'withdrawal_fee'")->fetch();
    if (!$column) {
        try {
            $pdo->exec('ALTER TABLE transactions ADD COLUMN withdrawal_fee DECIMAL(15,2) NOT NULL DEFAULT 0.00');
        } catch (PDOException $exception) {
            if (!$pdo->query("SHOW COLUMNS FROM transactions LIKE 'withdrawal_fee'")->fetch()) {
                throw $exception;
            }
        }
    }
}
