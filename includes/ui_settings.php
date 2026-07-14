<?php
/**
 * ui_settings.php
 * Helpers to retrieve configurable UI labels (volume unit, currency) from the DB settings table.
 * Usage: require_once '../includes/ui_settings.php';
 *        $unit = getVolumeUnit($conn);      // e.g. "L"
 *        $currency = getCurrencySymbol($conn); // e.g. "Ksh"
 */

function getSetting(PDO $conn, string $key, $default = null) {
    try {
        $stmt = $conn->prepare("SELECT `value` FROM settings WHERE `key` = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($row !== false) ? $row['value'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

function getVolumeUnit(PDO $conn): string {
    return getSetting($conn, 'volume_unit', 'L');
}

function getCurrencySymbol(PDO $conn): string {
    return getSetting($conn, 'currency_symbol', 'Ksh');
}
