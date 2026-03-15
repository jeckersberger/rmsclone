<?php
//Change password with policy enforcement
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/PasswordPolicyService.php';

require_once __DIR__ . '/../../services/PasswordHashService.php';

// Altes Passwort pruefen (unterstuetzt Legacy- und modernes Schema)
if (!PasswordHashService::verify(
    $_POST['oldpass'],
    $AUTH->data['users_password'],
    $AUTH->data['users_hash'],
    $AUTH->data['users_salty1'] ?? '',
    $AUTH->data['users_salty2'] ?? ''
)) finish(false, ["message" => "Current Password Incorrect"]);

// Enforce password policy on new password
$newPass = $_POST['newpass'] ?? '';
$violations = PasswordPolicyService::validate($newPass);
if (!empty($violations)) {
    finish(false, ["code" => "WEAK_PASSWORD", "message" => implode(' ', $violations)]);
}

// Neues Passwort mit Argon2ID hashen
$upgradeData = PasswordHashService::upgradeData($newPass);

$DBLIB->where ('users_userid', $AUTH->data['users_userid']);
if ($DBLIB->update('users', $upgradeData)) {
    // Alle anderen Sessions invalidieren (Sicherheit bei Passwortwechsel)
    $DBLIB->where('users_userid', $AUTH->data['users_userid']);
    $DBLIB->where('authTokens_token', $_SESSION['token'] ?? '', '!=');
    $DBLIB->update('authTokens', ['authTokens_valid' => 0]);

    $bCMS->auditLog("UPDATE", "users", "PASSWORD CHANGE + HASH UPGRADE", $AUTH->data['users_userid'], $AUTH->data['users_userid']);
    finish(true);
}
else finish(false);

/** @OA\Post(
 *      path="/account/changePass.php", 
 *      summary="Change Password", 
 *      description="Change the password of the current user", 
 *      operationId="changePassword", 
 *      tags={"account"}, 
 *      @OA\Response(
 *          response="200", 
 *          description="OK or Error",
 *          @OA\MediaType(
 *              mediaType="application/json", 
 *              @OA\Schema(ref="#/components/schemas/SimpleResponse"),
 *          ),
 *      ), 
 *      @OA\Parameter(
 *          name="oldpass",
 *          in="query",
 *          description="undefined",
 *          required="true", 
 *          @OA\Schema(
 *              type="string"), 
 *          ), 
 *      @OA\Parameter(
 *          name="newpass",
 *          in="query",
 *          description="undefined",
 *          required="true", 
 *          @OA\Schema(
 *              type="string"), 
 *          ), 
 *  )
 */