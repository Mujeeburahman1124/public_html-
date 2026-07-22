<?php
header('Content-Type: application/json');

/*
 * create-checkout-session.php
 * Handles creation of Stripe Checkout sessions
 */

require_once __DIR__ . '/vendor/autoload.php';

// Stripe Secret Key
$stripe_secret = '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';

\Stripe\Stripe::setApiKey($stripe_secret);

$input = json_decode(file_get_contents('php://input'), true);
$plan = $input['plan'] ?? '';
$email = $input['email'] ?? '';

if (!$plan || !$email) {
    echo json_encode(['error' => 'Plan and Email are required.']);
    exit;
}

$plan_data = [
    'starter' => [
        'name' => 'Starter Subscription',
        'amount' => 50000, // $500.00
    ],
    'professional' => [
        'name' => 'Professional Subscription',
        'amount' => 100000, // $1000.00
    ],
    'enterprise' => [
        'name' => 'Enterprise Subscription',
        'amount' => 200000, // $2000.00
    ],
];

if (!isset($plan_data[$plan])) {
    echo json_encode(['error' => 'Invalid plan selected.']);
    exit;
}

try {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $domain = $protocol . '://' . $_SERVER['HTTP_HOST'];
    
    $checkout_session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'customer_email' => $email,
        'line_items' => [[
            'price_data' => [
                'currency' => 'usd',
                'product_data' => [
                    'name' => $plan_data[$plan]['name'],
                    'description' => 'Access to MSJOBS Jobseeker Database',
                ],
                'unit_amount' => $plan_data[$plan]['amount'],
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'success_url' => $domain . '/payment-success.php?session_id={CHECKOUT_SESSION_ID}&plan=' . $plan . '&email=' . urlencode($email),
        'cancel_url' => $domain . '/Companylogin.php',
    ]);

    echo json_encode(['id' => $checkout_session->id]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
