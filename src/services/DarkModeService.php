<?php
/**
 * DarkModeService - Dark Mode Umschaltung
 *
 * Speichert die Dark-Mode-Praeferenz des Benutzers in der Datenbank.
 * AdminLTE unterstuetzt Dark Mode nativ ueber die CSS-Klasse 'dark-mode'.
 */
class DarkModeService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Dark Mode Status abrufen
     */
    public function isDarkMode(int $userId): bool
    {
        $this->db->where('users_userid', $userId);
        $result = $this->db->getOne('users', null, ['users_darkMode']);
        return $result ? (bool) ($result['users_darkMode'] ?? false) : false;
    }

    /**
     * Dark Mode umschalten
     */
    public function toggle(int $userId): bool
    {
        $current = $this->isDarkMode($userId);
        $newValue = $current ? 0 : 1;

        $this->db->where('users_userid', $userId);
        $this->db->update('users', ['users_darkMode' => $newValue]);

        return (bool) $newValue;
    }

    /**
     * Dark Mode setzen
     */
    public function set(int $userId, bool $enabled): void
    {
        $this->db->where('users_userid', $userId);
        $this->db->update('users', ['users_darkMode' => $enabled ? 1 : 0]);
    }

    /**
     * CSS-Klasse fuer body-Tag
     */
    public static function getBodyClass(bool $darkMode): string
    {
        return $darkMode ? 'dark-mode' : '';
    }
}
