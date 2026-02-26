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
    public $lastRequest; // Added for debugging

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

            error_log("Delhivery Pincode Data for $pincode: " . json_encode($postalData));
            return [
                'success' => true,
                'is_serviceable' => true,
                'cod' => (($postalData['cod'] ?? '') === 'Y' || ($postalData['cash'] ?? '') === 'Y' || ($postalData['is_cod'] ?? '') === 'Y'),
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
        
        // Pass as an array so makeRequest can handle the URL encoding properly
        $payload = [
            'format' => 'json',
            'data' => json_encode($orderData)
        ];

        return $this->makeRequest($url, 'POST', $payload, true);
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
        $dataPayload = [
            'shipments' => [
                [
                    'name' => substr($orderData['customer_name'], 0, 30),
                    'add' => trim(preg_replace('/\s+/', ' ', ($shippingAddr['street'] ?? '') . ' ' . ($shippingAddr['address_line1'] ?? '') . ' ' . ($shippingAddr['address_line2'] ?? ''))),
                    'pin' => $shippingAddr['zip'] ?? $shippingAddr['postal_code'] ?? '',
                    'city' => $shippingAddr['city'] ?? '',
                    'state' => $shippingAddr['state'] ?? '',
                    'country' => $shippingAddr['country'] ?? 'India',
                    'phone' => substr(preg_replace('/[^0-9]/', '', $orderData['customer_phone'] ?? '0000000000'), -10),
                    'order' => $orderData['order_number'],
                    'payment_mode' => $paymentMode,
                    'return_pin' => '',
                    'return_city' => '',
                    'return_phone' => '',
                    'return_add' => '',
                    'return_state' => '',
                    'return_country' => '',
                    'products_desc' => $productsDesc,
                    'hsn_code' => $items[0]['hsn_code'] ?? '',
                    'cod_amount' => number_format((float)$codAmount, 2, '.', ''),
                    'order_date' => date('Y-m-d H:i:s', strtotime($orderData['created_at'] ?? 'now')),
                    'total_amount' => number_format((float)$orderData['total_amount'], 2, '.', ''),
                    'seller_add' => '',
                    'seller_name' => $this->settings->get('site_name', 'Zens Shop', $storeId) ?: 'Zens Shop',
                    'seller_inv' => '',
                    'quantity' => (string)$totalQty,
                    'waybill' => '',
                    'shipment_width' => number_format((float)($items[0]['width'] > 0 ? $items[0]['width'] : 10), 2, '.', ''),
                    'shipment_height' => number_format((float)($items[0]['height'] > 0 ? $items[0]['height'] : 10), 2, '.', ''),
                    'weight' => number_format((float)($orderData['total_weight'] ?: 0.5), 2, '.', ''),
                    'shipping_mode' => 'Surface',
                    'address_type' => 'home'
                ]
            ],
            'pickup_location' => [
                'name' => $warehouseName
            ]
        ];

        $result = $this->createShipment($dataPayload);
        
        if (isset($result['success']) && $result['success'] && isset($result['packages'][0]['waybill'])) {
            $waybill = $result['packages'][0]['waybill'];
            // Update order with tracking number
            $db = Database::getInstance();
            $db->execute("UPDATE orders SET tracking_number = ?, order_status = 'processing' WHERE id = ?", [$waybill, $orderId]);
            return [
                'success' => true, 
                'waybill' => $waybill, 
                'request_payload' => $dataPayload // Return the payload for visibility
            ];
        }

        // Prioritize specific package remarks over generic 'rmk'
        $errorMsg = $result['packages'][0]['remarks'][0] ?? $result['rmk'] ?? $result['message'] ?? 'Failed to create shipment';
        return [
            'success' => false, 
            'message' => "$errorMsg (Warehouse: $warehouseName)",
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
        
        $result = $this->makeRequest($url, 'GET');
        
        // Normalize success flag for tracking
        if (isset($result['ShipmentData']) && !empty($result['ShipmentData'])) {
            $result['success'] = true;
        } else {
            $result['success'] = false;
            $result['message'] = $result['Error'] ?? 'No tracking data found';
        }
        
        return $result;
    }

    /**
     * Cancel a shipment
     */
    public function cancel($waybill) {
        if (empty($waybill)) return ['success' => false, 'message' => 'Waybill required'];

        // Added trailing slash as required by some Delhivery API versions
        $url = $this->expressUrl . '/api/p/edit/';
        $payload = [
            'waybill' => $waybill,
            'cancellation' => 'true'
        ];

        $result = $this->makeRequest($url, 'POST', $payload);
        
        // Normalize success flag for cancellation
        // Delhivery usually returns {"status": "Success"}
        if (isset($result['status']) && (strtolower($result['status']) === 'success' || $result['status'] === true)) {
            $result['success'] = true;
        } elseif (!isset($result['success'])) {
            $result['success'] = false;
            $result['message'] = $result['message'] ?? $result['remarks'][0] ?? 'Cancellation failed';
        }
        
        return $result;
    }

    /**
     * Internal helper for cURL requests
     */
    private function makeRequest($url, $method = 'GET', $data = null, $isFormData = false) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Added timeout as per snippet
        
        // SSL Verification Fix for Local Environments
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $headers = [
            'Authorization: Token ' . $this->token,
            'Accept: application/json'
        ];

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            
            if (strpos($url, 'cmu/create.json') !== false || $isFormData) {
                // For CMU creation, Delhivery requires URL-encoded body: format=json&data=ENCODED_JSON
                $body = is_array($data) ? http_build_query($data) : $data;
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            } else {
                $finalData = is_string($data) ? $data : json_encode($data);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $finalData);
                $headers[] = 'Content-Type: application/json';
            }
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Store request for debugging visibility in Network tab
        $this->lastRequest = [
            'url' => $url,
            'method' => $method,
            'headers' => $headers,
            'payload' => $data
        ];

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
            $msg = empty($response) ? "Empty response from Delhivery API" : "Invalid JSON: " . substr($response, 0, 100);
            return [
                'success' => false, 
                'message' => "Delhivery Error: " . $msg,
                'raw_response' => $response,
                'http_code' => $httpCode
            ];
        }

        // Log non-success responses for debugging
        if (isset($decoded['success']) && !$decoded['success']) {
            error_log("Delhivery API Failure: " . json_encode($decoded));
        }

        return $decoded;
    }
}
