<?php
/**
 * Delhivery API Integration Class
 */

require_once __DIR__ . '/Settings.php';

class Delhivery {
    private $token;
    private $isTest;
    private $baseUrl;
    private $expressUrl;
    private $settings;

    public function __construct($token = null, $storeId = null) {
        $this->settings = new Settings();
        
        // If storeId is not provided, try to get it from the session/context
        if (!$storeId && function_exists('getCurrentStoreId')) {
            $storeId = getCurrentStoreId();
        }

        $rawToken = $token ?: $this->settings->get('delhivery_api_token', '', $storeId);
        $mode = $this->settings->get('delhivery_mode', '', $storeId);
        
        // Fallback: If settings are missing (likely on frontend without store session)
        // Fetch them directly from the settings table
        if (empty($rawToken) || empty($mode)) {
            $db = Database::getInstance();
            $sql = "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('delhivery_api_token', 'delhivery_mode', 'delhivery_warehouse_name')";
            $params = [];
            
            if ($storeId) {
                $sql .= " AND (store_id = ? OR store_id IS NULL)";
                $params[] = $storeId;
            }
            // Ordering by store_id DESC ensures specific store settings override NULL/default settings
            $sql .= " ORDER BY store_id DESC";
            
            $allSettings = $db->fetchAll($sql, $params);
            
            $dbSettings = [];
            foreach ($allSettings as $s) {
                // Only take the first one found for each key (which will be the store-specific one due to ORDER BY)
                if (!isset($dbSettings[$s['setting_key']])) {
                    $dbSettings[$s['setting_key']] = $s['setting_value'];
                }
            }

            if (empty($rawToken)) $rawToken = $dbSettings['delhivery_api_token'] ?? '';
            if (empty($mode)) $mode = $dbSettings['delhivery_mode'] ?? 'test';
        }
        
        // Final fallback for mode if still empty
        if (empty($mode)) $mode = 'test';
        
        // Trim any accidental spaces
        $this->token = trim($rawToken);
        $this->isTest = ($mode === 'test');
        $this->baseUrl = ($mode === 'live') ? 'https://track.delhivery.com' : 'https://staging-express.delhivery.com';
        $this->expressUrl = $this->baseUrl;
    }

    public function getToken() {
        return $this->token;
    }

    /**
     * Check if a pincode is serviceable
     */
    public function checkPincode($pincode) {
        if (empty($pincode)) return ['success' => false, 'message' => 'Pincode is required'];

        // Use filter_codes as per Delhivery documentation/user snippet
        $url = $this->baseUrl . '/c/api/pin-codes/json/?filter_codes=' . urlencode($pincode);
        $response = $this->makeRequest($url, 'GET');

        // Check if makeRequest itself returned an error
        if (isset($response['success']) && $response['success'] === false) {
            return $response;
        }

        if ($response && isset($response['delivery_codes']) && !empty($response['delivery_codes'])) {
            $postalData = $response['delivery_codes'][0]['postal_code'];
            
            // Per documentation: "Embargo" indicates temporary non-serviceable
            $remark = strtolower($postalData['remark'] ?? '');
            if (strpos($remark, 'embargo') !== false) {
                return ['success' => true, 'is_serviceable' => false, 'message' => 'Service temporarily unavailable (Embargo)'];
            }

            return [
                'success' => true,
                'is_serviceable' => true,
                'cod' => (($postalData['cod'] ?? '') === 'Y' || ($postalData['cash'] ?? '') === 'Y'),
                'prepaid' => (($postalData['prepaid'] ?? $postalData['pre_paid'] ?? '') === 'Y'),
                'repl' => (($postalData['repl'] ?? '') === 'Y' || ($postalData['pickup'] ?? '') === 'Y'),
                'city' => $postalData['city'] ?? '',
                'state' => $postalData['state_code'] ?? '',
                'district' => $postalData['district'] ?? ''
            ];
        }

        return [
            'success' => true, 
            'is_serviceable' => false, 
            'message' => 'Not serviceable'
        ];
    }

    /**
     * Create a shipment in Delhivery
     * @param array $orderData Must contain shipments array and pickup_location
     */
    public function createShipment($orderData) {
        $url = $this->expressUrl . '/api/cmu/create.json';
        
        // Ensure data is structured correctly for Delhivery
        $payload = [
            'format' => 'json',
            'data' => json_encode($orderData)
        ];

        // CMU API uses form-data or raw body depending on implementation, 
        // but typically 'data' parameter is used.
        $response = $this->makeRequest($url, 'POST', $payload, true);

        return $response;
    }

    /**
     * Automatically create a shipment for a given order
     * @param int $orderId
     * @return array Result of the creation attempt
     */
    public function autoCreateShipment($orderId) {
        require_once __DIR__ . '/Order.php';
        $orderObj = new Order();
        $orderData = $orderObj->getById($orderId);

        if (!$orderData) {
            return ['success' => false, 'message' => 'Order not found'];
        }

        // RE-INITIALIZE TOKEN FOR THE SPECIFIC STORE OF THIS ORDER
        // This is crucial for multi-store environments
        if (isset($orderData['store_id'])) {
            $this->__construct(null, $orderData['store_id']);
        }

        // Check if already has tracking
        if (!empty($orderData['tracking_number'])) {
            return ['success' => false, 'message' => 'Shipment already exists', 'waybill' => $orderData['tracking_number']];
        }

        $shippingAddr = json_decode($orderData['shipping_address'] ?? '[]', true);
        $storeId = $orderData['store_id'] ?? null;
        $warehouseName = trim($this->settings->get('delhivery_warehouse_name', 'PRIMARY', $storeId));
        
        // Prepare items description
        $items = $orderData['items'] ?? [];
        $descParts = [];
        $totalQty = 0;
        foreach ($items as $item) {
            $descParts[] = $item['product_name'];
            $totalQty += $item['quantity'];
        }
        $productsDesc = substr(implode(', ', $descParts), 0, 50);

        // Map payment mode
        $paymentMethod = strtolower($orderData['payment_method'] ?? '');
        $isCOD = (strpos($paymentMethod, 'cod') !== false || strpos($paymentMethod, 'cash') !== false);
        $paymentMode = $isCOD ? 'COD' : 'Prepaid';
        $codAmount = $isCOD ? $orderData['total_amount'] : '0';

        $payload = [
            'client' => $warehouseName,
            'client_name' => $warehouseName,
            'shipments' => [
                [
                    'client' => $warehouseName,
                    'client_name' => $warehouseName,
                    'name' => substr($orderData['customer_name'], 0, 30),
                    'add' => trim(preg_replace('/\s+/', ' ', ($shippingAddr['street'] ?? '') . ' ' . ($shippingAddr['address_line1'] ?? '') . ' ' . ($shippingAddr['address_line2'] ?? ''))),
                    'pin' => $shippingAddr['zip'] ?? $shippingAddr['postal_code'] ?? '',
                    'city' => $shippingAddr['city'] ?? '',
                    'state' => $shippingAddr['state'] ?? '',
                    'country' => $shippingAddr['country'] ?? 'India',
                    'phone' => substr(preg_replace('/[^0-9]/', '', $orderData['customer_phone'] ?? '0000000000'), -10),
                    'order' => $orderData['order_number'],
                    'payment_mode' => $paymentMode,
                    'total_amount' => number_format((float)$orderData['total_amount'], 2, '.', ''),
                    'cod_amount' => number_format((float)$codAmount, 2, '.', ''),
                    'weight' => '0.5', // Default weight in Kg
                    'products_desc' => $productsDesc,
                    'quantity' => (string)$totalQty,
                    'order_date' => date('Y-m-d H:i:s', strtotime($orderData['created_at'] ?? 'now')),
                    'shipping_mode' => 'Standard', // Standard/Express
                    'address_type' => 'home',
                    'seller_name' => $this->settings->get('site_name', 'Zens Shop', $storeId) ?: 'Zens Shop',
                    'pickup_location' => $warehouseName,
                    'return_add' => trim(preg_replace('/\s+/', ' ', ($shippingAddr['street'] ?? '') . ' ' . ($shippingAddr['address_line1'] ?? '') . ' ' . ($shippingAddr['address_line2'] ?? '')))
                ]
            ],
            'pickup_location' => [
                'name' => $warehouseName
            ]
        ];
        
        // Add extra details if available from first item
        if (!empty($items[0])) {
            $payload['shipments'][0]['hsn_code'] = $items[0]['hsn_code'] ?? '';
            $payload['shipments'][0]['gst_tax_value'] = $orderData['tax_amount'];
        }

        $result = $this->createShipment($payload);
        
        if (isset($result['success']) && $result['success'] && isset($result['packages'][0]['waybill'])) {
            $waybill = $result['packages'][0]['waybill'];
            // Update order with tracking number
            $db = Database::getInstance();
            $db->execute("UPDATE orders SET tracking_number = ?, order_status = 'processing' WHERE id = ?", [$waybill, $orderId]);
            return ['success' => true, 'waybill' => $waybill];
        }

        // Prioritize specific package remarks over generic 'rmk'
        $errorMsg = $result['packages'][0]['remarks'][0] ?? $result['rmk'] ?? $result['message'] ?? 'Failed to create shipment';
        return [
            'success' => false, 
            'message' => "Delhivery Error: $errorMsg (Warehouse: $warehouseName)",
            'warehouse' => $warehouseName
        ];
    }

    /**
     * Get Expected TAT (Turnaround Time) / EDD
     * @param string $sourcePincode
     * @param string $destPincode
     */
    public function getExpectedTAT($sourcePincode, $destPincode) {
        $url = $this->baseUrl . "/c/api/tat/json/?ss=$sourcePincode&ds=$destPincode";
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Fetch Waybill API (Bulk AWB generation)
     * @param int $count Number of waybills to fetch
     */
    public function fetchWaybills($count = 1) {
        $url = $this->expressUrl . "/api/k/v1/waybill/fetch/?count=$count";
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Update Shipment details
     * @param array $data Packaging details to update
     */
    public function updateShipment($data) {
        $url = $this->expressUrl . '/api/p/edit/';
        return $this->makeRequest($url, 'POST', $data);
    }

    /**
     * Update Ewaybill for a shipment
     * @param string $waybill
     * @param string $ewaybillNumber
     */
    public function updateEwaybill($waybill, $ewaybillNumber) {
        $url = $this->expressUrl . '/api/p/edit/';
        $payload = [
            'waybill' => $waybill,
            'ewaybill' => $ewaybillNumber
        ];
        return $this->makeRequest($url, 'POST', $payload);
    }

    /**
     * Calculate Shipping Cost
     * @param array $params Contains ss, ds, wt, md, pt
     */
    public function calculateShippingCost($params) {
        $query = http_build_query($params);
        $url = $this->expressUrl . "/api/k/v1/invoice/shipping_charge/?$query";
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Generate Shipping Label (PDF)
     * @param string $waybill
     */
    public function generateLabel($waybill) {
        $url = $this->expressUrl . "/api/p/packing_slip?wbw=$waybill";
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Create Pickup Request
     * @param array $data Contains pickup_time, pickup_date, pickup_location, expected_package_count
     */
    public function createPickupRequest($data) {
        $url = $this->expressUrl . '/api/pickup/request/creation/json/';
        return $this->makeRequest($url, 'POST', $data);
    }

    /**
     * Create Client Warehouse
     * @param array $data Warehouse details
     */
    public function createWarehouse($data) {
        $url = $this->expressUrl . '/api/backend/clientwarehouse/create/';
        return $this->makeRequest($url, 'POST', $data);
    }

    /**
     * Update Client Warehouse
     * @param array $data Warehouse details (must include name or identifier)
     */
    public function updateWarehouse($data) {
        $url = $this->expressUrl . '/api/backend/clientwarehouse/edit/';
        return $this->makeRequest($url, 'POST', $data);
    }

    /**
     * Track a shipment
     */
    public function track($waybill = null, $orderId = null) {
        if (!$waybill && !$orderId) return ['success' => false, 'message' => 'Waybill or Order ID required'];

        $query = $waybill ? "waybill=$waybill" : "ref_id=$orderId";
        $url = $this->baseUrl . "/api/v1/packages/json/?$query";
        
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Cancel a shipment
     */
    public function cancel($waybill) {
        if (empty($waybill)) return ['success' => false, 'message' => 'Waybill required'];

        $url = $this->expressUrl . '/api/p/edit';
        $payload = [
            'waybill' => $waybill,
            'cancellation' => 'true'
        ];

        return $this->makeRequest($url, 'POST', $payload);
    }

    /**
     * Internal helper for cURL requests
     */
    private function makeRequest($url, $method = 'GET', $data = null, $isFormData = false) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        // SSL Verification Fix for Local Environments
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $headers = [
            'Authorization: Token ' . $this->token,
            'Accept: application/json'
        ];

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($isFormData) {
                $data['token'] = $this->token;
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                $headers[] = 'Content-Type: application/json';
            }
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // DEBUG LOGGING
        error_log("Delhivery Request URL: $url");
        error_log("Delhivery Request Method: $method");
        if ($data) error_log("Delhivery Request Data: " . json_encode($data));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("Delhivery cURL Error: $error");
            return ['success' => false, 'message' => "cURL Error: $error"];
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Delhivery Invalid JSON: " . $response);
            return [
                'success' => false, 
                'message' => "Invalid JSON from Delhivery: " . strip_tags(substr($response, 0, 500)),
                'raw' => $response
            ];
        }

        // Log non-success responses for debugging
        if (isset($decoded['success']) && !$decoded['success']) {
            error_log("Delhivery API Failure: " . json_encode($decoded));
        }

        return $decoded;
    }
}
