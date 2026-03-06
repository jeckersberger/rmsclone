<?php
require_once __DIR__ . '/../../apiHeadSecure.php';

if ($AUTH->data['users_userid'] !== $AUTH->data['instance']['instances_billingUser']) die("Sorry, you are not the billing contact for this business, please contact support.");

$stripeKey = $CONFIGCLASS->get('STRIPE_KEY');
if (!$stripeKey || strlen($stripeKey) === 0) {
    finish(false, ["message" => "Stripe-Billing ist nicht konfiguriert."]);
}

$stripe = new \Stripe\StripeClient($stripeKey);
$link = $stripe->billingPortal->sessions->create([
  'customer' => $AUTH->data['instance']['instances_planStripeCustomerId'],
  'return_url' => $CONFIG['ROOTURL'] . "/instances/billing.php",
]);


header("HTTP/1.1 303 See Other");
header("Location: " . $link->url);
