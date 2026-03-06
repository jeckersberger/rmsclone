<?php
$configStructureArray = [
  "ROOTURL" => [
    "form" => [
      "type" => "url", // The type of field this is (secret, text, number, url, select)
      "default" => function () { // Default value for the text box
        return 'http://' . $_SERVER['HTTP_HOST'];
      },
      "name" => "Root URL", // The name of the field to be shown to the user
      "description" => "The URL of the site that is used as a point of reference for all links and emails. This is an important URL, because if it is misconfigured it will prevent you from logging in. It is probably https://yourdomain.com or https://yourdomain.com/adamrms or http://localhost:8080. It must not end in a trailing slash.", // A description of the field to be shown to the user
      "group" => "General", // The group this field belongs to
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
      "name" => "Timezone",
      "group" => "General",
      "description" => "The timezone to use for AdamRMS",
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
      "name" => "Email sending",
      "group" => "Email",
      "description" => "Should AdamRMS send emails to users? If this is enabled then a provider must be setup below. Enabling this option will also require users to verify their email addresses on signup.",
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
      "name" => "Email provider",
      "group" => "Email",
      "description" => "Which provider should AdamRMS use to send emails to users? This option is ignored if email sending is disabled.",
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
      "name" => "From email address",
      "group" => "Email",
      "description" => "The email address to send emails from",
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
      "name" => "Email Service API key",
      "group" => "Email",
      "description" => "If Sengrid, Mailgun or Postmark is selected above, the API key to use to send emails",
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
      "name" => "Mailgun Server Location",
      "group" => "Email",
      "description" => "If Mailgun is selected above, whether to use the US or EU Mailgun servers",
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
      "name" => "SMTP server address",
      "group" => "Email",
      "description" => "If SMTP is selected above, the SMTP server to send emails from",
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
      "name" => "SMTP server username",
      "group" => "Email",
      "description" => "If SMTP is selected above, the username to connect to the SMTP server with",
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
      "name" => "SMTP server password",
      "group" => "Email",
      "description" => "If SMTP is selected above, the password to connect to the SMTP server with",
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
      "name" => "SMTP server port",
      "group" => "Email",
      "description" => "If SMTP is selected above, the port to connect to the SMTP server on",
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
      "name" => "SMTP encryption type",
      "group" => "Email",
      "description" => "If SMTP is selected above, the encryption type to use when connecting to the SMTP server",
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
      "name" => "Email Footer",
      "group" => "Email",
      "description" => "Footer for emails.",
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
      "name" => "Sentry.io API key",
      "group" => "Error Handling",
      "description" => "The Sentry.io API key to use to send log errors to Sentry.io - this is normally only used if you are developing AdamRMS",
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
      "name" => "User Signup",
      "group" => "Security & Login",
      "description" => "Can new users create an account? Disabling this means new users can't sign up themselves.",
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
        $characters = 'ABCDEFGHKMNOPQRSTUVWXYZ0123456789';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < 64; $i++) {
          $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
      },
      "name" => "JWT Key",
      "group" => "Security & Login",
      "description" => "The JWT key to use for signing JWTs. This should be a random value that you keep secret of 64 characters. If you are setting up AdamRMS for the first time, then the default generated value will be fine. Changing this later will invalidate all existing JWTs.",
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
      "name" => "Next password hashing algorithm",
      "group" => "Security & Login",
      "description" => "The hashing algorithm to use for new passwords. Changing this will not require users to change their passwords, but it will change the hashing algorithm the next time a given user changes their password.",
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
      "name" => "Google Auth Key",
      "group" => "Authentication",
      "description" => "The ID key for Google authentication. When configuring Google authentication, set the redirect URIs to https://YOURROOTURL/login/oauth/google.php and https://YOURROOTURL/api/account/oauth-link/google.php",
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
      "name" => "Google Auth Secret",
      "group" => "Authentication",
      "description" => "The secret key for Google authentication.",
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
      "name" => "Google Auth Scope",
      "group" => "Authentication",
      "description" => "The scope for Google authentication. You would only usually change this if you are developing AdamRMS.",
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
      "name" => "Microsoft Auth App ID",
      "group" => "Authentication",
      "description" => "The App ID key for Microsoft authentication. When configuring Microsoft authentication, set the redirect URIs to https://YOURROOTURL/login/oauth/microsoft.php and https://YOURROOTURL/api/account/oauth-link/microsoft.php",
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
      "name" => "Microsoft Auth Secret",
      "group" => "Authentication",
      "description" => "The secret key for Microsoft authentication.",
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
      "group" => "Customisation",
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
      "name" => "User guide URL",
      "group" => "Customisation",
      "description" => "The URL of the user guide, which is linked to from the help buttons",
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
      "name" => "Support URL",
      "group" => "Customisation",
      "description" => "The URL for links to the support page",
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
      "name" => "Terms of service URL",
      "group" => "Customisation",
      "description" => "The URL to the terms of service page. This is linked to from the login page. If this is not set, the link will not be shown.",
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
      "name" => "Analytics Tracking Links",
      "group" => "Customisation",
      "description" => "Code to insert into the footer of all pages, such as a Google Analytics tracking link",
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
      "name" => "Allow all users to create new instances",
      "group" => "Billing",
      "description" => "Controls whether users are allowed to create new instances themselves, or whether this must be done by an administrator. ",
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
      "name" => "Suspend new instances by default",
      "group" => "Billing",
      "description" => "When a new instance is created, whether it should be suspended by default. This can be used to prevent new instances from being created until they have been reviewed by an administrator, or have started a free trial",
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
      "name" => "Reason for suspending new instances",
      "group" => "Billing",
      "description" => "When a new instance is suspended using the option above, what should AdamRMS prompt the user to do? It could prompt them to setup a plan, or to fix a billing issue using the stripe APIs, or to do something else using the text below.",
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
      "name" => "Suspension reason for new instances",
      "group" => "Billing",
      "description" => "When a new instance is suspended using the option above, what reason should be given to the user? This can be used to explain why their instance is suspended, and what they need to do to get it unsuspended.",
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
      "name" => "Stripe Key",
      "group" => "Billing",
      "description" => "The stripe key to use for stripe billing support. Leave blank to disable stripe billing. Requires permissions for billing portal, prices, sessions and products.",
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
      "name" => "Stripe Webhook secret",
      "group" => "Billing",
      "description" => "The secret key to use for stripe webhooks.",
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
      "name" => "Reduce Telemetry collected",
      "group" => "Telemetry",
      "description" => "What level of telemetry should be collected? When set to limited, this will reduce the amount of information about the installation sent to the Bithell Studios telemetry server, such as the number of assets on the server. More details: https://telemetry.bithell.studio/privacy-and-security",
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
      "name" => "Show this installation url in list of installations",
      "group" => "Telemetry",
      "description" => "Should the URL of this installation be shown in a table of installations on the telemetry server? If disabled, the installation is still counted in the public statistics, but its url and notes (set below) are not shown in the list of installations.",
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
      "name" => "Telemetry Installation Notes",
      "group" => "Telemetry",
      "description" => "A note to show on the public telemetry dashboard to associate with this installation. This could be the name of the main business. This is only shown publicly if the above option (show url) is enabled.",
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
      "name" => "Telemetry NanoID",
      "group" => "Telemetry",
      "description" => "ID to associate with this installation, used to identify this installation on the telemetry server. Changing this will create a new installation on the telemetry server. It is not expected that you'd need to change this. You can change the level of telemetry collected in the configuration menu under the \"Reduce Telemetry collected\" option. More details: https://telemetry.bithell.studio/privacy-and-security",
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
    "envFallback" => false,
  ],
];
