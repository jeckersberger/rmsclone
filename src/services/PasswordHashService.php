<?php

/**
 * Zentraler Service fuer sicheres Password-Hashing.
 *
 * Migriert das bestehende hash($algo, $salt1.$pw.$salt2)-Schema
 * transparent auf password_hash() mit PASSWORD_ARGON2ID (oder bcrypt als Fallback).
 *
 * Konvention: users_hash = 'password_hash' bedeutet, dass users_password
 * einen password_hash()-String enthaelt und users_salty1/salty2 ignoriert werden.
 */
class PasswordHashService
{
    /** Marker-Wert fuer users_hash wenn password_hash() verwendet wird */
    const MODERN_HASH_MARKER = 'password_hash';

    /**
     * Erstellt einen neuen Passwort-Hash mit dem sichersten verfuegbaren Algorithmus.
     *
     * @param string $password Klartext-Passwort
     * @return array ['hash' => string, 'marker' => string]
     */
    public static function hashNew(string $password): array
    {
        // Argon2ID bevorzugen, bcrypt als Fallback
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;

        $hash = password_hash($password, $algo);
        if ($hash === false) {
            throw new \RuntimeException('Password hashing failed');
        }

        return [
            'hash' => $hash,
            'marker' => self::MODERN_HASH_MARKER,
        ];
    }

    /**
     * Verifiziert ein Passwort gegen den gespeicherten Hash.
     * Unterstuetzt sowohl das alte Schema (hash + salts) als auch das neue (password_hash).
     *
     * @param string $password       Eingegebenes Klartext-Passwort
     * @param string $storedHash     Gespeicherter Hash aus der DB (users_password)
     * @param string $hashAlgo       Gespeicherter Algorithmus (users_hash)
     * @param string $salt1          users_salty1 (nur fuer Legacy-Schema)
     * @param string $salt2          users_salty2 (nur fuer Legacy-Schema)
     * @return bool
     */
    public static function verify(
        string $password,
        string $storedHash,
        string $hashAlgo,
        string $salt1 = '',
        string $salt2 = ''
    ): bool {
        if ($hashAlgo === self::MODERN_HASH_MARKER) {
            // Neues Schema: password_verify()
            return password_verify($password, $storedHash);
        }

        // Legacy-Schema: hash($algo, $salt1 . $password . $salt2)
        $computed = hash($hashAlgo, $salt1 . $password . $salt2);
        return hash_equals($storedHash, $computed);
    }

    /**
     * Prueft ob ein User-Record auf das moderne Schema aktualisiert werden muss.
     *
     * @param string $hashAlgo  Aktueller users_hash Wert
     * @return bool true wenn ein Rehash noetig ist
     */
    public static function needsRehash(string $hashAlgo): bool
    {
        return $hashAlgo !== self::MODERN_HASH_MARKER;
    }

    /**
     * Erstellt die DB-Update-Daten fuer ein Passwort-Upgrade.
     * Setzt users_password auf den neuen Hash und users_hash auf den Marker.
     * Salts werden auf leer gesetzt (nicht mehr benoetigt, aber Spalten bleiben).
     *
     * @param string $password Klartext-Passwort
     * @return array Assoziatives Array fuer DB-Update
     */
    public static function upgradeData(string $password): array
    {
        $result = self::hashNew($password);
        return [
            'users_password' => $result['hash'],
            'users_hash' => $result['marker'],
            'users_salty1' => '',  // Nicht mehr benoetigt
            'users_salty2' => '',
        ];
    }
}
