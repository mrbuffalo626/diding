<?php
declare(strict_types=1);

session_start();

$products = [
    'ballpen' => ['name' => 'Ballpen', 'price' => 1200, 'icon' => '🖊️'],
    'pencil' => ['name' => 'Pencil', 'price' => 1000, 'icon' => '✏️'],
    'notebook' => ['name' => 'Notebook', 'price' => 3500, 'icon' => '📓'],
    'yellow-pad' => ['name' => 'Yellow Pad', 'price' => 4500, 'icon' => '📒'],
    'eraser' => ['name' => 'Eraser', 'price' => 800, 'icon' => '🧽'],
    'ruler' => ['name' => 'Ruler', 'price' => 1500, 'icon' => '📏'],
];

$_SESSION['cart'] ??= [];
$_SESSION['payment_input'] ??= '';
$_SESSION['receipt'] ??= null;
$_SESSION['error'] ??= '';

$formatPeso = static fn (int $cents): string => '₱' . number_format($cents / 100, 2);
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'new_transaction') {
        $_SESSION['cart'] = [];
        $_SESSION['payment_input'] = '';
        $_SESSION['receipt'] = null;
        $_SESSION['error'] = '';
    } elseif ($_SESSION['receipt'] === null) {
        $_SESSION['error'] = '';

        if ($action === 'add' || $action === 'increase' || $action === 'decrease' || $action === 'remove') {
            $productId = isset($_POST['product_id']) && is_string($_POST['product_id']) ? $_POST['product_id'] : '';

            if (!isset($products[$productId])) {
                $_SESSION['error'] = 'Please select a valid product.';
            } elseif ($action === 'add' || $action === 'increase') {
                $quantity = (int) ($_SESSION['cart'][$productId] ?? 0);
                if ($quantity < 999) {
                    $_SESSION['cart'][$productId] = $quantity + 1;
                } else {
                    $_SESSION['error'] = 'The maximum quantity per product is 999.';
                }
            } elseif ($action === 'decrease') {
                $quantity = (int) ($_SESSION['cart'][$productId] ?? 0);
                if ($quantity <= 1) {
                    unset($_SESSION['cart'][$productId]);
                } else {
                    $_SESSION['cart'][$productId] = $quantity - 1;
                }
            } else {
                unset($_SESSION['cart'][$productId]);
            }
        } elseif ($action === 'confirm_payment') {
            $submittedPayment = $_POST['amount_paid'] ?? '';
            $_SESSION['payment_input'] = is_string($submittedPayment) ? $submittedPayment : '';

            $total = 0;
            foreach ($_SESSION['cart'] as $productId => $quantity) {
                if (isset($products[$productId])) {
                    $total += $products[$productId]['price'] * (int) $quantity;
                }
            }

            $payment = trim($_SESSION['payment_input']);
            if ($payment === '') {
                $_SESSION['error'] = 'Enter the amount paid.';
            } elseif (!preg_match('/^-?(?:\d+(?:\.\d{1,2})?|\.\d{1,2})$/', $payment)) {
                $_SESSION['error'] = 'Enter a valid amount using up to two decimal places.';
            } elseif ($payment[0] === '-') {
                $_SESSION['error'] = 'The amount paid cannot be negative.';
            } elseif ($total <= 0) {
                $_SESSION['error'] = 'Add at least one product before confirming payment.';
            } else {
                [$whole, $fraction] = array_pad(explode('.', $payment, 2), 2, '');
                $whole = $whole === '' ? '0' : $whole;

                if (strlen($whole) > 12) {
                    $_SESSION['error'] = 'The amount entered is too large.';
                } else {
                    $amountPaid = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

                    if ($amountPaid < $total) {
                        $_SESSION['error'] = 'The amount paid is less than the total amount due.';
                    } else {
                        $receiptItems = [];
                        foreach ($_SESSION['cart'] as $productId => $quantity) {
                            if (isset($products[$productId])) {
                                $receiptItems[] = [
                                    'name' => $products[$productId]['name'],
                                    'icon' => $products[$productId]['icon'],
                                    'price' => $products[$productId]['price'],
                                    'quantity' => (int) $quantity,
                                ];
                            }
                        }

                        $_SESSION['receipt'] = [
                            'reference' => 'SS-' . strtoupper(bin2hex(random_bytes(5))),
                            'date' => date('F j, Y g:i A'),
                            'items' => $receiptItems,
                            'total' => $total,
                            'paid' => $amountPaid,
                            'change' => $amountPaid - $total,
                        ];
                    }
                }
            }
        }
    }

    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$error = (string) $_SESSION['error'];
$_SESSION['error'] = '';
$cart = $_SESSION['cart'];
$total = 0;
foreach ($cart as $productId => $quantity) {
    if (isset($products[$productId])) {
        $total += $products[$productId]['price'] * (int) $quantity;
    }
}
$receipt = $_SESSION['receipt'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Supplies Ordering System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<header class="bg-primary text-white py-4 mb-4">
    <div class="container">
        <h1 class="h2 mb-1">Student Supplies Ordering System</h1>
        <p class="mb-0">Choose your school supplies and pay with cash.</p>
    </div>
</header>

<main class="container pb-5">
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= $escape($error) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <section class="col-lg-7" aria-labelledby="products-heading">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white"><h2 class="h4 mb-0" id="products-heading">Product List</h2></div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach ($products as $productId => $product): ?>
                            <div class="col-sm-6">
                                <article class="card h-100">
                                    <div class="card-body d-flex justify-content-between align-items-center gap-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="fs-2" aria-hidden="true"><?= $escape($product['icon']) ?></span>
                                            <div>
                                                <h3 class="h6 mb-1"><?= $escape($product['name']) ?></h3>
                                                <span class="text-secondary"><?= $formatPeso($product['price']) ?></span>
                                            </div>
                                        </div>
                                        <form method="post">
                                            <input type="hidden" name="action" value="add">
                                            <input type="hidden" name="product_id" value="<?= $escape($productId) ?>">
                                            <button class="btn btn-primary btn-sm" type="submit" <?= $receipt !== null ? 'disabled' : '' ?>>Add to Cart</button>
                                        </form>
                                    </div>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="col-lg-5" aria-labelledby="cart-heading">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h2 class="h4 mb-0" id="cart-heading">Shopping Cart</h2></div>
                <?php if ($cart === []): ?>
                    <div class="card-body text-secondary">Your cart is empty.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                            <tr><th scope="col">Product</th><th scope="col">Price</th><th scope="col">Qty</th><th scope="col">Subtotal</th><th scope="col"><span class="visually-hidden">Remove</span></th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($cart as $productId => $quantity): ?>
                                <?php if (isset($products[$productId])): ?>
                                    <tr>
                                        <td><span class="me-1" aria-hidden="true"><?= $escape($products[$productId]['icon']) ?></span><?= $escape($products[$productId]['name']) ?></td>
                                        <td><?= $formatPeso($products[$productId]['price']) ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1">
                                                <form method="post">
                                                    <input type="hidden" name="action" value="decrease">
                                                    <input type="hidden" name="product_id" value="<?= $escape($productId) ?>">
                                                    <button class="btn btn-outline-secondary btn-sm" type="submit" aria-label="Decrease <?= $escape($products[$productId]['name']) ?> quantity" <?= $receipt !== null ? 'disabled' : '' ?>>−</button>
                                                </form>
                                                <span class="px-1"><?= (int) $quantity ?></span>
                                                <form method="post">
                                                    <input type="hidden" name="action" value="increase">
                                                    <input type="hidden" name="product_id" value="<?= $escape($productId) ?>">
                                                    <button class="btn btn-outline-secondary btn-sm" type="submit" aria-label="Increase <?= $escape($products[$productId]['name']) ?> quantity" <?= $receipt !== null ? 'disabled' : '' ?>>+</button>
                                                </form>
                                            </div>
                                        </td>
                                        <td><?= $formatPeso($products[$productId]['price'] * (int) $quantity) ?></td>
                                        <td>
                                            <form method="post">
                                                <input type="hidden" name="action" value="remove">
                                                <input type="hidden" name="product_id" value="<?= $escape($productId) ?>">
                                                <button class="btn btn-outline-danger btn-sm" type="submit" aria-label="Remove <?= $escape($products[$productId]['name']) ?>" <?= $receipt !== null ? 'disabled' : '' ?>>Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                <div class="card-body border-top">
                    <h3 class="h5">Order Summary</h3>
                    <p class="d-flex justify-content-between mb-0"><span>Total Amount</span><strong><?= $formatPeso($total) ?></strong></p>
                </div>
            </div>

            <div class="card shadow-sm mt-4">
                <div class="card-header bg-white"><h2 class="h4 mb-0" id="payment-heading">Payment Section</h2></div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="confirm_payment">
                        <label for="amount-paid" class="form-label">Amount paid (₱)</label>
                        <input class="form-control mb-3" type="number" id="amount-paid" name="amount_paid" min="0" step="0.01" placeholder="0.00" value="<?= $escape((string) $_SESSION['payment_input']) ?>" <?= $receipt !== null ? 'disabled' : '' ?>>
                        <button class="btn btn-success w-100" type="submit" <?= ($receipt !== null || $cart === []) ? 'disabled' : '' ?>>Confirm Payment</button>
                    </form>
                </div>
            </div>
        </section>
    </div>

    <section class="mt-4" aria-labelledby="receipt-heading">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><h2 class="h4 mb-0" id="receipt-heading">Receipt Section</h2></div>
            <?php if ($receipt === null): ?>
                <div class="card-body text-secondary">Your receipt will appear here after successful payment.</div>
            <?php else: ?>
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                        <div><strong>Transaction Reference:</strong> <?= $escape($receipt['reference']) ?></div>
                        <div><strong>Date &amp; Time:</strong> <?= $escape($receipt['date']) ?></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead class="table-light"><tr><th scope="col">Product</th><th scope="col">Quantity</th><th scope="col">Unit Price</th><th scope="col">Subtotal</th></tr></thead>
                            <tbody>
                            <?php foreach ($receipt['items'] as $item): ?>
                                <tr>
                                    <td><span class="me-1" aria-hidden="true"><?= $escape($item['icon']) ?></span><?= $escape($item['name']) ?></td>
                                    <td><?= (int) $item['quantity'] ?></td>
                                    <td><?= $formatPeso($item['price']) ?></td>
                                    <td><?= $formatPeso($item['price'] * $item['quantity']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <dl class="row mb-3">
                        <dt class="col-sm-3">Total Amount Due</dt><dd class="col-sm-9"><?= $formatPeso($receipt['total']) ?></dd>
                        <dt class="col-sm-3">Amount Paid</dt><dd class="col-sm-9"><?= $formatPeso($receipt['paid']) ?></dd>
                        <dt class="col-sm-3">Change</dt><dd class="col-sm-9"><?= $formatPeso($receipt['change']) ?></dd>
                    </dl>
                    <div class="alert alert-success mb-3" role="status">Payment confirmed. Thank you for your purchase!</div>
                    <form method="post">
                        <input type="hidden" name="action" value="new_transaction">
                        <button class="btn btn-primary" type="submit">New Transaction</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
</body>
</html>
