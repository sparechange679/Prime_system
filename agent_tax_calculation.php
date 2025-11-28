<?php
require_once 'config.php';
require_once 'database.php';

// Check if user is logged in and is an agent
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'agent') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Assessed customs value per kg (in MWK) used to derive Declared Value from HS + Weight
$assessed_value_mwk_per_kg = [
    '8528.72.00' => 40000,
    '8708.99.00' => 25000,
    '5208.52.00' => 15000,
    '6104.43.00' => 18000,
    '8432.80.00' => 20000,
    '8517.13.00' => 35000,
    '9503.00.00' => 12000,
    '8471.30.00' => 30000,
    '8471.41.00' => 32000,
    '8525.50.00' => 28000,
];
$assessed_default_mwk_per_kg = 20000; // fallback per-kg valuation in MWK
$full_name = $_SESSION['full_name'];

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Optional: prefill from a specific shipment
$prefill_tariff = '';
$prefill_currency = '';
$prefill_declared_value = '';
$prefill_weight = '';
$shipment_info = null;
if (isset($_GET['shipment_id']) && ctype_digit($_GET['shipment_id'])) {
    $pref_sid = (int)$_GET['shipment_id'];
    try {
        $ps = $db->prepare("SELECT s.shipment_id, s.tracking_number, s.goods_description, s.currency, s.declared_value, s.weight, s.tariff_number, c.company_name
                             FROM shipments s
                             JOIN clients c ON s.client_id = c.client_id
                             WHERE s.shipment_id = :sid AND s.agent_id = :aid LIMIT 1");
        $ps->bindValue(':sid', $pref_sid, PDO::PARAM_INT);
        $ps->bindValue(':aid', $user_id, PDO::PARAM_INT);
        $ps->execute();
        $shipment_info = $ps->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($shipment_info) {
            $prefill_tariff = (string)($shipment_info['tariff_number'] ?? '');
            $prefill_currency = (string)($shipment_info['currency'] ?? 'USD');
            $prefill_declared_value = (string)($shipment_info['declared_value'] ?? '');
            $prefill_weight = (string)($shipment_info['weight'] ?? '');
        }
    } catch (PDOException $e) { /* ignore prefill error */
    }
}
// Common HS Tariff codes with tax rates (Malawi rates)
$tariff_codes = [
    '8471.30.00' => ['description' => 'Portable digital automatic data processing machines', 'rate' => 0.15],
    '5208.52.00' => ['description' => 'Woven fabrics of cotton', 'rate' => 0.25],
    '8432.80.00' => ['description' => 'Agricultural machinery', 'rate' => 0.10],
    '8517.13.00' => ['description' => 'Smartphones', 'rate' => 0.20],
    '8528.72.00' => ['description' => 'Color television receivers', 'rate' => 0.25],
    '8708.99.00' => ['description' => 'Motor vehicle parts', 'rate' => 0.15],
    '9503.00.00' => ['description' => 'Other toys', 'rate' => 0.20],
    '6104.43.00' => ['description' => 'Women\'s dresses, synthetic fibres', 'rate' => 0.30],
    '8525.50.00' => ['description' => 'Digital cameras', 'rate' => 0.20],
    '8471.41.00' => ['description' => 'Laptop computers', 'rate' => 0.15]
];

// Currency list and fallback exchange rates to MWK (used only if API unavailable)
$currencies = [
    'USD' => 'US Dollar',
    'EUR' => 'Euro',
    'GBP' => 'British Pound',
    'MWK' => 'Malawi Kwacha',
    'ZMW' => 'Zambian Kwacha',
    'ZAR' => 'South African Rand',
    'CNY' => 'Chinese Yuan',
    'INR' => 'Indian Rupee',
    'KES' => 'Kenyan Shilling',
    'NGN' => 'Nigerian Naira',
    'GHS' => 'Ghanaian Cedi',
    'UGX' => 'Ugandan Shilling',
];

// Fallback approximate rates to MWK; will be overridden by live API when available
$fallback_rates_to_mwk = [
    'USD' => 1700,
    'EUR' => 1850,
    'GBP' => 2150,
    'MWK' => 1,
    'ZMW' => 80,
    'ZAR' => 90,
    'CNY' => 235,
    'INR' => 20,
    'KES' => 12,
    'NGN' => 1.2,
    'GHS' => 140,
    'UGX' => 0.45,
];

// Helper: get live exchange rate to MWK using exchangerate.host (no API key required)
function get_rate_to_mwk($base)
{
    global $fallback_rates_to_mwk;
    $base = strtoupper(trim($base));
    if ($base === 'MWK') return 1.0;
    $url = 'https://api.exchangerate.host/latest?base=' . urlencode($base) . '&symbols=MWK';
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        $resp = curl_exec($ch);
        if ($resp === false) {
            curl_close($ch);
            return $fallback_rates_to_mwk[$base] ?? 1.0;
        }
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http !== 200) {
            return $fallback_rates_to_mwk[$base] ?? 1.0;
        }
        $data = json_decode($resp, true);
        if (isset($data['rates']['MWK']) && is_numeric($data['rates']['MWK'])) {
            return (float)$data['rates']['MWK'];
        }
        return $fallback_rates_to_mwk[$base] ?? 1.0;
    } catch (Throwable $e) {
        return $fallback_rates_to_mwk[$base] ?? 1.0;
    }
}

// Handle tax calculation
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tariff_code = trim($_POST['tariff_code']);
    $currency = trim($_POST['currency']);
    $goods_weight = !empty($_POST['goods_weight']) ? (float)$_POST['goods_weight'] : 0;

    if (empty($tariff_code) || empty($currency)) {
        $calculation_error = "Tariff code and currency are required";
    } elseif (!isset($currencies[$currency])) {
        $calculation_error = "Invalid currency selected";
    } else {
        // Derive declared value from HS and weight
        $exchange_rate = get_rate_to_mwk($currency);
        $assessed_per_kg_mwk = $assessed_value_mwk_per_kg[$tariff_code] ?? $assessed_default_mwk_per_kg;
        $declared_value_mwk = max(0, $goods_weight) * $assessed_per_kg_mwk;
        $declared_value = $declared_value_mwk / max(0.000001, $exchange_rate);
        // Calculate taxes in original currency (ad valorem)
        $tariff_rate = $tariff_codes[$tariff_code]['rate'] ?? 0.20; // Default 20%
        $import_duty = $declared_value * $tariff_rate;
        $vat_rate = 0.165; // Malawi VAT 16.5%
        $vat_amount = ($declared_value + $import_duty) * $vat_rate;
        $total_tax = $import_duty + $vat_amount;
        $total_amount = $declared_value + $total_tax;

        // Convert to Malawi Kwacha (MWK)
        // $exchange_rate already computed
        // $declared_value_mwk already computed
        $import_duty_mwk = $import_duty * $exchange_rate;
        $vat_amount_mwk = $vat_amount * $exchange_rate;
        $total_tax_mwk = $total_tax * $exchange_rate;
        $total_amount_mwk = $total_amount * $exchange_rate;

        // Determine currency symbol for display
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'ZAR' => 'R',
            'MWK' => 'MWK',
            'ZMW' => 'ZK',
            'CNY' => '¥',
            'INR' => '₹',
            'KES' => 'KSh',
            'NGN' => '₦',
            'GHS' => 'GH₵',
            'UGX' => 'USh'
        ];
        $currency_symbol = $symbols[$currency] ?? $currency;

        $calculation_result = [
            'tariff_code' => $tariff_code,
            'currency' => $currency,
            'currency_symbol' => $currency_symbol,
            'tariff_rate' => $tariff_rate * 100,
            'declared_value' => $declared_value,
            'import_duty' => $import_duty,
            'vat_amount' => $vat_amount,
            'total_tax' => $total_tax,
            'total_amount' => $total_amount,
            'exchange_rate' => $exchange_rate,
            'declared_value_mwk' => $declared_value_mwk,
            'import_duty_mwk' => $import_duty_mwk,
            'vat_amount_mwk' => $vat_amount_mwk,
            'total_tax_mwk' => $total_tax_mwk,
            'total_amount_mwk' => $total_amount_mwk
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Calculator - Prime Cargo Limited</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="agent_dashboard.php">
                <i class="fas fa-ship me-2"></i>Prime Cargo Limited
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-white" href="agent_dashboard.php">
                    <i class="fas fa-arrow-left me-2"></i>Back to Agent Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-calculator me-2"></i>Tax Calculator</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($calculation_error)): ?>
                            <div class="alert alert-danger"><?php echo $calculation_error; ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">HS Tariff Code <span class="text-danger">*</span></label>
                                    <select name="tariff_code" class="form-select" required>
                                        <option value="">Select Tariff Code</option>
                                        <?php foreach ($tariff_codes as $code => $info): ?>
                                            <option value="<?php echo $code; ?>"
                                                <?php
                                                $sel = '';
                                                if (isset($_POST['tariff_code'])) {
                                                    $sel = ($_POST['tariff_code'] == $code) ? 'selected' : '';
                                                } elseif (!empty($prefill_tariff)) {
                                                    $sel = ($prefill_tariff == $code) ? 'selected' : '';
                                                }
                                                echo $sel;
                                                ?>>
                                                <?php echo $code; ?> - <?php echo $info['description']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Select the appropriate Harmonized System code</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Currency <span class="text-danger">*</span></label>
                                    <select name="currency" id="currencySelect" class="form-select" required>
                                        <option value="">Select Currency</option>
                                        <?php foreach ($currencies as $code => $name): ?>
                                            <option value="<?php echo $code; ?>" <?php
                                                                                    $sel = '';
                                                                                    if (isset($_POST['currency'])) {
                                                                                        $sel = ($_POST['currency'] == $code) ? 'selected' : '';
                                                                                    } elseif (!empty($prefill_currency)) {
                                                                                        $sel = ($prefill_currency == $code) ? 'selected' : '';
                                                                                    }
                                                                                    echo $sel;
                                                                                    ?>>
                                                <?php echo $code; ?> — <?php echo $name; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Live rate to MWK: <strong id="ratePreview">—</strong></div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Declared Value</label>
                                    <input type="number" class="form-control" placeholder="Auto from HS & Weight" value="<?php
                                                                                                                            if (isset($calculation_result)) {
                                                                                                                                echo htmlspecialchars(number_format($calculation_result['declared_value'], 2, '.', ''));
                                                                                                                            }
                                                                                                                            ?>" readonly>
                                    <div class="form-text">Auto-derived from HS & Weight (no manual entry)</div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Goods Weight (kg)</label>
                                        <input type="number" name="goods_weight" class="form-control"
                                            step="0.01" min="0" placeholder="0.00"
                                            value="<?php echo isset($_POST['goods_weight']) ? htmlspecialchars($_POST['goods_weight']) : htmlspecialchars($prefill_weight); ?>">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-calculator me-2"></i>Calculate Taxes
                                        </button>
                                    </div>
                                </div>
                        </form>

                        <?php if (isset($calculation_result)): ?>
                            <hr class="my-4">
                            <h5><i class="fas fa-chart-pie me-2"></i>Tax Calculation Results</h5>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6>Import Duty</h6>
                                            <p class="mb-1"><strong>Rate:</strong> <?php echo $calculation_result['tariff_rate']; ?>%</p>
                                            <p class="mb-1"><strong>Amount:</strong> <?php echo $calculation_result['currency_symbol']; ?><?php echo number_format($calculation_result['import_duty'], 2); ?></p>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6>VAT (16.5%)</h6>
                                            <p class="mb-1"><strong>Base:</strong> <?php echo $calculation_result['currency_symbol']; ?><?php echo number_format($calculation_result['declared_value'] + $calculation_result['import_duty'], 2); ?></p>
                                            <p class="mb-1"><strong>Amount:</strong> <?php echo $calculation_result['currency_symbol']; ?><?php echo number_format($calculation_result['vat_amount'], 2); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-info mt-3">
                                <h6>Summary (<?php echo $calculation_result['currency']; ?>):</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Declared Value:</strong> <?php echo $calculation_result['currency']; ?> <?php echo number_format($calculation_result['declared_value'], 2); ?></p>
                                        <p class="mb-1"><strong>Total Taxes:</strong> <?php echo $calculation_result['currency']; ?> <?php echo number_format($calculation_result['total_tax'], 2); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Import Duty:</strong> <?php echo $calculation_result['currency']; ?> <?php echo number_format($calculation_result['import_duty'], 2); ?></p>
                                        <p class="mb-1"><strong>VAT:</strong> <?php echo $calculation_result['currency']; ?> <?php echo number_format($calculation_result['vat_amount'], 2); ?></p>
                                    </div>
                                </div>
                                <hr>
                                <h6>Summary (Malawi Kwacha - MWK):</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Declared Value:</strong> MWK <?php echo number_format($calculation_result['declared_value_mwk'], 2); ?></p>
                                        <p class="mb-1"><strong>Total Taxes:</strong> MWK <?php echo number_format($calculation_result['total_tax_mwk'], 2); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Import Duty:</strong> MWK <?php echo number_format($calculation_result['import_duty_mwk'], 2); ?></p>
                                        <p class="mb-1"><strong>VAT:</strong> MWK <?php echo number_format($calculation_result['vat_amount_mwk'], 2); ?></p>
                                    </div>
                                </div>
                                <hr>
                                <h5 class="text-primary mb-0">
                                    <strong>Total Amount Due: <?php echo $calculation_result['currency']; ?> <?php echo number_format($calculation_result['total_amount'], 2); ?> / MWK <?php echo number_format($calculation_result['total_amount_mwk'], 2); ?></strong>
                                </h5>
                                <small class="text-muted">Exchange Rate: 1 <?php echo $calculation_result['currency']; ?> = <?php echo number_format($calculation_result['exchange_rate'], 2); ?> MWK</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Information Sidebar -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Tax Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-lightbulb me-2"></i>Tax Calculation Guide:</h6>
                            <ul class="mb-0">
                                <li>Import duty rates vary by HS code</li>
                                <li>VAT is calculated on value + duty</li>
                                <li>Rates are based on Malawi customs</li>
                                <li>Always verify with customs authorities</li>
                            </ul>
                        </div>

                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Important Notes:</h6>
                            <ul class="mb-0">
                                <li>Tax rates may change</li>
                                <li>Special exemptions may apply</li>
                                <li>Verify with MRA for accuracy</li>
                                <li>Keep records for audit purposes</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>