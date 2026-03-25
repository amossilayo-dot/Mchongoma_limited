<?php

declare(strict_types=1);

final class MobileMoneyGateway
{
    private const PROVIDERS = [
        'mpesa' => 'M-Pesa',
        'tigo_pesa' => 'Tigo Pesa',
        'airtel_money' => 'Airtel Money',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    private ?array $settingsCache = null;

    public function initiate(array $input): array
    {
        $provider = $this->normalizeProvider((string) ($input['provider'] ?? ''));
        $phone = $this->normalizePhone((string) ($input['phone'] ?? ''));
        $amount = (float) ($input['amount'] ?? 0);
        $currency = (string) ($input['currency'] ?? 'TZS');
        $reference = trim((string) ($input['reference'] ?? ''));

        if ($provider === null) {
            return [
                'success' => false,
                'message' => 'Invalid mobile money provider selected.',
            ];
        }

        if ($amount <= 0) {
            return [
                'success' => false,
                'message' => 'Amount must be greater than zero for mobile money.',
            ];
        }

        if ($phone === null) {
            return [
                'success' => false,
                'message' => 'Enter a valid Tanzania mobile number (e.g. 07XXXXXXXX or 2557XXXXXXXX).',
            ];
        }

        $mode = strtolower((string) $this->getConfigValue('mobile_money_mode', 'MOBILE_MONEY_MODE', 'mock'));

        if ($mode === 'live') {
            $live = $this->initiateLive($provider, $phone, $amount, $currency, $reference);
            if (!$live['success']) {
                return $live;
            }

            $transactionId = $this->saveTransaction([
                'provider' => $provider,
                'msisdn' => $phone,
                'amount' => $amount,
                'currency' => $currency,
                'external_reference' => (string) $live['external_reference'],
                'status' => (string) ($live['status'] ?? 'pending'),
                'response_payload' => (string) json_encode($live['raw_response'] ?? [], JSON_UNESCAPED_UNICODE),
            ]);

            return [
                'success' => true,
                'provider' => $provider,
                'provider_label' => self::PROVIDERS[$provider],
                'phone' => $phone,
                'external_reference' => (string) $live['external_reference'],
                'status' => (string) ($live['status'] ?? 'pending'),
                'transaction_id' => $transactionId,
                'message' => self::PROVIDERS[$provider] . ' request initiated successfully.',
            ];
        }

        // Mock mode is useful during setup before provider credentials are configured.
        $externalReference = 'MM-' . strtoupper(substr($provider, 0, 2)) . '-' . date('YmdHis') . random_int(100, 999);
        $transactionId = $this->saveTransaction([
            'provider' => $provider,
            'msisdn' => $phone,
            'amount' => $amount,
            'currency' => $currency,
            'external_reference' => $externalReference,
            'status' => 'mock_approved',
            'response_payload' => json_encode([
                'mode' => 'mock',
                'message' => 'No live credentials configured yet.',
                'reference' => $reference,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        return [
            'success' => true,
            'provider' => $provider,
            'provider_label' => self::PROVIDERS[$provider],
            'phone' => $phone,
            'external_reference' => $externalReference,
            'status' => 'mock_approved',
            'transaction_id' => $transactionId,
            'message' => self::PROVIDERS[$provider] . ' payment approved in mock mode.',
        ];
    }

    public function attachSaleId(int $transactionId, int $saleId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE mobile_money_transactions
             SET sale_id = :sale_id
             WHERE id = :id'
        );
        $stmt->execute([
            ':sale_id' => $saleId,
            ':id' => $transactionId,
        ]);
    }

    public function verifyCallbackToken(?string $providedToken): bool
    {
        $configuredSecret = trim($this->getConfigValue('mobile_money_callback_secret', 'MOBILE_MONEY_CALLBACK_SECRET', ''));
        if ($configuredSecret === '') {
            $mode = strtolower((string) $this->getConfigValue('mobile_money_mode', 'MOBILE_MONEY_MODE', 'mock'));
            return $mode === 'mock';
        }

        if ($providedToken === null) {
            return false;
        }

        return hash_equals($configuredSecret, trim($providedToken));
    }

    public function processCallback(array $payload): array
    {
        $reference = $this->extractCallbackReference($payload);
        if ($reference === '') {
            return [
                'success' => false,
                'statusCode' => 422,
                'message' => 'Missing transaction reference in callback payload.',
            ];
        }

        $status = $this->normalizeCallbackStatus((string) ($payload['status'] ?? $payload['result'] ?? $payload['transaction_status'] ?? 'pending'));
        $transaction = $this->findTransactionByReference($reference);
        if ($transaction === null) {
            return [
                'success' => false,
                'statusCode' => 404,
                'message' => 'Transaction not found for reference: ' . $reference,
            ];
        }

        $combinedPayload = [
            'initial_payload' => $this->decodePayload((string) ($transaction['response_payload'] ?? '')),
            'callback_payload' => $payload,
            'updated_at' => date('c'),
        ];

        $stmt = $this->pdo->prepare(
            'UPDATE mobile_money_transactions
             SET status = :status,
                 response_payload = :response_payload
             WHERE id = :id'
        );
        $stmt->execute([
            ':status' => $status,
            ':response_payload' => (string) json_encode($combinedPayload, JSON_UNESCAPED_UNICODE),
            ':id' => (int) $transaction['id'],
        ]);

        return [
            'success' => true,
            'statusCode' => 200,
            'message' => 'Callback processed successfully.',
            'reference' => $reference,
            'status' => $status,
        ];
    }

    private function normalizeProvider(string $provider): ?string
    {
        $key = strtolower(trim($provider));
        return array_key_exists($key, self::PROVIDERS) ? $key : null;
    }

    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '255' . substr($digits, 1);
        }

        if (str_starts_with($digits, '255') && strlen($digits) === 12) {
            return $digits;
        }

        return null;
    }

    private function extractCallbackReference(array $payload): string
    {
        $possibleKeys = [
            'external_reference',
            'reference',
            'merchant_reference',
            'order_id',
            'transaction_id',
            'checkout_request_id',
        ];

        foreach ($possibleKeys as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $value = trim((string) $payload[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function normalizeCallbackStatus(string $status): string
    {
        $value = strtolower(trim($status));
        return match ($value) {
            'success', 'successful', 'completed', 'paid', 'approved' => 'success',
            'failed', 'declined', 'error' => 'failed',
            'cancelled', 'canceled' => 'cancelled',
            default => 'pending',
        };
    }

    private function findTransactionByReference(string $reference): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, external_reference, response_payload
             FROM mobile_money_transactions
             WHERE external_reference = :reference
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([':reference' => $reference]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    private function decodePayload(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : ['raw' => $value];
    }

    private function ensureTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS mobile_money_transactions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sale_id INT NULL,
                provider VARCHAR(40) NOT NULL,
                msisdn VARCHAR(20) NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                currency VARCHAR(10) NOT NULL DEFAULT "TZS",
                external_reference VARCHAR(120) NOT NULL,
                status VARCHAR(40) NOT NULL,
                response_payload LONGTEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_sale_id (sale_id),
                INDEX idx_external_reference (external_reference)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function saveTransaction(array $row): int
    {
        $this->ensureTable();

        $stmt = $this->pdo->prepare(
            'INSERT INTO mobile_money_transactions
             (sale_id, provider, msisdn, amount, currency, external_reference, status, response_payload)
             VALUES (NULL, :provider, :msisdn, :amount, :currency, :external_reference, :status, :response_payload)'
        );

        $stmt->execute([
            ':provider' => (string) $row['provider'],
            ':msisdn' => (string) $row['msisdn'],
            ':amount' => (float) $row['amount'],
            ':currency' => (string) ($row['currency'] ?? 'TZS'),
            ':external_reference' => (string) $row['external_reference'],
            ':status' => (string) $row['status'],
            ':response_payload' => (string) ($row['response_payload'] ?? ''),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function initiateLive(string $provider, string $phone, float $amount, string $currency, string $reference): array
    {
        $baseVar = match ($provider) {
            'mpesa' => 'MOBILE_MONEY_MPESA_URL',
            'tigo_pesa' => 'MOBILE_MONEY_TIGO_URL',
            'airtel_money' => 'MOBILE_MONEY_AIRTEL_URL',
            default => null,
        };

        $tokenVar = match ($provider) {
            'mpesa' => 'MOBILE_MONEY_MPESA_TOKEN',
            'tigo_pesa' => 'MOBILE_MONEY_TIGO_TOKEN',
            'airtel_money' => 'MOBILE_MONEY_AIRTEL_TOKEN',
            default => null,
        };

        $dbUrlKey = match ($provider) {
            'mpesa' => 'mobile_money_mpesa_url',
            'tigo_pesa' => 'mobile_money_tigo_url',
            'airtel_money' => 'mobile_money_airtel_url',
            default => '',
        };

        $dbTokenKey = match ($provider) {
            'mpesa' => 'mobile_money_mpesa_token',
            'tigo_pesa' => 'mobile_money_tigo_token',
            'airtel_money' => 'mobile_money_airtel_token',
            default => '',
        };

        if ($baseVar === null || $tokenVar === null) {
            return [
                'success' => false,
                'message' => 'Provider configuration is missing.',
            ];
        }

        $url = trim((string) $this->getConfigValue($dbUrlKey, $baseVar, ''));
        $token = trim((string) $this->getConfigValue($dbTokenKey, $tokenVar, ''));

        if ($url === '' || $token === '') {
            return [
                'success' => false,
                'message' => 'Live gateway credentials are not configured. Set ' . $baseVar . ' and ' . $tokenVar . '.',
            ];
        }

        $requestReference = $reference !== '' ? $reference : ('POS-' . date('YmdHis'));
        $payload = [
            'msisdn' => $phone,
            'amount' => $amount,
            'currency' => $currency,
            'reference' => $requestReference,
        ];

        if ($provider === 'mpesa') {
            $mpesaCommand = strtolower((string) $this->getConfigValue('mobile_money_mpesa_command', 'MOBILE_MONEY_MPESA_COMMAND', 'customer_paybill'));
            if (!in_array($mpesaCommand, ['customer_paybill', 'customer_buygoods', 'disburse'], true)) {
                $mpesaCommand = 'customer_paybill';
            }

            $payload['command'] = $mpesaCommand;
            $businessId = trim((string) $this->getConfigValue('mobile_money_mpesa_business_id', 'MOBILE_MONEY_MPESA_BUSINESS_ID', ''));
            if ($businessId !== '') {
                $payload['business_id'] = $businessId;
            }
        }

        $response = $this->sendJsonRequest($url, $payload, $token);
        if (!$response['success']) {
            return $response;
        }

        $body = $response['body'];
        $externalReference = (string) ($body['transaction_id'] ?? $body['reference'] ?? ('GW-' . date('YmdHis') . random_int(100, 999)));
        $status = strtolower((string) ($body['status'] ?? 'pending'));

        return [
            'success' => true,
            'status' => $status,
            'external_reference' => $externalReference,
            'raw_response' => $body,
        ];
    }

    private function sendJsonRequest(string $url, array $payload, string $token): array
    {
        $timeout = (int) $this->getConfigValue('mobile_money_timeout', 'MOBILE_MONEY_TIMEOUT', '15');
        if ($timeout <= 0) {
            $timeout = 15;
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $token,
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => (string) json_encode($payload, JSON_UNESCAPED_UNICODE),
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ]);

        $responseBody = @file_get_contents($url, false, $context);
        if ($responseBody === false) {
            return [
                'success' => false,
                'message' => 'Failed to connect to mobile money provider.',
            ];
        }

        $statusCode = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
            $statusCode = (int) $matches[1];
        }

        $decoded = json_decode($responseBody, true);
        if (!is_array($decoded)) {
            $decoded = ['raw' => $responseBody];
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            return [
                'success' => false,
                'message' => 'Gateway rejected request with status ' . $statusCode . '.',
                'body' => $decoded,
            ];
        }

        return [
            'success' => true,
            'body' => $decoded,
        ];
    }

    private function getConfigValue(string $storeKey, ?string $envKey, string $default): string
    {
        $storeValue = trim($this->getStoreSetting($storeKey));
        if ($storeValue !== '') {
            return $storeValue;
        }

        if ($envKey !== null && $envKey !== '') {
            $envValue = trim((string) getenv($envKey));
            if ($envValue !== '') {
                return $envValue;
            }
        }

        return $default;
    }

    private function getStoreSetting(string $key): string
    {
        $settings = $this->loadStoreSettings();
        return (string) ($settings[$key] ?? '');
    }

    private function loadStoreSettings(): array
    {
        if (is_array($this->settingsCache)) {
            return $this->settingsCache;
        }

        try {
            $stmt = $this->pdo->query('SELECT setting_key, setting_value FROM store_settings');
            if (!$stmt) {
                $this->settingsCache = [];
                return $this->settingsCache;
            }

            $rows = $stmt->fetchAll();
            $settings = [];
            foreach ($rows as $row) {
                $settingKey = (string) ($row['setting_key'] ?? '');
                if ($settingKey === '') {
                    continue;
                }
                $settings[$settingKey] = (string) ($row['setting_value'] ?? '');
            }

            $this->settingsCache = $settings;
            return $this->settingsCache;
        } catch (Throwable) {
            $this->settingsCache = [];
            return $this->settingsCache;
        }
    }
}
