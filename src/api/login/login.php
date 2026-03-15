<?php
require_once 'loginAjaxHead.php';
require_once __DIR__ . '/../../services/RateLimitService.php';
require_once __DIR__ . '/../../services/LoginLogService.php';
require_once __DIR__ . '/../../services/TotpService.php';
use \Firebase\JWT\JWT;
if (isset($_POST['formInput']) and isset($_POST['password'])) {
	$input = trim(strtolower($GLOBALS['bCMS']->sanitizeString($_POST['formInput'])));
    $password = $GLOBALS['bCMS']->sanitizeString($_POST['password']);
	if ($input == "" || $password == "") finish(false, ["code" => null, "message" => "No data specified"]);
	else {
        // IP-basiertes Rate-Limiting (zusaetzlich zum bestehenden Account-basiertem Schutz)
        $rateLimiter = new RateLimitService($DBLIB);
        $clientIp = $_SERVER["HTTP_CF_CONNECTING_IP"] ?? (isset($_SERVER["HTTP_X_FORWARDED_FOR"]) ? trim(explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"])[0]) : $_SERVER["REMOTE_ADDR"]);
        if (!$rateLimiter->isAllowed('login', $clientIp)) {
            $rateLimiter->recordAttempt('login', $clientIp, false);
            finish(false, ["code" => null, "message" => "Too many login attempts from this IP address. Please try again later."]);
        }
        if (filter_var($input, FILTER_VALIDATE_EMAIL)) $DBLIB->where ("users_email", $input);
        else $DBLIB->where ("users_username", $input);
        $DBLIB->where("users_password", NULL, "IS NOT"); //To cover oauth users
        $user = $DBLIB->getOne("users",["users.users_salty1", "users.users_suspended", "users.users_salty2", "users.users_password", "users.users_userid", "users.users_hash", "users.users_totpSecret", "users.users_totpEnabled"]);
        if (!$user) $successful = false;
        elseif (!hash_equals($user['users_password'], hash($user['users_hash'], $user['users_salty1'] . $password . $user['users_salty2']))) $successful = false;
        else $successful = true;

        // Account-basierter Brute-Force-Schutz (5 Minuten Fenster)
        $DBLIB->where ("loginAttempts_timestamp >= '" . date('Y-m-d G:i:s', strtotime('-5 minutes')) . "'");
        $DBLIB->where ("loginAttempts_successful",0);
        $DBLIB->where ("loginAttempts_textEntered", $input);
        $previousattempts = $DBLIB->getValue("loginAttempts", "count(*)");
        $bruteforceattempt = ($previousattempts > 6);

        // Account-Lockout: Nach 15 Fehlversuchen in 30 Minuten wird der Account gesperrt
        $DBLIB->where("loginAttempts_timestamp >= '" . date('Y-m-d G:i:s', strtotime('-30 minutes')) . "'");
        $DBLIB->where("loginAttempts_successful", 0);
        $DBLIB->where("loginAttempts_textEntered", $input);
        $lockoutAttempts = $DBLIB->getValue("loginAttempts", "count(*)");
        if ($lockoutAttempts >= 15) {
            // Temporaerer Lockout - Account fuer 30 Minuten gesperrt
            if ($user && $user['users_suspended'] == '0') {
                $DBLIB->where('users_userid', $user['users_userid']);
                $DBLIB->update('users', ['users_suspended' => 2]); // 2 = temp lockout (vs 1 = admin suspended)
            }
            finish(false, ["code" => "LOCKOUT", "message" => "Account temporarily locked due to too many failed attempts. Please try again in 30 minutes or reset your password."]);
        }

        if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) $ipAddress = $_SERVER["HTTP_CF_CONNECTING_IP"];
        elseif(isset($_SERVER["HTTP_X_FORWARDED_FOR"])) $ipAddress = array_shift(explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"]));
        else $ipAddress = $_SERVER["REMOTE_ADDR"];

        //Record this login attempt
        $DBLIB->insert ('loginAttempts', [
            "loginAttempts_ip" => $ipAddress,
            "loginAttempts_textEntered" => $input,
            "loginAttempts_timestamp" => date('Y-m-d G:i:s'),
            "loginAttempts_blocked" => ($bruteforceattempt ? '1' : '0'),
            "loginAttempts_successful" => ($successful ? '1' : '0')
        ]);

        // Initialize login log service
        $loginLog = new LoginLogService($DBLIB);
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        if ($bruteforceattempt) {
            $rateLimiter->recordAttempt('login', $clientIp, false);
            $loginLog->logAttempt($user['users_userid'] ?? null, $clientIp, $userAgent, false, 'brute_force_block');
            finish(false, ["code" => null, "message" => "Sorry - you've tried too many times to login - please try again in 5 minutes"]);
        }
        elseif (!$successful) {
            $rateLimiter->recordAttempt('login', $clientIp, false);
            $loginLog->logAttempt($user['users_userid'] ?? null, $clientIp, $userAgent, false, 'invalid_credentials');
            finish(false, ["code" => null, "message" => "Username, email or password incorrect"]);
        }
        elseif ($user['users_suspended'] != '0') {
            $loginLog->logAttempt($user['users_userid'], $clientIp, $userAgent, false, 'account_suspended');
            finish(false, ["code" => null, "message" => "User account is suspended"]);
        }
        else {
            // TOTP-Pruefung: Wenn 2FA aktiviert, muss TOTP-Code mitgesendet werden
            $totpService = new TotpService($DBLIB);
            if ($totpService->isTotpEnabled($user['users_userid'])) {
                $totpCode = trim($_POST['totp_code'] ?? '');
                if (empty($totpCode)) {
                    // Kein Code mitgesendet — Frontend soll TOTP-Eingabe anzeigen
                    finish(false, ["code" => "TOTP_REQUIRED", "message" => "Zwei-Faktor-Code erforderlich"]);
                }
                // Versuche TOTP-Code, dann Backup-Code
                if (!$totpService->verifyCode($user['users_totpSecret'] ?? '', $totpCode)
                    && !$totpService->verifyBackupCode($user['users_userid'], $totpCode)) {
                    $loginLog->logAttempt($user['users_userid'], $clientIp, $userAgent, false, 'invalid_totp');
                    finish(false, ["code" => "TOTP_INVALID", "message" => "Ungueltiger Zwei-Faktor-Code"]);
                }
            }

            $rateLimiter->recordAttempt('login', $clientIp, true);
            $rateLimiter->resetOnSuccess('login', $clientIp);
            $loginLog->logAttempt($user['users_userid'], $clientIp, $userAgent, true);

            // Session-Regeneration: Neue Session-ID nach Login (verhindert Session-Fixation)
            if (session_status() === PHP_SESSION_ACTIVE) {
                $returnUrl = $_SESSION['return'] ?? null;
                $appOauth = $_SESSION['app-oauth'] ?? null;
                session_regenerate_id(true);
                if ($returnUrl) $_SESSION['return'] = $returnUrl;
                if ($appOauth) $_SESSION['app-oauth'] = $appOauth;
            }
            if (!$_SESSION['return'] and isset($_SESSION['app-oauth'])) {
                $token = $GLOBALS['AUTH']->generateToken($user['users_userid'], false, "App OAuth", "app-v1");
                $jwt = $GLOBALS['AUTH']->issueJWT($token, $user['users_userid'], "app-v1");
                finish(true,null,["redirect" => $_SESSION['app-oauth'] . "oauth_callback?token=" . $jwt]);
            } else {
                $GLOBALS['AUTH']->generateToken($user['users_userid'], false, "Web", "web-session");
                $redirect = $CONFIG['ROOTURL'];
                if (isset($_SESSION['return']) && $_SESSION['return']) {
                    $p = parse_url($_SESSION['return']);
                    $r = parse_url($CONFIG['ROOTURL']);
                    if ($p && $r && isset($p['host']) && $p['host'] === $r['host']) $redirect = $_SESSION['return'];
                }
                finish(true,null,["redirect" => $redirect]);
            }
        }
	}
} else finish(false, ["code" => null, "message" => "Unknown error"]);

/**
 *  @OA\Post(
 *      path="/login/login.php",
 *      summary="Login",
 *      description="User Login",
 *      operationId="login",
 *      tags={"authentication"},
 *      @OA\Response(
 *          response="200",
 *          description="Success",
 *          @OA\MediaType(
 *             mediaType="application/json", 
 *             @OA\Schema(ref="#/components/schemas/SimpleResponse"),
 *         ),
 *      ),
 *      @OA\Parameter(
 *          name="formInput",
 *          in="query",
 *          description="Email Address of user",
 *          required="true",
 *          @OA\Schema(
 *              type="string",
 *          ),
 *      ),
 *      @OA\Parameter(
 *          name="password",
 *          in="query",
 *          description="Password of user",
 *          required="true",
 *          @OA\Schema(
 *              type="string",
 *          ),
 *      ),
 *  )
 */
