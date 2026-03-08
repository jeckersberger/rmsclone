<?php
//Change password with policy enforcement
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/PasswordPolicyService.php';

if (hash($AUTH->data['users_hash'], $AUTH->data['users_salty1'] . $_POST['oldpass']. $AUTH->data['users_salty2']) != $AUTH->data['users_password']) finish(false,["message"=>"Current Password Incorrect"]);

// Enforce password policy on new password
$newPass = $_POST['newpass'] ?? '';
$violations = PasswordPolicyService::validate($newPass);
if (!empty($violations)) {
    finish(false, ["code" => "WEAK_PASSWORD", "message" => implode(' ', $violations)]);
}

$DBLIB->where ('users_userid', $AUTH->data['users_userid']);
if ($DBLIB->update('users', ["users_password" => hash($CONFIG['AUTH_NEXTHASH'], $AUTH->data['users_salty1'] . $newPass . $AUTH->data['users_salty2'])])) {
    $bCMS->auditLog("UPDATE", "users", "PASSWORD CHANGE", $AUTH->data['users_userid'],$AUTH->data['users_userid']);
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