<?php

namespace App\Services;

use App\Filters\OrderFilter;
use App\Models\Booking;
use App\Models\Order;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use setasign\Fpdi\Tcpdf\Fpdi;

class OrderExportService
{
    protected string $letterheadPath;

    public function __construct(
        protected OrderRepositoryInterface $orderRepository
    ) {
        $this->letterheadPath = storage_path('app/letterhead.pdf');
    }

    public function downloadInvoicePdf(Order $order)
    {
        $order->load([
            'user',
            'items.product.files',
            'shippingAddress',
            'billingAddress',
            'latestPayment.paymentMethod',
        ]);

        if ($order->type === 'booking' && $order->orderable) {
            $order->load('orderable.services.bookable');
        }

        $data = $this->prepareInvoiceData($order);

        $reference = $order->reference ?? "ORD-{$order->id}";
        $filename = 'Invoice-' . $reference . '.pdf';

        // Generate PDF using letterhead template
        $pdfContent = $this->generateInvoiceWithLetterhead($data);

        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * Generate invoice PDF using the letterhead template
     */
    protected function generateInvoiceWithLetterhead(array $data): string
    {
        // Create new PDF with FPDI
        $pdf = new Fpdi('P', 'mm', 'A4');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(true, 25);

        // Import the letterhead template
        if (file_exists($this->letterheadPath)) {
            $pdf->setSourceFile($this->letterheadPath);
            $templateId = $pdf->importPage(1);
        } else {
            $templateId = null;
        }

        // Add first page with letterhead
        $pdf->AddPage();
        if ($templateId) {
            $pdf->useTemplate($templateId, 0, 0, 210, 297);
        }

        // Set font
        $pdf->SetFont('helvetica', '', 10);

        // Colors
        $brandColor = [76, 55, 21]; // #4C3715

        // === INVOICE TITLE ===
        $pdf->SetY(35);
        $pdf->SetFont('helvetica', 'B', 24);
        $pdf->SetTextColor(...$brandColor);
        $pdf->Cell(0, 10, 'INVOICE', 0, 1, 'C');

        // Invoice reference
        $pdf->SetFont('helvetica', '', 12);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 6, '#' . ($data['reference'] ?? 'N/A'), 0, 1, 'C');

        // Refunded stamp
        if (!empty($data['isRefunded'])) {
            $pdf->Ln(2);
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetTextColor(220, 38, 38);
            $pdf->Cell(0, 8, 'REFUNDED', 0, 1, 'C');
        }

        // === CUSTOMER INFO & DATE ===
        $pdf->SetY(60);
        $pdf->SetFont('helvetica', '', 10);

        // Left side - Bill To
        $pdf->SetTextColor(136, 136, 136);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(90, 5, 'BILL TO', 0, 0, 'L');

        // Right side - Invoice Details
        $pdf->Cell(90, 5, 'INVOICE DETAILS', 0, 1, 'L');

        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(...$brandColor);

        // Customer name
        $pdf->Cell(90, 6, $data['customerName'] ?? 'N/A', 0, 0, 'L');
        // Date
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(30, 6, 'Date:', 0, 0, 'L');
        $pdf->SetTextColor(...$brandColor);
        $pdf->Cell(60, 6, $data['order']->created_at->format('d M Y'), 0, 1, 'L');

        // Customer email
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(90, 6, $data['customerEmail'] ?? 'N/A', 0, 0, 'L');
        // Payment method
        if ($data['paymentMethod']) {
            $pdf->Cell(30, 6, 'Payment:', 0, 0, 'L');
            $pdf->SetTextColor(...$brandColor);
            $pdf->Cell(60, 6, $data['paymentMethod'], 0, 1, 'L');
        } else {
            $pdf->Ln(6);
        }

        // === ITEMS TABLE ===
        $pdf->SetY(90);

        // Table header
        $pdf->SetFillColor(...$brandColor);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 9);

        $pdf->Cell(80, 8, 'ITEM', 1, 0, 'L', true);
        $pdf->Cell(25, 8, 'QTY', 1, 0, 'C', true);
        $pdf->Cell(35, 8, 'UNIT PRICE', 1, 0, 'R', true);
        $pdf->Cell(30, 8, 'TOTAL', 1, 1, 'R', true);

        // Table rows
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(...$brandColor);
        $currency = $data['order']->currency ?? 'AED';

        $rowFill = false;
        foreach ($data['items'] as $item) {
            if ($rowFill) {
                $pdf->SetFillColor(250, 250, 250);
            } else {
                $pdf->SetFillColor(255, 255, 255);
            }

            $pdf->Cell(80, 8, $item['name'], 'LR', 0, 'L', true);
            $pdf->Cell(25, 8, $item['quantity'], 'LR', 0, 'C', true);
            $pdf->Cell(35, 8, number_format($item['unitPrice'], 2) . ' ' . $currency, 'LR', 0, 'R', true);
            $pdf->Cell(30, 8, number_format($item['subtotal'], 2) . ' ' . $currency, 'LR', 1, 'R', true);

            $rowFill = !$rowFill;
        }

        // Close table bottom
        $pdf->Cell(170, 0, '', 'T', 1);

        // === TOTALS ===
        $pdf->Ln(5);
        $totalsX = 100;
        $pdf->SetX($totalsX);

        // Subtotal
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(40, 7, 'Subtotal:', 0, 0, 'L');
        $pdf->SetTextColor(...$brandColor);
        $pdf->Cell(30, 7, number_format($data['subtotal'], 2) . ' ' . $currency, 0, 1, 'R');

        // VAT
        if ($data['tax'] > 0) {
            $pdf->SetX($totalsX);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(40, 7, 'VAT (5%):', 0, 0, 'L');
            $pdf->SetTextColor(...$brandColor);
            $pdf->Cell(30, 7, number_format($data['tax'], 2) . ' ' . $currency, 0, 1, 'R');
        }

        // Discount
        if (isset($data['discount']) && $data['discount'] > 0) {
            $pdf->SetX($totalsX);
            $pdf->SetTextColor(45, 95, 63); // Green for discount
            $discountLabel = $data['discountLabel'] ? "Discount ({$data['discountLabel']}):" : 'Discount:';
            $pdf->Cell(40, 7, $discountLabel, 0, 0, 'L');
            $pdf->Cell(30, 7, '-' . number_format($data['discount'], 2) . ' ' . $currency, 0, 1, 'R');
        }

        // Gift Card
        if (isset($data['giftCardAmount']) && $data['giftCardAmount'] > 0) {
            $pdf->SetX($totalsX);
            $pdf->SetTextColor(45, 95, 63); // Green for gift card
            $giftCardLabel = 'Gift Card:';
            $pdf->Cell(40, 7, $giftCardLabel, 0, 0, 'L');
            $pdf->Cell(30, 7, '-' . number_format($data['giftCardAmount'], 2) . ' ' . $currency, 0, 1, 'R');
        }

        // Tip
        if (isset($data['tipAmount']) && $data['tipAmount'] > 0) {
            $pdf->SetX($totalsX);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(40, 7, 'Tip:', 0, 0, 'L');
            $pdf->SetTextColor(...$brandColor);
            $pdf->Cell(30, 7, number_format($data['tipAmount'], 2) . ' ' . $currency, 0, 1, 'R');
        }

        // Total
        $pdf->SetX($totalsX);
        $pdf->SetFillColor(...$brandColor);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(40, 10, 'TOTAL:', 1, 0, 'L', true);
        $pdf->Cell(30, 10, number_format($data['total'], 2) . ' ' . $currency, 1, 1, 'R', true);

        // === THANK YOU MESSAGE ===
        $pdf->Ln(15);
        $pdf->SetFillColor(239, 230, 216); // #EFE6D8
        $pdf->SetTextColor(...$brandColor);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 15, 'Thank you for your business!', 0, 1, 'C', true);

        return $pdf->Output('', 'S');
    }

    public function downloadInvoiceXlsx(Order $order): StreamedResponse
    {
        $order->load([
            'user',
            'items.product.files',
            'shippingAddress',
            'billingAddress',
            'latestPayment.paymentMethod',
        ]);

        if ($order->type === 'booking' && $order->orderable) {
            $order->load('orderable.services.bookable');
        }

        $data = $this->prepareInvoiceData($order);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Invoice #' . ($order->reference ?? $order->id));
        $sheet->setCellValue('A3', 'Customer:');
        $sheet->setCellValue('B3', $data['customerName'] ?? 'N/A');
        $sheet->setCellValue('A4', 'Email:');
        $sheet->setCellValue('B4', $data['customerEmail'] ?? 'N/A');
        $sheet->setCellValue('A5', 'Date:');
        $sheet->setCellValue('B5', $order->created_at->format('d M Y'));

        $row = 7;
        $sheet->setCellValue("A{$row}", 'Item');
        $sheet->setCellValue("B{$row}", 'Quantity');
        $sheet->setCellValue("C{$row}", 'Unit Price');
        $sheet->setCellValue("D{$row}", 'Total');

        $row = 8;
        foreach ($data['items'] as $item) {
            $sheet->setCellValue("A{$row}", $item['name']);
            $sheet->setCellValue("B{$row}", $item['quantity']);
            $sheet->setCellValue("C{$row}", $item['unitPrice']);
            $sheet->setCellValue("D{$row}", $item['subtotal']);
            $row++;
        }

        $row++;
        $sheet->setCellValue("C{$row}", 'Subtotal:');
        $sheet->setCellValue("D{$row}", $data['subtotal']);
        $row++;
        $sheet->setCellValue("C{$row}", 'Tax:');
        $sheet->setCellValue("D{$row}", $data['tax']);
        if (isset($data['discount']) && $data['discount'] > 0) {
            $row++;
            $discountLabel = $data['discountLabel'] ? "Discount ({$data['discountLabel']}):" : 'Discount:';
            $sheet->setCellValue("C{$row}", $discountLabel);
            $sheet->setCellValue("D{$row}", -$data['discount']);
        }
        if (isset($data['giftCardAmount']) && $data['giftCardAmount'] > 0) {
            $row++;
            $giftCardLabel = 'Gift Card:';
            $sheet->setCellValue("C{$row}", $giftCardLabel);
            $sheet->setCellValue("D{$row}", -$data['giftCardAmount']);
        }
        if (isset($data['tipAmount']) && $data['tipAmount'] > 0) {
            $row++;
            $sheet->setCellValue("C{$row}", 'Tip:');
            $sheet->setCellValue("D{$row}", $data['tipAmount']);
        }
        $row++;
        $sheet->setCellValue("C{$row}", 'Total:');
        $sheet->setCellValue("D{$row}", $data['total']);

        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $reference = $order->reference ?? "ORD-{$order->id}";
        $filename = 'Invoice-' . $reference . '.xlsx';

        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportOrdersPdf(?OrderFilter $filter = null, ?array $ids = null)
    {
        $query = Order::query();

        if ($ids && is_array($ids) && count($ids) > 0) {
            $query = $query->whereIn('id', $ids);
        } elseif ($filter) {
            $query = $filter->apply($query);
        }

        $orders = $query->with([
            'user',
            'items.product',
            'shippingAddress',
            'latestPayment.paymentMethod',
        ])->orderByDesc('created_at')->get();

        $filename = 'Payments-Export-' . now()->format('Y-m-d') . '.pdf';

        // Generate bulk PDF using letterhead template
        $pdfContent = $this->generateBulkInvoiceWithLetterhead($orders);

        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * Generate bulk invoice PDF with letterhead for multiple orders
     */
    protected function generateBulkInvoiceWithLetterhead($orders): string
    {
        // Create new PDF with FPDI
        $pdf = new Fpdi('P', 'mm', 'A4');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(true, 25);

        // Import the letterhead template
        $templateId = null;
        if (file_exists($this->letterheadPath)) {
            $pdf->setSourceFile($this->letterheadPath);
            $templateId = $pdf->importPage(1);
        }

        $brandColor = [76, 55, 21];

        foreach ($orders as $index => $order) {
            $data = $this->prepareInvoiceData($order);

            // Add new page with letterhead
            $pdf->AddPage();
            if ($templateId) {
                $pdf->useTemplate($templateId, 0, 0, 210, 297);
            }

            // === INVOICE TITLE ===
            $pdf->SetY(35);
            $pdf->SetFont('helvetica', 'B', 24);
            $pdf->SetTextColor(...$brandColor);
            $pdf->Cell(0, 10, 'INVOICE', 0, 1, 'C');

            $pdf->SetFont('helvetica', '', 12);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(0, 6, '#' . ($data['reference'] ?? 'N/A'), 0, 1, 'C');

            // Refunded stamp
            if (!empty($data['isRefunded'])) {
                $pdf->Ln(2);
                $pdf->SetFont('helvetica', 'B', 12);
                $pdf->SetTextColor(220, 38, 38);
                $pdf->Cell(0, 8, 'REFUNDED', 0, 1, 'C');
            }

            // === CUSTOMER INFO ===
            $pdf->SetY(60);
            $pdf->SetTextColor(136, 136, 136);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(90, 5, 'BILL TO', 0, 0, 'L');
            $pdf->Cell(90, 5, 'INVOICE DETAILS', 0, 1, 'L');

            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetTextColor(...$brandColor);
            $pdf->Cell(90, 6, $data['customerName'] ?? 'N/A', 0, 0, 'L');
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(30, 6, 'Date:', 0, 0, 'L');
            $pdf->SetTextColor(...$brandColor);
            $pdf->Cell(60, 6, $data['order']->created_at->format('d M Y'), 0, 1, 'L');

            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(90, 6, $data['customerEmail'] ?? 'N/A', 0, 0, 'L');
            if ($data['paymentMethod']) {
                $pdf->Cell(30, 6, 'Payment:', 0, 0, 'L');
                $pdf->SetTextColor(...$brandColor);
                $pdf->Cell(60, 6, $data['paymentMethod'], 0, 1, 'L');
            } else {
                $pdf->Ln(6);
            }

            // === ITEMS TABLE ===
            $pdf->SetY(90);
            $pdf->SetFillColor(...$brandColor);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('helvetica', 'B', 9);

            $pdf->Cell(80, 8, 'ITEM', 1, 0, 'L', true);
            $pdf->Cell(25, 8, 'QTY', 1, 0, 'C', true);
            $pdf->Cell(35, 8, 'UNIT PRICE', 1, 0, 'R', true);
            $pdf->Cell(30, 8, 'TOTAL', 1, 1, 'R', true);

            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetTextColor(...$brandColor);
            $currency = $data['order']->currency ?? 'AED';

            $rowFill = false;
            foreach ($data['items'] as $item) {
                $pdf->SetFillColor($rowFill ? 250 : 255, $rowFill ? 250 : 255, $rowFill ? 250 : 255);
                $pdf->Cell(80, 8, $item['name'], 'LR', 0, 'L', true);
                $pdf->Cell(25, 8, $item['quantity'], 'LR', 0, 'C', true);
                $pdf->Cell(35, 8, number_format($item['unitPrice'], 2) . ' ' . $currency, 'LR', 0, 'R', true);
                $pdf->Cell(30, 8, number_format($item['subtotal'], 2) . ' ' . $currency, 'LR', 1, 'R', true);
                $rowFill = !$rowFill;
            }

            $pdf->Cell(170, 0, '', 'T', 1);

            // === TOTALS ===
            $pdf->Ln(5);
            $totalsX = 100;
            $pdf->SetX($totalsX);

            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(40, 7, 'Subtotal:', 0, 0, 'L');
            $pdf->SetTextColor(...$brandColor);
            $pdf->Cell(30, 7, number_format($data['subtotal'], 2) . ' ' . $currency, 0, 1, 'R');

            if ($data['tax'] > 0) {
                $pdf->SetX($totalsX);
                $pdf->SetTextColor(100, 100, 100);
                $pdf->Cell(40, 7, 'VAT (5%):', 0, 0, 'L');
                $pdf->SetTextColor(...$brandColor);
                $pdf->Cell(30, 7, number_format($data['tax'], 2) . ' ' . $currency, 0, 1, 'R');
            }

            if (isset($data['discount']) && $data['discount'] > 0) {
                $pdf->SetX($totalsX);
                $pdf->SetTextColor(45, 95, 63);
                $discountLabel = $data['discountLabel'] ? "Discount ({$data['discountLabel']}):" : 'Discount:';
                $pdf->Cell(40, 7, $discountLabel, 0, 0, 'L');
                $pdf->Cell(30, 7, '-' . number_format($data['discount'], 2) . ' ' . $currency, 0, 1, 'R');
            }

            if (isset($data['giftCardAmount']) && $data['giftCardAmount'] > 0) {
                $pdf->SetX($totalsX);
                $pdf->SetTextColor(45, 95, 63);
                $giftCardLabel = 'Gift Card:';
                $pdf->Cell(40, 7, $giftCardLabel, 0, 0, 'L');
                $pdf->Cell(30, 7, '-' . number_format($data['giftCardAmount'], 2) . ' ' . $currency, 0, 1, 'R');
            }

            if (isset($data['tipAmount']) && $data['tipAmount'] > 0) {
                $pdf->SetX($totalsX);
                $pdf->SetTextColor(100, 100, 100);
                $pdf->Cell(40, 7, 'Tip:', 0, 0, 'L');
                $pdf->SetTextColor(...$brandColor);
                $pdf->Cell(30, 7, number_format($data['tipAmount'], 2) . ' ' . $currency, 0, 1, 'R');
            }

            $pdf->SetX($totalsX);
            $pdf->SetFillColor(...$brandColor);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(40, 10, 'TOTAL:', 1, 0, 'L', true);
            $pdf->Cell(30, 10, number_format($data['total'], 2) . ' ' . $currency, 1, 1, 'R', true);

            // Thank you
            $pdf->Ln(15);
            $pdf->SetFillColor(239, 230, 216);
            $pdf->SetTextColor(...$brandColor);
            $pdf->SetFont('helvetica', '', 12);
            $pdf->Cell(0, 15, 'Thank you for your business!', 0, 1, 'C', true);
        }

        return $pdf->Output('', 'S');
    }

    public function exportOrdersXlsx(?OrderFilter $filter = null, ?array $ids = null): StreamedResponse
    {
        $query = Order::query();

        if ($ids && is_array($ids) && count($ids) > 0) {
            $query = $query->whereIn('id', $ids);
        } elseif ($filter) {
            $query = $filter->apply($query);
        }

        $orders = $query->with([
            'user',
            'items.product',
            'shippingAddress',
            'latestPayment.paymentMethod',
        ])->orderByDesc('created_at')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Payment ID');
        $sheet->setCellValue('B1', 'Service/Order');
        $sheet->setCellValue('C1', 'Payment Method');
        $sheet->setCellValue('D1', 'Amount');
        $sheet->setCellValue('E1', 'Date');
        $sheet->setCellValue('F1', 'Status');

        $row = 2;
        foreach ($orders as $order) {
            $paymentId = $order->reference ?? $order->latestPayment?->external_id ?? "#{$order->id}";
            
            $productName = 'N/A';
            if ($order->type === 'ecommerce' && $order->items->isNotEmpty()) {
                $productName = $order->items->first()->product?->name ?? 'N/A';
            } elseif ($order->type === 'booking' && $order->orderable instanceof Booking) {
                $booking = $order->orderable;
                if ($booking->services->isNotEmpty()) {
                    $productName = $booking->services->first()->bookable?->name ?? 'N/A';
                }
            }

            $paymentMethod = 'N/A';
            if ($order->latestPayment?->paymentMethod) {
                $pm = $order->latestPayment->paymentMethod;
                $paymentMethod = ($pm->brand ? ucfirst($pm->brand) : 'Card') . ' ...' . $pm->last4;
            } elseif ($order->latestPayment?->provider === 'stripe') {
                $paymentMethod = 'Card';
            }

            $isRefunded = in_array($order->status, ['refunded', 'return_approved']);

            $sheet->setCellValue("A{$row}", $paymentId);
            $sheet->setCellValue("B{$row}", $productName);
            $sheet->setCellValue("C{$row}", $paymentMethod);
            $sheet->setCellValue("D{$row}", $order->amount . ' ' . ($order->currency ?? 'AED'));
            $sheet->setCellValue("E{$row}", $order->created_at->format('d. M Y'));
            $sheet->setCellValue("F{$row}", $isRefunded ? 'Refunded' : 'Paid');
            $row++;
        }

        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'Payments-Export-' . now()->format('Y-m-d') . '.xlsx';

        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function prepareInvoiceData(Order $order): array
    {
        $customerName = null;
        $customerEmail = null;
        
        if ($order->user) {
            $firstName = $order->user->name ?? '';
            $lastName = $order->user->last_name ?? '';
            $customerName = trim("{$firstName} {$lastName}");
            $customerEmail = $order->user->email;
        }

        // For booking orders, prefer the booking's customer info over the order's user (which may be the admin)
        if ($order->type === 'booking' && $order->orderable instanceof Booking) {
            $booking = $order->orderable;
            if ($booking->customer_name) {
                $customerName = $booking->customer_name;
            }
            if ($booking->customer_email) {
                $customerEmail = $booking->customer_email;
            }
        }

        if (!$customerName) {
            $customerName = $order->meta['customer_name'] ?? null;
        }
        if (!$customerEmail) {
            $customerEmail = $order->meta['customer_email'] ?? null;
        }

        $items = [];
        $subtotal = 0;
        $tax = 0;

        if ($order->type === 'ecommerce' && $order->items->isNotEmpty()) {
            foreach ($order->items as $item) {
                $unitPrice = (float) $item->unit_price;
                $quantity = (int) $item->quantity;
                $itemSubtotal = $unitPrice * $quantity;
                
                $items[] = [
                    'name' => $item->product?->name ?? 'N/A',
                    'quantity' => $quantity,
                    'unitPrice' => $unitPrice,
                    'subtotal' => $itemSubtotal,
                ];
                
                $subtotal += $itemSubtotal;
            }
        } elseif ($order->type === 'booking' && $order->orderable instanceof Booking) {
            // Get all bookings (including batch bookings)
            $allBookings = $order->getAllBookings();
            foreach ($allBookings as $booking) {
                $booking->loadMissing('services.bookable');
                if ($booking->services->isNotEmpty()) {
                    foreach ($booking->services as $service) {
                        // Use base_price for subtotal, vat_amount for tax
                        $basePrice = (float) ($service->base_price ?? $service->price ?? 0);
                        $vatAmount = (float) ($service->vat_amount ?? 0);

                        $items[] = [
                            'name' => $service->bookable?->name ?? 'N/A',
                            'quantity' => 1,
                            'unitPrice' => $basePrice,
                            'subtotal' => $basePrice,
                        ];

                        $subtotal += $basePrice;
                        $tax += $vatAmount;
                    }
                }
            }
        } elseif ($order->type === 'service_package') {
            // Gross (base + 5% VAT) lives in meta so it survives a gift-card
            // reduction of order.amount; split it back out.
            $gross = (float) ($order->meta['total_amount'] ?? $order->amount);
            $base = round($gross / 1.05, 2);
            $items[] = [
                'name' => $order->meta['service_package_name'] ?? 'Service Package',
                'quantity' => 1,
                'unitPrice' => $base,
                'subtotal' => $base,
            ];
            $subtotal = $base;
            $tax = round($gross - $base, 2);
        } elseif ($order->type === 'gift_card') {
            // Gift cards are sold at face value with no VAT.
            $amount = (float) $order->amount;
            $items[] = [
                'name' => $order->meta['gift_card_name'] ?? 'Gift Card',
                'quantity' => 1,
                'unitPrice' => $amount,
                'subtotal' => $amount,
            ];
            $subtotal = $amount;
            $tax = 0;
        }

        // For ecommerce orders, calculate tax as 5% of subtotal
        if ($order->type === 'ecommerce') {
            $tax = $subtotal * 0.05;
        }
        $total = $order->amount;

        // Gift card — resolve BEFORE the discount so the discount isn't inflated
        // by the gift-card amount (order.amount is already net of the gift card).
        $meta = $order->meta ?? [];
        $giftCardCode = $meta['gift_card_code'] ?? null;
        $giftCardAmount = (float) ($meta['gift_card_amount'] ?? 0);

        if (!$giftCardCode && $order->type === 'booking' && $order->orderable instanceof Booking) {
            $giftCardCode = $order->orderable->gift_card_code;
        }
        if ($giftCardCode && $giftCardAmount <= 0 && $order->type === 'booking' && $order->orderable instanceof Booking) {
            $usage = \App\Models\GiftCardUsage::where('used_for_type', 'booking')
                ->where('used_for_id', $order->orderable->id)
                ->first();
            if ($usage) {
                $giftCardAmount = (float) $usage->amount_used;
            }
        }

        // Calculate discount — the reduction NOT explained by the gift card.
        $discount = 0;
        $discountLabel = null;
        if ($order->type === 'booking' && $order->orderable instanceof Booking) {
            $allBookings = $order->getAllBookings();
            foreach ($allBookings as $booking) {
                // Get discount label from first booking that has one
                if (!$discountLabel && $booking->discount_label) {
                    $discountLabel = $booking->discount_label;
                }
            }
            $expectedTotal = $subtotal + $tax - $giftCardAmount;
            if ($expectedTotal > $total) {
                $discount = round($expectedTotal - $total, 2);
            }
        }

        // Tip amount (booking orders only)
        $tipAmount = 0;
        if ($order->type === 'booking' && $order->orderable instanceof Booking) {
            $tipAmount = (float) ($order->orderable->tip_amount ?? 0);
        }

        $paymentMethod = null;
        if ($order->type === 'booking' && $order->orderable instanceof Booking && $order->orderable->paid_payment_method) {
            $methods = array_map('trim', explode(',', $order->orderable->paid_payment_method));
            $paymentMethod = implode(' + ', array_map(fn($m) => ucfirst(str_replace('_', ' ', $m)), $methods));
        } elseif ($order->latestPayment?->paymentMethod) {
            $pm = $order->latestPayment->paymentMethod;
            $paymentMethod = ($pm->brand ? ucfirst($pm->brand) : 'Card') . ' ...' . $pm->last4;
        } elseif ($order->latestPayment?->provider === 'stripe') {
            $paymentMethod = 'Card';
        }

        // If gift card fully covers the order, show "Gift Card" as payment method
        $expectedTotal = $subtotal + $tax - $discount;
        if ($giftCardAmount > 0 && $giftCardAmount >= $expectedTotal) {
            $paymentMethod = 'Gift Card';
        }

        $computedTotal = $expectedTotal - $giftCardAmount;

        return [
            'order' => $order,
            'customerName' => $customerName,
            'customerEmail' => $customerEmail,
            'items' => $items,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'discount' => $discount,
            'discountLabel' => $discountLabel,
            'tipAmount' => $tipAmount,
            'giftCardCode' => $giftCardCode,
            'giftCardAmount' => $giftCardAmount,
            'total' => $computedTotal,
            'paymentMethod' => $paymentMethod,
            'reference' => $order->reference ?? "#{$order->id}",
            'isRefunded' => in_array($order->status, ['refunded', 'return_approved']),
        ];
    }
}
