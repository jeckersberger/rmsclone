<?php
$configStructureArray = [
  "ROOTURL" => [
    "form" => [
      "type" => "url", // The type of field this is (secret, text, number, url, select)
      "default" => function () { // Default value for the text box
        return 'http://' . $_SERVER['HTTP_HOST'];
      },
      "name" => "Basis-URL", // The name of the field to be shown to the user
      "description" => "Die URL der Seite, die als Referenz fuer alle Links und E-Mails verwendet wird. Diese URL ist wichtig - bei falscher Konfiguration koennen Sie sich nicht mehr einloggen. Wahrscheinlich ist es https://ihredomain.de oder http://localhost:8080. Darf nicht mit einem Schraegstrich enden.", // A description of the field to be shown to the user
      "group" => "Allgemein", // The group this field belongs to
      "required" => true, // Is this value required? Or can it be left blank
      "maxlength" => 255, // This is the maximum length of the string (if of string type)
      "minlength" => 10, // This is the minimum length of the string (if of string type)
      "options" => [], // An array of options that can be selected for a select dropdown
      "verifyMatch" => function ($value, $options) { // A filter which takes the value provided by the user, the options array in the config, and returns an array with the following keys: valid, value, error
        $checkedValue = filter_var($value, FILTER_VALIDATE_URL);
        if ($checkedValue !== false) return ["valid" => true, "value" => rtrim($checkedValue, '/'), "error" => null];
        else return ["valid" => false, "value" => null, "error" => "Invalid URL"];
      },
    ],
    "specialRequest" => false, // Should this value by downloaded for every single pageload? True is reccomended for values that are used by every single page or are used by twig, and so should be taken from the database in a bulk call to improve performance
    "default" => false, // Default value if one is not in the database (false to fail if not in database)
    "envFallback" => "CONFIG_ROOTURL", // If the value isn't in the database, use this environment variable (false to not use one)
  ],
  "TIMEZONE" => [
    "form" => [
      "type" => "select",
      "default" => function () {
        return "Europe/London";
      },
      "name" => "Zeitzone",
      "group" => "Allgemein",
      "description" => "Die Zeitzone fuer das System",
      "required" => true,
      "maxlength" => 1000,
      "minlength" => 1,
      "options" => DateTimeZone::listIdentifiers(DateTimeZone::ALL),
      "verifyMatch" => function ($value, $options) {
        return ["valid" => in_array($value, $options), "value" => $value, "error" => in_array($value, $options) ? '' : "Invalid timezone"];
      }
    ],
    "specialRequest" => false,
    "default" => "Europe/London",
    "envFallback" => false,
  ],
  "EMAILS_ENABLED" => [
    "form" => [
      "type" => "select",
      "default" => function () {
        return "Disabled";
      },
      "name" => "E-Mail-Versand",
      "group" => "E-Mail",
      "description" => "Soll das System E-Mails an Benutzer senden? Wenn aktiviert, muss unten ein Anbieter konfiguriert werden. Diese Option erfordert auch, dass Benutzer ihre E-Mail-Adresse bei der Registrierung bestaetigen.",
      "required" => false,
      "maxlength" => 255,
      "minlength" => 5,
      "options" => ["Enabled", "Disabled"],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => in_array($value, $options), "value" => $value, "error" => in_array($value, $options) ? '' : "Invalid option selected"];
      }
    ],
    "specialRequest" => false,
    "default" => "Disabled",
    "envFallback" => "CONFIG_EMAILS_ENABLED",
  ],
  "EMAILS_PROVIDER" => [
    "form" => [
      "type" => "select",
      "default" => function () {
        return "Sendgrid";
      },
      "name" => "E-Mail-Anbieter",
      "group" => "E-Mail",
      "description" => "Welchen Anbieter soll das System fuer den E-Mail-Versand verwenden? Diese Option wird ignoriert, wenn der E-Mail-Versand deaktiviert ist.",
      "required" => false,
      "maxlength" => 255,
      "minlength" => 4,
      "options" => ["Sendgrid", "Mailgun", "Postmark", "SMTP"],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => in_array($value, $options), "value" => $value, "error" => in_array($value, $options) ? '' : "Invalid option selected"];
      }
    ],
    "specialRequest" => true,
    "default" => "Sendgrid",
    "envFallback" => "CONFIG_EMAILS_PROVIDER",
  ],
  "EMAILS_FROMEMAIL" => [
    "form" => [
      "type" => "email",
      "default" => function () {
        return "adamrms@example.com";
      },
      "name" => "Absender-E-Mail-Adresse",
      "group" => "E-Mail",
      "description" => "Die E-Mail-Adresse, von der E-Mails gesendet werden",
      "required" => false,
      "maxlength" => 255,
      "minlength" => 0,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        $checkedValue = filter_var($value, FILTER_VALIDATE_EMAIL);
        if ($checkedValue) return ["valid" => true, "value" => $checkedValue, "error" => null];
        else return ["valid" => false, "value" => "", "error" => "Invalid email address"];
      }
    ],
    "specialRequest" => true,
    "default" => false,
    "envFallback" => "CONFIG_EMAILS_FROM_EMAIL",
  ],
  "EMAILS_PROVIDERS_APIKEY" => [
    "form" => [
      "type" => "secret",
      "default" => function () {
        return "";
      },
      "name" => "E-Mail-Dienst API-Schluessel",
      "group" => "E-Mail",
      "description" => "Wenn Sendgrid, Mailgun oder Postmark oben ausgewaehlt wurde, der API-Schluessel fuer den E-Mail-Versand",
      "required" => false,
      "maxlength" => 255,
      "minlength" => 0,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => true, "value" => $value, "error" => null];
      }
    ],
    "specialRequest" => true,
    "default" => false,
    "envFallback" => "bCMS__SendGridAPIKEY",
  ],
  "EMAILS_PROVIDERS_MAILGUN_LOCATION" => [
    "form" => [
      "type" => "select",
      "default" => function () {
        return "";
      },
      "name" => "Mailgun Server-Standort",
      "group" => "E-Mail",
      "description" => "Wenn Mailgun oben ausgewaehlt wurde, ob US- oder EU-Mailgun-Server verwendet werden sollen",
      "required" => false,
      "maxlength" => 255,
      "minlength" => 0,
      "options" => ["US", "EU"],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => in_array($value, $options), "value" => $value, "error" => in_array($value, $options) ? '' : "Invalid option selected"];
      }
    ],
    "specialRequest" => true,
    "default" => false,
    "envFallback" => false,
  ],
  "EMAILS_SMTP_SERVER" => [
    "form" => [
      "type" => "text",
      "default" => function () {
        return "smtp.example.com";
      },
      "name" => "SMTP-Serveradresse",
      "group" => "E-Mail",
      "description" => "Wenn SMTP oben ausgewaehlt wurde, der SMTP-Server fuer den E-Mail-Versand",
      "required" => false,
      "maxlength" => 255,
      "minlength" => 0,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => true, "value" => $value, "error" => null];
      }
    ],
    "specialRequest" => true,
    "default" => false,
    "envFallback" => "CONFIG_EMAILS_SMTP_SERVER",
  ],
  "EMAILS_SMTP_USERNAME" => [
    "form" => [
      "type" => "text",
      "default" => function () {
        return "user@example.com";
      },
      "name" => "SMTP-Benutzername",
      "group" => "E-Mail",
      "description" => "Wenn SMTP oben ausgewaehlt wurde, der Benutzername fuer die Verbindung zum SMTP-Server",
      "required" => false,
      "maxlength" => 255,
      "minlength" => 0,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => true, "value" => $value, "error" => null];
      }
    ],
    "specialRequest" => true,
    "default" => false,
    "envFallback" => false,
  ],
  "EMAILS_SMTP_PASSWORD" => [
    "form" => [
      "type" => "secret",
      "default" => function () {
        return "password";
      },
      "name" => "SMTP-Passwort",
      "group" => "E-Mail",
      "description" => "Wenn SMTP oben ausgewaehlt wurde, das Passwort fuer die Verbindung zum SMTP-Server",
      "required" => false,
      "maxlength" => 255,
      "minlength" => 0,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => true, "value" => $value, "error" => null];
      }
    ],
    "specialRequest" => true,
    "default" => false,
    "envFallback" => false,
  ],
  "EMAILS_SMTP_PORT" => [
    "form" => [
      "type" => "number",
      "default" => function () {
        return 465;
      },
      "name" => "SMTP-Port",
      "group" => "E-Mail",
      "description" => "Wenn SMTP oben ausgewaehlt wurde, der Port fuer die Verbindung zum SMTP-Server",
      "required" => false,
      "maxlength" => 255,
      "minlength" => 0,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => true, "value" => $value, "error" => null];
      }
    ],
    "specialRequest" => true,
    "default" => 465,
    "envFallback" => "CONFIG_EMAILS_SMTP_PORT",
  ],
  "EMAILS_SMTP_ENCRYPTION" => [
    "form" => [
      "type" => "select",
      "default" => function () {
        return "SSL";
      },
      "name" => "SMTP-Verschluesselung",
      "group" => "E-Mail",
      "description" => "Wenn SMTP oben ausgewaehlt wurde, der Verschluesselungstyp fuer die Verbindung zum SMTP-Server",
      "required" => false,
      "maxlength" => 255,
      "minlength" => 0,
      "options" => ["None", "SSL", "TLS"],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => in_array($value, $options), "value" => $value, "error" => in_array($value, $options) ? '' : "Invalid option selected"];
      }
    ],
    "specialRequest" => true,
    "default" => "SSL",
    "envFallback" => false,
  ],
  "EMAILS_FOOTER" => [
    "form" => [
      "type" => "text",
      "default" => function () {
        return null;
      },
      "name" => "E-Mail-Fusszeile",
      "group" => "E-Mail",
      "description" => "Fusszeile fuer E-Mails.",
      "required" => false,
      "maxlength" => 65535,
      "minlength" => 0,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => true, "value" => $value, "error" => null];
      }
    ],
    "specialRequest" => true,
    "default" => "",
    "envFallback" => false,
  ],
  "IMAP_ENABLED" => [
    "form" => [
      "type" => "select",
      "default" => function () {
        return "Disabled";
      },
      "name" => "E-Mail Empfang (IMAP)",
      "group" => "Email",
      "description" => "Sollen eingehende E-Mails ueber IMAP abgerufen werden? Wenn aktiviert, muessen die IMAP-Zugangsdaten unten konfiguriert werden.",
      "required" => false,
      "maxlength" => 255,
      "minlength" => 5,
      "options" => ["Enabled", "Disabled"],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => in_array($value, $options), "value" => $value, "error" => in_array($value, $options) ? '' : "Invalid option selected"];
      }
    ],
    "specialRequest" => false,
    "default" => "Disabled",
    "envFallback" => "CONFIG_IMAP_ENABLED",
  ],
  "IMAP_SERVER" => [
    "form" => [
      "type" => "text",
      "default" => function () {
        return "imap.example.com";
      },
      "name" => "IMAP Server",
      "group" => "Email",
      "description" => "Der IMAP-Server zum Abrufen eingehender E-Mails (z.B. imap.example.com). Die Zugangsdaten werden in der Datenbank gespeichert.",
      "required" => false,
      "maxlength" => 255,
      "minlength" => 0,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => true, "value" => $value, "error" => null];
      }
    ],
    "specialRequest" => true,
    "default" => false,
    "envFallback" => "CONFIG_IMAP_SERVER",
  ],
  "IMAP_PORT" => [
    "form" => [
      "type" => "number",
      "default" => function () {
        return 993;
      },
      "name" => "IMAP Port",
      "group" => "Email",
      "description" => "Der Port fuer die IMAP-Verbindung. Standard: 993 (SSL) oder 143 (ohne SSL).",
      "required" => false,
      "maxlength" => 255,
      "minlength" => 0,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => true, "value" => $value, "error" => null];
      }
    ],
    "specialRequest" => true,
    "default" => 993,
    "envFallback" => "CONFIG_IMAP_PORT",
  ],
  "IMAP_ENCRYPTION" => [
    "form" => [
      "type" => "select",
      "default" => function () {
        return "SSL";
      },
      "name" => "IMAP Verschluesselung",
      "group" => "Email",
      "description" => "Verschluesselungstyp fuer die IMAP-Verbindung.",
      "required" => false,
      "maxlength" => 255,
      "minlength" => 0,
      "options" => ["None", "SSL", "TLS"],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => in_array($value, $options), "value" => $value, "error" => in_array($value, $options) ? '' : "Invalid option selected"];
      }
    ],
    "specialRequest" => true,
    "default" => "SSL",
    "envFallback" => false,
  ],
  "IMAP_USERNAME" => [
    "form" => [
      "type" => "text",
      "default" => function () {
        return "user@example.com";
      },
      "name" => "IMAP Benutzername",
      "group" => "Email",
      "description" => "Der Benutzername fuer die IMAP-Anmeldung (meistens die E-Mail-Adresse).",
      "required" => false,
      "maxlength" => 255,
      "minlength" => 0,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => true, "value" => $value, "error" => null];
      }
    ],
    "specialRequest" => true,
    "default" => false,
    "envFallback" => false,
  ],
  "IMAP_PASSWORD" => [
    "form" => [
      "type" => "secret",
      "default" => function () {
        return "";
      },
      "name" => "IMAP Passwort",
      "group" => "Email",
      "description" => "Das Passwort fuer die IMAP-Anmeldung.",
      "required" => false,
      "maxlength" => 255,
      "minlength" => 0,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => true, "value" => $value, "error" => null];
      }
    ],
    "specialRequest" => true,
    "default" => false,
    "envFallback" => false,
  ],
  "IMAP_FOLDER" => [
    "form" => [
      "type" => "text",
      "default" => function () {
        return "INBOX";
      },
      "name" => "IMAP Ordner",
      "group" => "Email",
      "description" => "Welcher Ordner soll abgerufen werden? Standard ist INBOX.",
      "required" => false,
      "maxlength" => 255,
      "minlength" => 0,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => true, "value" => $value, "error" => null];
      }
    ],
    "specialRequest" => true,
    "default" => "INBOX",
    "envFallback" => false,
  ],
  "IMAP_PROCESS_ATTACHMENTS" => [
    "form" => [
      "type" => "select",
      "default" => function () {
        return "Enabled";
      },
      "name" => "IMAP Anhaenge speichern",
      "group" => "Email",
      "description" => "Sollen E-Mail-Anhaenge (z.B. PDF-Rechnungen) automatisch heruntergeladen und gespeichert werden?",
      "required" => false,
      "maxlength" => 255,
      "minlength" => 5,
      "options" => ["Enabled", "Disabled"],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => in_array($value, $options), "value" => $value, "error" => in_array($value, $options) ? '' : "Invalid option selected"];
      }
    ],
    "specialRequest" => true,
    "default" => "Enabled",
    "envFallback" => false,
  ],
  "ERRORS_PROVIDERS_SENTRY" => [
    "form" => [
      "type" => "secret",
      "default" => function () {
        return "";
      },
      "name" => "Sentry.io API-Schluessel",
      "group" => "Fehlerbehandlung",
      "description" => "Der Sentry.io API-Schluessel zum Senden von Fehlerprotokollen - wird normalerweise nur fuer die Entwicklung benoetigt",
      "required" => false,
      "maxlength" => 255,
      "minlength" => 0,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => true, "value" => $value, "error" => null];
      }
    ],
    "specialRequest" => false,
    "default" => null,
    "envFallback" => "bCMS__SENTRYLOGIN",
  ],
  "AUTH_SIGNUP_ENABLED" => [
    "form" => [
      "type" => "select",
      "default" => function () {
        return "Enabled";
      },
      "name" => "Benutzerregistrierung",
      "group" => "Sicherheit & Anmeldung",
      "description" => "Koennen sich neue Benutzer selbst registrieren? Bei Deaktivierung ist keine Selbstregistrierung moeglich.",
      "required" => true,
      "maxlength" => 255,
      "minlength" => 5,
      "options" => ["Enabled", "Disabled"],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => in_array($value, $options), "value" => $value, "error" => in_array($value, $options) ? '' : "Invalid option selected"];
      }
    ],
    "specialRequest" => false,
    "default" => "Enabled",
    "envFallback" => "CONFIG_SIGNUP_ENABLED",
  ],
  "AUTH_JWTKey" => [
    "form" => [
      "type" => "secret",
      "default" => function () {
        return bin2hex(random_bytes(32));
      },
      "name" => "JWT-Schluessel",
      "group" => "Sicherheit & Anmeldung",
      "description" => "Der JWT-Schluessel zum Signieren von JWTs. Muss ein geheimer Zufallswert mit 64 Zeichen sein. Bei Ersteinrichtung ist der generierte Wert in Ordnung. Spaetere Aenderungen machen alle bestehenden JWTs ungueltig.",
      "required" => true,
      "maxlength" => 64,
      "minlength" => 64,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        $checkedValue = filter_var($value, FILTER_VALIDATE_REGEXP, ["options" => ["regexp" => "/^[A-Z0-9]+$/"]]);
        if ($checkedValue) return ["valid" => true, "value" => $checkedValue, "error" => null];
        else return ["valid" => false, "value" => null, "error" => "Invalid JWT key"];
      }
    ],
    "specialRequest" => false,
    "default" => false,
    "envFallback" => "CONFIG_AUTH_JWTKey",
  ],
  "AUTH_NEXTHASH" => [
    "form" => [
      "type" => "select",
      "default" => function () {
        return "sha256";
      },
      "name" => "Naechster Passwort-Hash-Algorithmus",
      "group" => "Sicherheit & Anmeldung",
      "description" => "Der Hash-Algorithmus fuer neue Passwoerter. Eine Aenderung erfordert keine Passwortaenderung durch Benutzer, aber der Algorithmus aendert sich beim naechsten Passwortwechsel.",
      "required" => false,
      "maxlength" => 6,
      "minlength" => 6,
      "options" => ["sha256", "sha512"],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => in_array($value, $options), "value" => $value, "error" => in_array($value, $options) ? '' : "Invalid hashing algorithm"];
      }
    ],
    "specialRequest" => false,
    "default" => "sha256",
    "envFallback" => false,
  ],
  "AUTH_PROVIDERS_GOOGLE_KEYS_ID" => [
    "form" => [
      "type" => "text",
      "default" => function () {
        return null;
      },
      "name" => "Google Auth Schluessel",
      "group" => "Authentifizierung",
      "description" => "Der ID-Schluessel fuer die Google-Authentifizierung. Bei der Konfiguration die Redirect-URIs auf https://IHREURL/login/oauth/google.php und https://IHREURL/api/account/oauth-link/google.php setzen.",
      "required" => false,
      "maxlength" => 100,
      "minlength" => 0,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => true, "value" => $value, "error" => ''];
      }
    ],
    "specialRequest" => true,
    "default" => false,
    "envFallback" => false,
  ],

  "AUTH_PROVIDERS_GOOGLE_KEYS_SECRET" => [
    "form" => [
      "type" => "text",
      "default" => function () {
        return null;
      },
      "name" => "Google Auth Geheimschluessel",
      "group" => "Authentifizierung",
      "description" => "Der geheime Schluessel fuer die Google-Authentifizierung.",
      "required" => false,
      "maxlength" => 100,
      "minlength" => 0,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => true, "value" => $value, "error" => ''];
      }
    ],
    "specialRequest" => true,
    "default" => false,
    "envFallback" => false,
  ],

  "AUTH_PROVIDERS_GOOGLE_SCOPE" => [
    "form" => [
      "type" => "text",
      "default" => function () {
        return 'https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email';
      },
      "name" => "Google Auth Bereich",
      "group" => "Authentifizierung",
      "description" => "Der Bereich fuer die Google-Authentifizierung. Normalerweise nur fuer Entwickler relevant.",
      "required" => false,
      "maxlength" => 255,
      "minlength" => 0,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => true, "value" => $value, "error" => ''];
      }
    ],
    "specialRequest" => true,
    "default" => 'https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email',
    "envFallback" => false,
  ],
  "AUTH_PROVIDERS_MICROSOFT_APP_ID" => [
    "form" => [
      "type" => "text",
      "default" => function () {
        return null;
      },
      "name" => "Microsoft Auth App-ID",
      "group" => "Authentifizierung",
      "description" => "Die App-ID fuer die Microsoft-Authentifizierung. Bei der Konfiguration die Redirect-URIs auf https://IHREURL/login/oauth/microsoft.php und https://IHREURL/api/account/oauth-link/microsoft.php setzen.",
      "required" => false,
      "maxlength" => 100,
      "minlength" => 0,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => true, "value" => $value, "error" => ''];
      }
    ],
    "specialRequest" => true,
    "default" => false,
    "envFallback" => false,
  ],

  "AUTH_PROVIDERS_MICROSOFT_KEYS_SECRET" => [
    "form" => [
      "type" => "text",
      "default" => function () {
        return null;
      },
      "name" => "Microsoft Auth Geheimschluessel",
      "group" => "Authentifizierung",
      "description" => "Der geheime Schluessel fuer die Microsoft-Authentifizierung.",
      "required" => false,
      "maxlength" => 100,
      "minlength" => 0,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => true, "value" => $value, "error" => ''];
      }
    ],
    "specialRequest" => true,
    "default" => false,
    "envFallback" => false,
  ],
  "PROJECT_NAME" => [
    "form" => [
      "type" => "text",
      "default" => function () {
        return "rmsclone";
      },
      "name" => "Projekt-Name",
      "description" => "Der Name Ihrer Installation (wird im Browser-Tab und in E-Mails angezeigt).",
      "group" => "Anpassung",
      "required" => false,
      "maxlength" => 20,
      "minlength" => 2,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        $checkedValue = filter_var($value, FILTER_VALIDATE_REGEXP, ["options" => ["regexp" => "/^[a-zA-Z0-9_ ]+$/"]]);
        if ($checkedValue) return ["valid" => true, "value" => $checkedValue, "error" => null];
        else return ["valid" => false, "value" => null, "error" => "Invalid name"];
      }
    ],
    "specialRequest" => false,
    "default" => "rmsclone",
    "envFallback" => "CONFIG_PROJECT_NAME",
  ],
  "LINKS_USERGUIDEURL" => [
    "form" => [
      "type" => "url",
      "default" => function () {
        return "";
      },
      "name" => "Benutzerhandbuch-URL",
      "group" => "Anpassung",
      "description" => "Die URL des Benutzerhandbuchs, auf die von den Hilfe-Buttons verlinkt wird",
      "required" => false,
      "maxlength" => 255,
      "minlength" => 0,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        $checkedValue = filter_var($value, FILTER_VALIDATE_URL);
        if ($checkedValue) return ["valid" => true, "value" => $checkedValue, "error" => null];
        else return ["valid" => false, "value" => "", "error" => "Invalid URL"];
      }
    ],
    "specialRequest" => false,
    "default" => "",
    "envFallback" => false,
  ],
  "LINKS_SUPPORTURL" => [
    "form" => [
      "type" => "url",
      "default" => function () {
        return "";
      },
      "name" => "Support-URL",
      "group" => "Anpassung",
      "description" => "Die URL fuer Links zur Support-Seite",
      "required" => false,
      "maxlength" => 255,
      "minlength" => 0,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        $checkedValue = filter_var($value, FILTER_VALIDATE_URL);
        if ($checkedValue) return ["valid" => true, "value" => $checkedValue, "error" => null];
        else return ["valid" => false, "value" => "", "error" => "Invalid URL"];
      }
    ],
    "specialRequest" => false,
    "default" => "",
    "envFallback" => false,
  ],
  "LINKS_TERMSOFSERVICEURL" => [
    "form" => [
      "type" => "url",
      "default" => function () {
        return null;
      },
      "name" => "AGB-URL",
      "group" => "Anpassung",
      "description" => "Die URL zur Seite mit den Allgemeinen Geschaeftsbedingungen. Wird auf der Login-Seite verlinkt. Ohne Angabe wird der Link nicht angezeigt.",
      "required" => false,
      "maxlength" => 255,
      "minlength" => 0,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        $checkedValue = filter_var($value, FILTER_VALIDATE_URL);
        if ($checkedValue) return ["valid" => true, "value" => $checkedValue, "error" => null];
        else return ["valid" => false, "value" => "", "error" => "Invalid URL"];
      }
    ],
    "specialRequest" => false,
    "default" => null,
    "envFallback" => false,
  ],
  "FOOTER_ANALYTICS" => [
    "form" => [
      "type" => "text",
      "default" => function () {
        return null;
      },
      "name" => "Analytics-Tracking-Code",
      "group" => "Anpassung",
      "description" => "Code, der in die Fusszeile aller Seiten eingefuegt wird, z.B. ein Google Analytics Tracking-Code",
      "required" => false,
      "maxlength" => 2000,
      "minlength" => 0,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => true, "value" => $value, "error" => ''];
      }
    ],
    "specialRequest" => false,
    "default" => null,
    "envFallback" => false,
  ],
  "FILES_ENABLED" => [
    "form" => [
      "type" => "select",
      "default" => function () {
        return "Enabled";
      },
      "name" => "Dateispeicher aktiviert",
      "group" => "Dateispeicher",
      "description" => "Ob der lokale Dateispeicher aktiviert oder deaktiviert ist. Bei Deaktivierung können Benutzer keine Dateien hochladen.",
      "required" => false,
      "maxlength" => 8,
      "minlength" => 7,
      "options" => ["Enabled", "Disabled"],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => in_array($value, $options), "value" => $value, "error" => in_array($value, $options) ? '' : "Ungültige Auswahl"];
      }
    ],
    "specialRequest" => false,
    "default" => "Enabled",
    "envFallback" => "CONFIG_FILES_ENABLED",
  ],
  "LOCAL_STORAGE_PATH" => [
    "form" => [
      "type" => "text",
      "default" => function () {
        return "/var/www/html/storage";
      },
      "name" => "Lokaler Speicherpfad",
      "group" => "Dateispeicher",
      "description" => "Der Dateisystempfad, in dem hochgeladene Dateien gespeichert werden. Stellen Sie sicher, dass dieser Pfad vom Webserver beschreibbar ist.",
      "required" => false,
      "maxlength" => 255,
      "minlength" => 1,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => true, "value" => rtrim($value, '/'), "error" => ''];
      }
    ],
    "specialRequest" => true,
    "default" => "/var/www/html/storage",
    "envFallback" => "LOCAL_STORAGE_PATH",
  ],
  "NEW_INSTANCE_ENABLED" => [
    "form" => [
      "type" => "select",
      "default" => function () {
        return "Enabled";
      },
      "name" => "Allen Benutzern neue Instanzen erlauben",
      "group" => "Abrechnung",
      "description" => "Steuert, ob Benutzer selbst neue Instanzen erstellen duerfen, oder ob dies von einem Administrator erledigt werden muss.",
      "required" => false,
      "maxlength" => 8,
      "minlength" => 7,
      "options" => ["Enabled", "Disabled"],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => in_array($value, $options), "value" => $value, "error" => in_array($value, $options) ? '' : "Invalid option selected"];
      }
    ],
    "specialRequest" => false,
    "default" => "Enabled",
    "envFallback" => false,
  ],
  "NEW_INSTANCE_SUSPENDED" => [
    "form" => [
      "type" => "select",
      "default" => function () {
        return "Do not suspend";
      },
      "name" => "Neue Instanzen standardmaessig sperren",
      "group" => "Abrechnung",
      "description" => "Ob neue Instanzen standardmaessig gesperrt werden sollen. Damit koennen neue Instanzen erst nach Pruefung durch einen Administrator oder nach Start einer Testphase freigeschaltet werden.",
      "required" => false,
      "maxlength" => 20,
      "minlength" => 1,
      "options" => ["Do not suspend", "Suspended"],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => in_array($value, $options), "value" => $value, "error" => in_array($value, $options) ? '' : "Invalid option selected"];
      }
    ],
    "specialRequest" => true,
    "default" => "Do not suspend",
    "envFallback" => false,
  ],
  "NEW_INSTANCE_SUSPENDED_REASON_TYPE" => [
    "form" => [
      "type" => "select",
      "default" => function () {
        return "other";
      },
      "name" => "Grund fuer die Sperrung neuer Instanzen",
      "group" => "Abrechnung",
      "description" => "Wenn eine neue Instanz ueber die obige Option gesperrt wird, was soll der Benutzer aufgefordert werden zu tun? Einen Plan einrichten, ein Abrechnungsproblem beheben, oder etwas anderes mit dem Text unten.",
      "required" => false,
      "maxlength" => 20,
      "minlength" => 1,
      "options" => ["noplan", "billing", "other"],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => in_array($value, $options), "value" => $value, "error" => in_array($value, $options) ? '' : "Invalid option selected"];
      }
    ],
    "specialRequest" => true,
    "default" => "other",
    "envFallback" => false,
  ],
  "NEW_INSTANCE_SUSPENDED_REASON" => [
    "form" => [
      "type" => "text",
      "default" => function () {
        return "as no subscription has been chosen.";
      },
      "name" => "Sperrgrund fuer neue Instanzen",
      "group" => "Abrechnung",
      "description" => "Welcher Grund soll dem Benutzer bei Sperrung angezeigt werden? Kann erklaeren, warum die Instanz gesperrt ist und was getan werden muss.",
      "required" => false,
      "maxlength" => 180,
      "minlength" => 0,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => true, "value" => $value, "error" => ''];
      }
    ],
    "specialRequest" => true,
    "default" => "",
    "envFallback" => false,
  ],
  "STRIPE_KEY" => [
    "form" => [
      "type" => "text",
      "default" => function () {
        return null;
      },
      "name" => "Stripe-Schluessel",
      "group" => "Abrechnung",
      "description" => "Der Stripe-Schluessel fuer die Abrechnungsunterstuetzung. Leer lassen um Stripe-Abrechnung zu deaktivieren. Benoetigt Berechtigungen fuer Billing Portal, Preise, Sitzungen und Produkte.",
      "required" => false,
      "maxlength" => 255,
      "minlength" => 0,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => true, "value" => $value, "error" => ''];
      }
    ],
    "specialRequest" => true,
    "default" => false,
    "envFallback" => false,
  ],
  "STRIPE_WEBHOOK_SECRET"  => [
    "form" => [
      "type" => "text",
      "default" => function () {
        return null;
      },
      "name" => "Stripe Webhook-Geheimschluessel",
      "group" => "Abrechnung",
      "description" => "Der geheime Schluessel fuer Stripe-Webhooks.",
      "required" => false,
      "maxlength" => 255,
      "minlength" => 0,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => true, "value" => $value, "error" => ''];
      }
    ],
    "specialRequest" => true,
    "default" => false,
    "envFallback" => false,
  ],
  "TELEMETRY_MODE" => [
    "form" => [
      "type" => "select",
      "default" => function () {
        return "Standard";
      },
      "name" => "Telemetrie reduzieren",
      "group" => "Telemetrie",
      "description" => "Welches Telemetrie-Level soll erfasst werden? Bei 'Limited' werden weniger Informationen an den Telemetrie-Server gesendet. Weitere Details: https://telemetry.bithell.studio/privacy-and-security",
      "required" => false,
      "maxlength" => 10,
      "minlength" => 5,
      "options" => ["Standard", "Limited"],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => in_array($value, $options), "value" => $value, "error" => in_array($value, $options) ? '' : "Invalid option selected"];
      }
    ],
    "specialRequest" => true,
    "default" => "Standard",
    "envFallback" => false,
  ],
  "TELEMETRY_SHOW_URL" => [
    "form" => [
      "type" => "select",
      "default" => function () {
        return "Enabled";
      },
      "name" => "Installations-URL in Liste anzeigen",
      "group" => "Telemetrie",
      "description" => "Soll die URL dieser Installation in der Installationsliste des Telemetrie-Servers angezeigt werden? Bei Deaktivierung wird die Installation weiterhin gezaehlt, aber URL und Notizen werden nicht oeffentlich angezeigt.",
      "required" => false,
      "maxlength" => 10,
      "minlength" => 5,
      "options" => ["Enabled", "Disabled"],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => in_array($value, $options), "value" => $value, "error" => in_array($value, $options) ? '' : "Invalid option selected"];
      }
    ],
    "specialRequest" => true,
    "default" => "Enabled",
    "envFallback" => false,
  ],
  "TELEMETRY_NOTES"  => [
    "form" => [
      "type" => "text",
      "default" => function () {
        return null;
      },
      "name" => "Telemetrie-Installationsnotizen",
      "group" => "Telemetrie",
      "description" => "Eine Notiz fuer das oeffentliche Telemetrie-Dashboard zu dieser Installation, z.B. der Firmenname. Wird nur oeffentlich angezeigt, wenn die obige Option (URL anzeigen) aktiviert ist.",
      "required" => false,
      "maxlength" => 255,
      "minlength" => 0,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => true, "value" => $value, "error" => ''];
      }
    ],
    "specialRequest" => true,
    "default" => false,
    "envFallback" => false,
  ],
  // ── Update-Einstellungen ──
  "UPDATE_GIT_REMOTE" => [
    "form" => [
      "type" => "text",
      "default" => function () {
        return "origin";
      },
      "name" => "Git Remote Name",
      "group" => "Updates",
      "description" => "Name des Git-Remotes fuer Updates (Standard: origin). Aendern Sie dies nur, wenn Sie einen anderen Remote verwenden.",
      "required" => true,
      "maxlength" => 50,
      "minlength" => 1,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        $v = preg_match('/^[a-zA-Z0-9_-]+$/', $value);
        return ["valid" => (bool)$v, "value" => $value, "error" => $v ? '' : 'Ungueltiger Remote-Name'];
      }
    ],
    "specialRequest" => false,
    "default" => "origin",
    "envFallback" => "UPDATE_GIT_REMOTE",
  ],
  "UPDATE_GIT_BRANCH" => [
    "form" => [
      "type" => "text",
      "default" => function () {
        return "main";
      },
      "name" => "Git Branch",
      "group" => "Updates",
      "description" => "Branch von dem Updates bezogen werden (Standard: main).",
      "required" => true,
      "maxlength" => 100,
      "minlength" => 1,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        $v = preg_match('/^[a-zA-Z0-9_\.\/-]+$/', $value);
        return ["valid" => (bool)$v, "value" => $value, "error" => $v ? '' : 'Ungueltiger Branch-Name'];
      }
    ],
    "specialRequest" => false,
    "default" => "main",
    "envFallback" => "UPDATE_GIT_BRANCH",
  ],
  "UPDATE_AUTO_CHECK" => [
    "form" => [
      "type" => "select",
      "default" => function () {
        return "Enabled";
      },
      "name" => "Automatisch auf Updates pruefen",
      "group" => "Updates",
      "description" => "Wenn aktiviert, wird beim Laden der Admin-Seiten automatisch nach neuen Versionen gesucht und ein Hinweis angezeigt.",
      "required" => false,
      "maxlength" => 10,
      "minlength" => 5,
      "options" => ["Enabled", "Disabled"],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => in_array($value, $options), "value" => $value, "error" => ''];
      }
    ],
    "specialRequest" => false,
    "default" => "Enabled",
    "envFallback" => false,
  ],
  "TELEMETRY_NANOID"  => [
    "form" => [
      "type" => "text",
      "default" => function () {
        $client = new Hidehalo\Nanoid\Client();
        return $client->generateId(21);
      },
      "name" => "Telemetrie-NanoID",
      "group" => "Telemetrie",
      "description" => "ID fuer diese Installation, wird zur Identifikation auf dem Telemetrie-Server verwendet. Eine Aenderung erstellt eine neue Installation auf dem Server. Normalerweise muss dies nicht geaendert werden.",
      "required" => true,
      "maxlength" => 21,
      "minlength" => 21,
      "options" => [],
      "verifyMatch" => function ($value, $options) {
        return ["valid" => true, "value" => $value, "error" => ''];
      }
    ],
    "specialRequest" => false, // Has to be false as it's generated, otherwise it wont generate
    "default" => false,
    "envFallback" => "CONFIG_TELEMETRY_NANOID",
  ],
];
