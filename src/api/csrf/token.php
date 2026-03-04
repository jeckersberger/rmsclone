<?php
/**
 * CSRF-Token Endpoint
 * GET: Liefert ein frisches CSRF-Token fuer die aktuelle Session
 */
require_once __DIR__ . '/../apiHead.php';
require_once __DIR__ . '/../../services/CsrfService.php';

$token = CsrfService::generateToken();
finish(true, null, ['csrf_token' => $token]);
