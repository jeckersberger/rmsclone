<?php
/**
 * PasswordPolicyService - Passwortrichtlinien und -validierung
 *
 * Prueft Passwoerter gegen definierte Richtlinien:
 * - Mindestlaenge 10 Zeichen
 * - Gross-/Kleinbuchstaben, Ziffern, Sonderzeichen
 * - Pruefung gegen eine Liste haeufiger Passwoerter
 */
class PasswordPolicyService
{
    const MIN_PASSWORD_LENGTH = 10;

    /**
     * Validiert ein Passwort und gibt ein Array von Verstoessen zurueck.
     * Leeres Array = Passwort ist konform.
     */
    public static function validate(string $password): array
    {
        $violations = [];

        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $violations[] = 'Mindestens ' . self::MIN_PASSWORD_LENGTH . ' Zeichen erforderlich.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $violations[] = 'Mindestens ein Grossbuchstabe erforderlich.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $violations[] = 'Mindestens ein Kleinbuchstabe erforderlich.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $violations[] = 'Mindestens eine Ziffer erforderlich.';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $violations[] = 'Mindestens ein Sonderzeichen erforderlich (z.B. !@#$%^&*).';
        }

        $breached = self::checkBreached($password);
        if ($breached) {
            $violations[] = 'Dieses Passwort ist zu haeufig und unsicher. Bitte waehlen Sie ein anderes.';
        }

        return $violations;
    }

    /**
     * Gibt die Anforderungen als lesbaren Text zurueck (Deutsch).
     */
    public static function getRequirements(): array
    {
        return [
            'Mindestens ' . self::MIN_PASSWORD_LENGTH . ' Zeichen',
            'Mindestens ein Grossbuchstabe (A-Z)',
            'Mindestens ein Kleinbuchstabe (a-z)',
            'Mindestens eine Ziffer (0-9)',
            'Mindestens ein Sonderzeichen (!@#$%^&* etc.)',
            'Darf kein gaengiges Passwort sein',
        ];
    }

    /**
     * Prueft ob das Passwort in der Top-100-Liste haeufiger Passwoerter vorkommt.
     */
    public static function checkBreached(string $password): bool
    {
        $lower = strtolower($password);
        $common = [
            'password', '123456', '12345678', 'qwerty', 'abc123', 'monkey', '1234567',
            'letmein', 'trustno1', 'dragon', 'baseball', 'iloveyou', 'master', 'sunshine',
            'ashley', 'bailey', 'shadow', '123123', '654321', 'superman', 'qazwsx',
            'michael', 'football', 'password1', 'password123', '1234567890', '123456789',
            '000000', 'charlie', 'donald', 'princess', 'admin', 'welcome', 'login',
            'starwars', '121212', 'flower', 'passw0rd', 'hello', 'cheese', 'photon',
            'hottie', 'loveme', 'zaq1zaq1', 'password2', 'qwerty123', 'test', 'testing',
            'qwer1234', 'killer', 'asshole', 'fuckyou', 'jordan', 'jennifer', 'hunter',
            'buster', 'soccer', 'harley', 'batman', 'andrew', 'tigger', 'ranger',
            'thomas', 'robert', 'soccer1', 'arsenal', 'access', 'mustang', 'letmein1',
            'joshua', 'george', 'computer', 'michelle', 'jessica', 'pepper', 'daniel',
            'hockey', 'ranger1', 'matrix', 'whatever', 'ginger', 'summer', 'corvette',
            'austin', 'merlin', 'diamond', 'hannah', 'freedom', 'falcon', '1q2w3e4r',
            'yankees', 'dallas', 'austin1', 'anthony', 'william', 'passwort', 'hallo',
            'schatz', 'geheim', 'passwort1', 'passwort123',
        ];

        return in_array($lower, $common, true);
    }
}
