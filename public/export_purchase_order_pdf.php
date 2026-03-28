<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/PdfHelper.php';

ensureSecureSessionStarted();
requireAuthentication();

$purchaseOrderId = (int) ($_GET['po_id'] ?? 0);
if ($purchaseOrderId <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Invalid po_id.';
    exit;
}

try {
    $pdo = getDatabaseConnection();

    $orderStmt = $pdo->prepare(
        'SELECT po.id,
                po.po_no,
                po.amount,
                po.status,
                po.expected_delivery_date,
                po.notes,
                po.created_at,
                po.supplier_id,
                s.name AS supplier_name,
                s.contact_person,
                s.phone,
                s.email
         FROM purchase_orders po
         LEFT JOIN suppliers s ON s.id = po.supplier_id
         WHERE po.id = :id
         LIMIT 1'
    );
    $orderStmt->execute([':id' => $purchaseOrderId]);
    $order = $orderStmt->fetch();

    if (!$order) {
        throw new RuntimeException('Purchase order not found.');
    }

    $itemStmt = $pdo->prepare(
        'SELECT poi.product_id,
                p.name AS product_name,
                poi.quantity,
                poi.unit_cost,
                poi.line_total
         FROM purchase_order_items poi
         LEFT JOIN products p ON p.id = poi.product_id
         WHERE poi.purchase_order_id = :purchase_order_id
         ORDER BY poi.id ASC'
    );
    $itemStmt->execute([':purchase_order_id' => $purchaseOrderId]);
    $items = $itemStmt->fetchAll() ?: [];

    $supplierName = (string) (($order['supplier_name'] ?? '') !== ''
        ? $order['supplier_name']
        : ('Supplier #' . (string) ($order['supplier_id'] ?? '-')));

    $createdAtRaw = trim((string) ($order['created_at'] ?? ''));
    $createdAtLabel = $createdAtRaw;
    if ($createdAtRaw !== '') {
        $ts = strtotime($createdAtRaw);
        if ($ts !== false) {
            $createdAtLabel = date('Y-m-d H:i', $ts);
        }
    }

    $expectedDeliveryRaw = trim((string) ($order['expected_delivery_date'] ?? ''));
    $expectedDeliveryLabel = $expectedDeliveryRaw !== '' ? $expectedDeliveryRaw : 'N/A';

    $lines = [
        'Generated: ' . date('Y-m-d H:i:s'),
        'PO No: ' . (string) ($order['po_no'] ?? '-'),
        'Status: ' . (string) ($order['status'] ?? 'Pending'),
        'Date: ' . $createdAtLabel,
        'Expected Delivery: ' . $expectedDeliveryLabel,
        str_repeat('-', 90),
        'Supplier Details',
        'Name: ' . $supplierName,
        'Contact: ' . (string) ((trim((string) ($order['contact_person'] ?? '')) !== '') ? $order['contact_person'] : 'N/A'),
        'Phone: ' . (string) ((trim((string) ($order['phone'] ?? '')) !== '') ? $order['phone'] : 'N/A'),
        'Email: ' . (string) ((trim((string) ($order['email'] ?? '')) !== '') ? $order['email'] : 'N/A'),
        str_repeat('-', 90),
        'PO Items',
    ];

    $computedTotal = 0.0;
    if (count($items) === 0) {
        $lines[] = 'No PO items recorded.';
    } else {
        foreach ($items as $index => $item) {
            $productName = trim((string) ($item['product_name'] ?? ''));
            if ($productName === '') {
                $productName = 'Product #' . (string) ($item['product_id'] ?? '-');
            }

            $quantity = (int) ($item['quantity'] ?? 0);
            $unitCost = (float) ($item['unit_cost'] ?? 0);
            $lineTotal = (float) ($item['line_total'] ?? ($quantity * $unitCost));
            $computedTotal += $lineTotal;

            $lines[] = sprintf(
                '%d. %s | Qty: %d | Unit: Tsh %s | Line: Tsh %s',
                $index + 1,
                $productName,
                $quantity,
                number_format($unitCost, 0, '.', ','),
                number_format($lineTotal, 0, '.', ',')
            );
        }
    }

    $orderAmount = (float) ($order['amount'] ?? 0);
    $grandTotal = $orderAmount > 0 ? $orderAmount : $computedTotal;

    $lines[] = str_repeat('-', 90);
    $lines[] = 'Total Amount: Tsh ' . number_format($grandTotal, 0, '.', ',');

    $notes = trim((string) ($order['notes'] ?? ''));
    if ($notes !== '') {
        $lines[] = 'Notes: ' . $notes;
    }

    $pdf = buildSimplePdf('Purchase Order', $lines);

    $safePoNo = preg_replace('/[^A-Za-z0-9\-_]/', '_', (string) ($order['po_no'] ?? ('PO_' . $purchaseOrderId)));
    if (!is_string($safePoNo) || $safePoNo === '') {
        $safePoNo = 'PO_' . $purchaseOrderId;
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="purchase_order_' . $safePoNo . '_' . date('Ymd_His') . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    echo $pdf;
    exit;
} catch (Throwable $exception) {
    error_log('[POS PO Export PDF] ' . $exception->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Could not generate purchase order PDF at this time.';
    exit;
}
