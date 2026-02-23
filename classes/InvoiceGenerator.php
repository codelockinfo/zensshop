<?php
namespace CookPro;

use Dompdf\Dompdf;
use Dompdf\Options;

class InvoiceGenerator {
    private $order;
    private $storeId;
    private $db;

    public function __construct($order) {
        $this->order = $order;
        $this->storeId = $order['store_id'];
        $this->db = \Database::getInstance();
    }

    public function generate() {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        
        $dompdf = new Dompdf($options);
        
        // Fetch items
        $items = $this->db->fetchAll("SELECT oi.*, p.name as product_name, p.featured_image as product_image, p.sku as product_sku, p.currency as product_currency
                                     FROM order_items oi 
                                     LEFT JOIN products p ON (oi.product_id = p.product_id OR (oi.product_id < 1000000000 AND oi.product_id = p.id))
                                     WHERE oi.order_num = ?", [$this->order['order_number']]);
        
        // Fetch Settings
        $siteName = getStoreName($this->storeId);
        $logo = getSetting('footer_logo_image', null, $this->storeId);
        $logoType = getSetting('footer_logo_type', 'text', $this->storeId);
        
        $storeAddress = getSetting('store_address', 'Ashapuri Society, Ashwin society -2, Khodiyar nagar road, Varachha main road, surat', $this->storeId);
        $storePhone = getSetting('store_phone', '+91 7383841408', $this->storeId);
        $storeEmail = getSetting('store_email', 'zens.shop07@gmail.com', $this->storeId);
        
        $shippingAddress = json_decode($this->order['shipping_address'], true) ?? [];

        $html = $this->getTemplate($siteName, $logo, $logoType, $storeAddress, $storePhone, $storeEmail, $items, $shippingAddress);
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        return $dompdf->output();
    }

    private function getTemplate($siteName, $logo, $logoType, $address, $phone, $email, $items, $shipping) {
        // Map currency to symbol
        $currencyCode = 'INR';
        if (!empty($items) && !empty($items[0]['product_currency'])) {
            $currencyCode = $items[0]['product_currency'];
        }
        
        $currencyMap = [
            'INR' => '₹',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£'
        ];
        $currencySymbol = $currencyMap[$currencyCode] ?? $currencyCode;

        $logoHtml = '';
        if ($logoType === 'image' && !empty($logo)) {
            $logoUrl = getImageUrl($logo); // Ensure this is a full URL or absolute path
            // For DOMPDF, absolute paths or full URLs with isRemoteEnabled are best
            $logoHtml = '<img src="' . $logoUrl . '" style="height: 60px; object-fit: contain;">';
        } else {
            $logoHtml = '<h1 style="font-size: 32px; font-weight: 900; color: #022c22; font-style: italic; margin: 0;">' . htmlspecialchars($siteName) . '</h1>';
        }

        // Summary bar calculations
        $pm = strtoupper($this->order['payment_method'] ?? 'ONLINE');
        $paymentMode = ($pm === 'COD' || $pm === 'CASH_ON_DELIVERY') ? 'Cash on Delivery' : 'Online Secure';
        $status = str_replace('_', ' ', $this->order['order_status']);
        $statusStyle = (strtolower($this->order['order_status']) === 'delivered') ? 'background: #d1fae5; color: #065f46;' : 'background: #fef3c7; color: #92400e;';
        $dispatchDate = (!empty($this->order['delivery_date'])) ? date('d M Y', strtotime($this->order['delivery_date'])) : 'In Progress';

        // Address formatting
        $line1Parts = array_filter([$shipping['street'] ?? '', $shipping['address'] ?? $shipping['address_line1'] ?? '', $shipping['address_line2'] ?? '']);
        $line2Parts = array_filter([$shipping['city'] ?? '', $shipping['state'] ?? '']);
        $line2 = implode(', ', $line2Parts) . ' ' . ($shipping['zip'] ?? $shipping['pincode'] ?? $shipping['postal_code'] ?? '');
        $country = $shipping['country'] ?? 'India';

        $html = '
        <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
            <style>
                @page { margin: 20px; }
                body { font-family: "DejaVu Sans", sans-serif; font-size: 11px; color: #1e293b; margin: 0; padding: 20px; background: #fff; }
                .invoice-container { max-width: 800px; margin: 0 auto; }
                
                /* Header Area */
                .header-table { width: 100%; margin-bottom: 30px; }
                .customer-label { font-size: 8px; font-weight: bold; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 2px; }
                .customer-name { font-size: 24px; font-weight: bold; color: #0f172a; margin: 0; }
                .thank-you { font-size: 10px; color: #64748b; margin-top: 5px; }
                
                .invoice-badge { background: #064e3b; color: #fff; padding: 8px 18px; border-radius: 6px; font-size: 16px; font-weight: bold; letter-spacing: 2px; text-align: center; display: inline-block; }
                .order-id-label { font-size: 8px; font-weight: bold; color: #94a3b8; text-transform: uppercase; margin-top: 15px; }
                .order-id-value { font-size: 16px; font-weight: bold; color: #0f172a; margin: 0; }

                /* Summary Bar */
                .summary-bar { width: 100%; background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 8px; margin-bottom: 30px; }
                .summary-bar td { padding: 15px; width: 25%; }
                .summary-label { font-size: 7px; font-weight: bold; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; display: block; }
                .summary-value { font-size: 10px; font-weight: bold; color: #1e293b; margin: 0; }
                .status-badge { padding: 3px 10px; border-radius: 999px; font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }

                /* Address Grid */
                .address-table { width: 100%; margin-bottom: 30px; }
                .address-col { width: 50%; vertical-align: top; }
                .address-title { font-size: 8px; font-weight: bold; color: #059669; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #ecfdf5; padding-bottom: 5px; margin-bottom: 10px; width: 90%; }
                .address-name { font-size: 11px; font-weight: bold; color: #1e293b; margin-bottom: 5px; }
                .address-text { font-size: 10px; color: #64748b; line-height: 1.5; }

                /* Items Table */
                .items-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
                .items-table thead th { background: #1e293b; color: #fff; padding: 10px; font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; text-align: left; }
                .items-table thead th:first-child { border-top-left-radius: 6px; text-align: center; }
                .items-table thead th:last-child { border-top-right-radius: 6px; text-align: right; }
                
                .items-table tbody td { padding: 12px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
                .item-no { color: #94a3b8; font-weight: bold; text-align: center; }
                .item-img { width: 40px; height: 40px; border-radius: 4px; border: 1px solid #f1f5f9; margin-right: 10px; }
                .item-name { font-weight: bold; color: #1e293b; font-size: 10px; }
                .item-sku { font-size: 7px; font-weight: bold; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 3px; }
                .item-price { color: #475569; font-weight: normal; text-align: right; font-size: 10px; }
                .item-qty { font-weight: bold; color: #1e293b; text-align: center; }
                .item-gst { color: #64748b; text-align: center; }
                .item-total { font-weight: bold; color: #0f172a; text-align: right; font-size: 10px; }

                /* Totals Area */
                .bottom-grid { width: 100%; }
                .terms-col { width: 40%; vertical-align: top; }
                .totals-col { width: 60%; vertical-align: top; }
                
                .terms-title { font-size: 8px; font-weight: bold; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px; }
                .terms-text { font-size: 7px; color: #94a3b8; font-style: italic; line-height: 1.4; width: 80%; }
                
                .totals-table { width: 100%; border-top: 1px solid #f1f5f9; padding-top: 15px; }
                .totals-table td { padding: 5px 0; font-size: 10px; }
                .total-label { color: #64748b; font-weight: bold; text-transform: uppercase; font-size: 8px; letter-spacing: 1px; }
                .total-value { color: #0f172a; font-weight: bold; text-align: right; }
                .discount-label { color: #e11d48; }
                .discount-value { color: #e11d48; }
                
                .grand-total-box { background: #064e3b; color: #fff; padding: 15px; border-radius: 8px; margin-top: 15px; }
                .grand-total-label { font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; opacity: 0.7; margin-bottom: 3px; }
                .grand-total-value { font-size: 20px; font-weight: bold; margin: 0; }

                .footer { margin-top: 40px; text-align: center; color: #94a3b8; font-size: 8px; border-top: 1px solid #f1f5f9; padding-top: 15px; }
            </style>
        </head>
        <body>
            <div class="invoice-container">
                <table class="header-table">
                    <tr>
                        <td style="width: 60%; vertical-align: top;">
                            <div style="margin-bottom: 25px;">' . $logoHtml . '</div>
                            <div class="customer-label">Customer</div>
                            <div class="customer-name">' . htmlspecialchars($this->order['customer_name']) . '</div>
                            <div class="thank-you">Thank you for choosing ' . htmlspecialchars($siteName) . '. Your order details are below.</div>
                        </td>
                        <td style="width: 40%; text-align: right; vertical-align: top;">
                            <div class="invoice-badge">INVOICE</div>
                            <div class="order-id-label">Order Identity</div>
                            <div class="order-id-value">#' . htmlspecialchars($this->order['order_number']) . '</div>
                        </td>
                    </tr>
                </table>

                <table class="summary-bar">
                    <tr>
                        <td>
                            <span class="summary-label">Order Date</span>
                            <div class="summary-value">' . date('D, d M Y', strtotime($this->order['created_at'])) . '</div>
                        </td>
                        <td>
                            <span class="summary-label">Payment Mode</span>
                            <div class="summary-value">' . $paymentMode . '</div>
                        </td>
                        <td>
                            <span class="summary-label">Status</span>
                            <div><span class="status-badge" style="' . $statusStyle . '">' . $status . '</span></div>
                        </td>
                        <td>
                            <span class="summary-label">Dispatch Date</span>
                            <div class="summary-value">' . $dispatchDate . '</div>
                        </td>
                    </tr>
                </table>

                <table class="address-table">
                    <tr>
                        <td class="address-col">
                            <div class="address-title">Business Address</div>
                            <div class="address-name">' . htmlspecialchars($siteName) . '</div>
                            <div class="address-text">
                                ' . nl2br(htmlspecialchars($address)) . '<br>
                                <strong>Contact:</strong> ' . htmlspecialchars($phone) . '<br>
                                <strong>Email:</strong> ' . htmlspecialchars($email) . '
                            </div>
                        </td>
                        <td class="address-col">
                            <div class="address-title">Shipping To</div>
                            <div class="address-name">' . htmlspecialchars(($shipping['first_name'] ?? '') . ' ' . ($shipping['last_name'] ?? '') ?: $this->order['customer_name']) . '</div>
                            <div class="address-text">
                                ' . htmlspecialchars(implode(", ", $line1Parts)) . '<br>
                                ' . htmlspecialchars($line2) . '<br>
                                ' . htmlspecialchars($country) . '<br>
                                <strong>Ph:</strong> ' . htmlspecialchars($this->order['customer_phone'] ?? '') . '
                            </div>
                        </td>
                    </tr>
                </table>

                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width: 30px;">No.</th>
                            <th>Item Description</th>
                            <th style="width: 80px; text-align: right;">Price</th>
                            <th style="width: 40px; text-align: center;">Qty</th>
                            <th style="width: 50px; text-align: center;">GST %</th>
                            <th style="width: 80px; text-align: right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        $i = 1;
        foreach ($items as $item) {
            $taxRate = $item['gst_percent'] ?? 0;
            $unitPrice = $item['price'];
            $lineTotal = $item['subtotal'] ?? ($unitPrice * $item['quantity']);
            if (isset($item['line_total']) && $item['line_total'] > 0) $lineTotal = $item['line_total'];

            $imgHtml = '';
            if (!empty($item['product_image'])) {
                $imgUrl = getImageUrl($item['product_image']);
                $imgHtml = '<img src="' . $imgUrl . '" class="item-img">';
            }

            $html .= '
                        <tr>
                            <td class="item-no">' . $i++ . '</td>
                            <td>
                                <table style="width: 100%; border: none; border-collapse: collapse;">
                                    <tr>
                                        ' . ($imgHtml ? '<td style="width: 45px; border: none; padding: 0;">' . $imgHtml . '</td>' : '') . '
                                        <td style="border: none; padding: 0; vertical-align: middle;">
                                            <div class="item-name">' . htmlspecialchars($item['product_name']) . '</div>';
            
            if (!empty($item['variant_attributes'])) {
                $attrs = json_decode($item['variant_attributes'], true);
                if (is_array($attrs)) {
                    $attrStrings = [];
                    foreach ($attrs as $k => $v) { $attrStrings[] = ucfirst($k) . ": " . $v; }
                    $html .= '<div style="font-size: 8px; color: #64748b; margin-top: 2px;">' . htmlspecialchars(implode(", ", $attrStrings)) . '</div>';
                }
            }

            if (!empty($item['product_sku'])) {
                $html .= '<div class="item-sku">Article: ' . htmlspecialchars($item['product_sku']) . '</div>';
            }

            $html .= '
                                        </td>
                                    </tr>
                                </table>
                            </td>
                            <td class="item-price">' . $currencySymbol . ' ' . number_format($unitPrice, 2) . '</td>
                            <td class="item-qty">' . $item['quantity'] . '</td>
                            <td class="item-gst">' . ($taxRate > 0 ? (float)$taxRate . "%" : "-") . '</td>
                            <td class="item-total">' . $currencySymbol . ' ' . number_format($lineTotal, 2) . '</td>
                        </tr>';
        }

        $html .= '
                    </tbody>
                </table>

                <table class="bottom-grid">
                    <tr>
                        <td class="terms-col">
                            <div class="terms-title">Terms & Conditions</div>
                            <div class="terms-text">
                                All returns must accompany original invoice. Any claims for shortage or damage must be made within 24 hours of receipt. Goods once sold will not be taken back.
                            </div>
                        </td>
                        <td class="totals-col">
                            <table class="totals-table">
                                <tr>
                                    <td class="total-label">Base Amount</td>
                                    <td class="total-value">' . $currencySymbol . ' ' . number_format($this->order['subtotal'], 2) . '</td>
                                </tr>';
        
        if ($this->order['tax_amount'] > 0) {
            $html .= '
                                <tr>
                                    <td class="total-label">Tax (GST)</td>
                                    <td class="total-value">' . $currencySymbol . ' ' . number_format($this->order['tax_amount'], 2) . '</td>
                                </tr>';
        }

        if ($this->order['shipping_amount'] > 0) {
            $html .= '
                                <tr>
                                    <td class="total-label">Shipping Fee</td>
                                    <td class="total-value">' . $currencySymbol . ' ' . number_format($this->order['shipping_amount'], 2) . '</td>
                                </tr>';
        }

        if ($this->order['discount_amount'] > 0) {
            $html .= '
                                <tr>
                                    <td class="total-label discount-label">Privilege Discount</td>
                                    <td class="total-value discount-value">-' . $currencySymbol . ' ' . number_format($this->order['discount_amount'], 2) . '</td>
                                </tr>';
        }

        $html .= '
                            </table>
                            
                            <div class="grand-total-box">
                                <div class="grand-total-label">Payable Amount</div>
                                <div class="grand-total-value">' . $currencySymbol . ' ' . number_format($this->order['grand_total'], 2) . '</div>
                            </div>
                        </td>
                    </tr>
                </table>

                <div class="footer">
                    <p>This is a computer-generated invoice.</p>
                    <p>&copy; ' . date('Y') . ' ' . htmlspecialchars($siteName) . '. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
        
        return $html;
    }
}
