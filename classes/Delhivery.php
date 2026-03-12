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
    private $currentAuthType = 'Token'; // Added to track retry auth type
    public $lastRequest; // Added for debugging

    public function __construct($token = null, $storeId = null) {
        $this->settings = new Settings();
        
        // If storeId is not provided, try to get it from the session/context
        if (!$storeId && function_exists('getCurrentStoreId')) {
            $storeId = getCurrentStoreId();
        }

        // Settings::get() has built-in store_id fallback chain
        // Trim the token when fetching it from Settings
        $rawToken = $token ?: trim($this->settings->get('delhivery_api_token', '', $storeId));
        $mode = $this->settings->get('delhivery_mode', 'test', $storeId);
        
        // Final fallback for mode if still empty
        if (empty($mode)) $mode = 'test';
        
        // Trim any accidental spaces (redundant now, but harmless)
        $this->token = trim($rawToken);
        $this->isTest = ($mode === 'test');
        $this->baseUrl = ($mode === 'live') ? 'https://track.delhivery.com' : 'https://staging-express.delhivery.com';
        $this->expressUrl = $this->baseUrl;
    }

    public function getToken() {
        return $this->token;
    }

    public function isTest() {
        return $this->isTest;
    }

    public function getBaseUrl() {
        return $this->baseUrl;
    }

    /**
     * Check if a pincode is serviceable
     */
    public function checkPincode($pincode) {
        if (empty($pincode)) return ['success' => false, 'message' => 'Pincode is required'];

        // Delhivery has multiple API versions and authentication patterns. 
        // We try a wide variety to ensure compatibility with all account types.
        $attempts = [
            // Attempt 1: Standard Client API (Most common)
            ['url' => $this->baseUrl . '/c/api/pin-codes/json/?filter_codes=' . urlencode($pincode), 'auth_type' => 'Token'],
            
            // Attempt 2: Standard Client API with URL Token (Needed for some accounts)
            ['url' => $this->baseUrl . '/c/api/pin-codes/json/?token=' . $this->token . '&filter_codes=' . urlencode($pincode), 'auth_type' => 'Token'],
            
            // Attempt 3: Unified API domain (track.delhivery.com)
            ['url' => 'https://track.delhivery.com/c/api/pin-codes/json/?filter_codes=' . urlencode($pincode), 'auth_type' => 'Token'],

            // Attempt 4: Multi-Store / CL-API domain (cl-api.delhivery.com)
            ['url' => 'https://cl-api.delhivery.com/c/api/pin-codes/json/?filter_codes=' . urlencode($pincode), 'auth_type' => 'Token'],
            
            // Attempt 5: Bearer Token (New 2024/25 standard)
            ['url' => $this->baseUrl . '/c/api/pin-codes/json/?filter_codes=' . urlencode($pincode), 'auth_type' => 'Bearer'],

            // Attempt 7: Legacy express domain
            ['url' => 'https://express.delhivery.com/c/api/pin-codes/json/?filter_codes=' . urlencode($pincode), 'auth_type' => 'Token'],

            // Attempt 8: Header 'Token' instead of 'Authorization' (Found in some docs)
            ['url' => $this->baseUrl . '/c/api/pin-codes/json/?filter_codes=' . urlencode($pincode), 'auth_type' => 'CustomTokenHeader'],

            // Attempt 9: Path without /c/ (Unified API style)
            ['url' => $this->baseUrl . '/api/pin-codes/json/?filter_codes=' . urlencode($pincode), 'auth_type' => 'Token']
        ];

        $allAttempts = [];
        $finalResult = null;
        foreach ($attempts as $attempt) {
            $this->currentAuthType = $attempt['auth_type'];
            $response = $this->makeRequest($attempt['url'], 'GET');
            
            $isValidResponse = (isset($response['success']) && $response['success'] === true) || !empty($response['delivery_codes']);
            if ($isValidResponse && !empty($response['delivery_codes'])) {
                $finalResult = $response;
                break;
            }
            
            $allAttempts[] = [
                'url' => $attempt['url'],
                'auth' => $attempt['auth_type'],
                'http_code' => $response['http_code'] ?? 'N/A',
                'response' => $response['raw_response'] ?? 'N/A'
            ];
            $lastError = $response;
        }

        if (!$finalResult) {
            $lastError = $lastError ?? [];
            if (!isset($lastError['success'])) {
                $lastError['success'] = false;
            }
            if (!isset($lastError['message'])) {
                $lastError['message'] = 'Pincode not serviceable';
            }
            $lastError['debug_attempts'] = $allAttempts;
            return $lastError;
        }

        // Process finalResult
        $postalData = $finalResult['delivery_codes'][0]['postal_code'];
        
        // Per documentation: "Embargo" indicates temporary non-serviceable
        $remark = strtolower($postalData['remark'] ?? '');
        if (strpos($remark, 'embargo') !== false) {
            return ['success' => true, 'is_serviceable' => false, 'message' => 'Service temporarily unavailable (Embargo)'];
        }

        error_log("Delhivery Pincode Data for $pincode: " . json_encode($postalData));
        return [
            'success' => true,
            'is_serviceable' => true,
            'city' => $postalData['district'] ?? ($postalData['city'] ?? 'Unknown'),
            'state' => $postalData['state'] ?? 'Unknown',
            'cod' => ($postalData['cash'] ?? 'No') === 'Yes',
            'pickup' => ($postalData['pickup'] ?? 'No') === 'Yes',
            'prepaid' => ($postalData['prepaid'] ?? 'No') === 'Yes',
            'delivery_type' => $postalData['delivery_type'] ?? 'Standard',
            'raw' => $postalData
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
        $warehouseName = trim($this->settings->get('delhivery_warehouse_name', 'ZENSENTERPRISE-do-B2C', $storeId));
        
        // Fetch seller/return address from settings (stored as single JSON)
        $sellerJson = null;
        
        // 1. Try specific store ID
        if ($storeId) {
            $sellerJson = $this->settings->get('seller_address_data', null, $storeId);
        }
        
        // 2. Try Global (NULL store_id)
        if (empty($sellerJson) || $sellerJson === '{}' || $sellerJson === 'EMPTY') {
            $db = Database::getInstance();
            $globalRes = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'seller_address_data' AND (store_id IS NULL OR store_id = '' OR store_id = 0) LIMIT 1");
            $sellerJson = $globalRes['setting_value'] ?? '{}';
        }

        $sellerData    = json_decode($sellerJson, true) ?: [];
        $sellerAdd     = trim($sellerData['address'] ?? '');
        $sellerCity    = trim($sellerData['city'] ?? '');
        $sellerState   = trim($sellerData['state'] ?? '');
        $sellerPin     = trim($sellerData['pincode'] ?? '');
        $sellerPhone   = trim($sellerData['phone'] ?? '');
        $sellerCountry = trim($sellerData['country'] ?? 'India');
        
        $sellerName    = $this->settings->get('site_name', null, $storeId);
        if (empty($sellerName)) $sellerName = $this->settings->get('site_name', 'ZENS ENTERPRISE', null);
        
        // Prepare items description (Max 50 chars for Delhivery)
        $items = $orderData['items'] ?? [];
        $descParts = [];
        $totalQty = 0;
        foreach ($items as $item) {
            $descParts[] = $item['product_name'];
            $totalQty += $item['quantity'];
        }
        $productsDesc = substr(implode(', ', $descParts), 0, 47) . '...';

        // Map payment mode
        $paymentMethod = strtolower($orderData['payment_method'] ?? '');
        $isCOD = (strpos($paymentMethod, 'cod') !== false || strpos($paymentMethod, 'cash') !== false);
        $paymentMode = $isCOD ? 'COD' : 'Prepaid';
        $codAmount = $isCOD ? $orderData['total_amount'] : '0';

        // Smarter lookup for Address, State and Phone if missing/unknown
        $db = Database::getInstance();
        
        // Initialize with order data
        $fName  = $shippingAddr['name'] ?? $orderData['customer_name'] ?? 'Customer';
        $fAdd   = trim(preg_replace('/\s+/', ' ', ($shippingAddr['street'] ?? $shippingAddr['address_line1'] ?? $shippingAddr['address'] ?? $orderData['shipping_address_str'] ?? '')));
        $fPin   = $shippingAddr['zip'] ?? $shippingAddr['postal_code'] ?? $shippingAddr['pincode'] ?? '';
        $fCity  = $shippingAddr['city'] ?? $orderData['customer_city'] ?? '';
        $fState = $shippingAddr['state'] ?? $orderData['customer_state'] ?? '';
        $fPhone = $shippingAddr['phone'] ?? $orderData['customer_phone'] ?? '';

        // Fallback to registered customer profile if critical fields are missing
        if (!empty($orderData['user_id'])) {
            $cust = $db->fetchOne("SELECT shipping_address, phone, name FROM customers WHERE customer_id = ?", [$orderData['user_id']]);
            if ($cust) {
                if (empty($fPhone)) $fPhone = $cust['phone'] ?? '';
                if (empty($fName) || $fName === 'Customer') $fName = $cust['name'] ?? 'Customer';
                
                if (!empty($cust['shipping_address'])) {
                    $custAddr = json_decode($cust['shipping_address'], true);
                    if ($custAddr) {
                        if (empty($fAdd)) {
                            $fAdd = trim(preg_replace('/\s+/', ' ', ($custAddr['street'] ?? $custAddr['address_line1'] ?? $custAddr['address'] ?? '')));
                        }
                        if (empty($fPin))   $fPin   = $custAddr['zip'] ?? $custAddr['postal_code'] ?? $custAddr['pincode'] ?? '';
                        if (empty($fCity))  $fCity  = $custAddr['city'] ?? '';
                        if (empty($fState) || strtolower($fState) === 'unknown') $fState = $custAddr['state'] ?? '';
                    }
                }
            }
        }

        // Final formatting and hard fallbacks for mandatory fields
        $fPhone = substr(preg_replace('/[^0-9]/', '', $fPhone), -10);
        $fState = (empty($fState) || strtolower($fState) === 'unknown') ? 'Gujarat' : $fState;
        $fName  = substr($fName, 0, 30);
        $fAdd   = !empty($fAdd) ? $fAdd : 'Address Not Provided';

        $dataPayload = [
            'shipments' => [
                [
                    'name' => $fName,
                    'add' => $fAdd,
                    'pin' => $fPin,
                    'city' => $fCity,
                    'state' => $fState,
                    'country' => $shippingAddr['country'] ?? 'India',
                    'phone' => $fPhone,
                    'order' => $orderData['order_number'],
                    'payment_mode' => $paymentMode,
                    'return_pin' => $sellerPin,
                    'return_city' => $sellerCity,
                    'return_phone' => $sellerPhone ? substr(preg_replace('/[^0-9]/', '', $sellerPhone), -10) : '',
                    'return_add' => $sellerAdd,
                    'return_state' => $sellerState,
                    'return_country' => $sellerCountry,
                    'products_desc' => $productsDesc,
                    'hsn_code' => $items[0]['hsn_code'] ?? '',
                    'cod_amount' => number_format((float)$codAmount, 2, '.', ''),
                    'order_date' => date('Y-m-d', strtotime($orderData['created_at'] ?? 'now')),
                    'total_amount' => number_format((float)$orderData['total_amount'], 2, '.', ''),
                    'seller_add' => $sellerAdd,
                    'seller_name' => $sellerName,
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
        
        // Handle Duplicate Order ID error
        if (isset($result['success']) && !$result['success']) {
            $errorMsg = $result['packages'][0]['remarks'][0] ?? $result['rmk'] ?? $result['message'] ?? '';
            
            if (strpos(strtolower($errorMsg), 'duplicate order id') !== false) {
                // Append a partial timestamp to make the order ID unique for Delhivery
                $dataPayload['shipments'][0]['order'] = $orderData['order_number'] . '-' . substr(time(), -4);
                $result = $this->createShipment($dataPayload);
            }
        }
        
        if (isset($result['success']) && $result['success'] && isset($result['packages'][0]['waybill'])) {
            $waybill = $result['packages'][0]['waybill'];
            // Update order with tracking number
            $db = Database::getInstance();
            $db->execute("UPDATE orders SET tracking_number = ?, order_status = 'processing' WHERE id = ?", [$waybill, $orderId]);
            return [
                'success' => true, 
                'waybill' => $waybill, 
                'request_payload' => $dataPayload // Return the final payload used
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
        $cleanToken = preg_replace('/^(Token|Bearer)\s+/i', '', $this->token);
        $url = $this->expressUrl . "/api/p/packing_slip?wbw=$waybill&client=$cleanToken";
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

        // Staging requires .json extension for clean JSON response
        $url = $this->expressUrl . '/api/p/edit.json';
        
        // Some staging accounts require return_pin for validation even on cancel
        $storeId = $_SESSION['store_id'] ?? null;
        $sellerJson = $this->settings->get('seller_address_data', '{}', $storeId);
        $sellerData = json_decode($sellerJson, true) ?: [];
        $returnPin = $sellerData['pincode'] ?? '394101';

        $payload = [
            'waybill' => $waybill,
            'cancellation' => 'true'
            // 'return_pin' => $returnPin // Some versions need this, adding if header edit fails
        ];

        $result = $this->makeRequest($url, 'POST', $payload);
        
        // Normalize success flag for cancellation
        // Delhivery returns {"status": true} or {"status": "Success"}
        if (isset($result['status']) && ($result['status'] === true || strtolower($result['status']) === 'success')) {
            $result['success'] = true;
        } elseif (!isset($result['success'])) {
            $result['success'] = false;
            $result['message'] = $result['message'] ?? $result['remarks'][0] ?? $result['remark'] ?? 'Cancellation failed';
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
        
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        // SSL Verification Fix for Local Environments
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        // Construct Headers based on current attempt
        $cleanToken = preg_replace('/^(Token|Bearer)\s+/i', '', $this->token);
        $headers = ['Accept: */*']; // Use a generic accept header to avoid 406 errors

        if ($this->currentAuthType === 'Bearer') {
            $headers[] = 'Authorization: Bearer ' . $cleanToken;
        } elseif ($this->currentAuthType === 'CustomTokenHeader') {
            $headers[] = 'Token: ' . $cleanToken;
        } elseif ($this->currentAuthType === 'Raw') {
            $headers[] = 'Authorization: ' . $cleanToken;
        } else {
            // Default: Token prefix
            $headers[] = 'Authorization: Token ' . $cleanToken;
        }

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
        } else {
            // For GET requests, ensure token is in URL as well as some Delhivery accounts require it
            // Skip appending if 'token=' or 'client=' is already present
            if (strpos($url, 'token=') === false && strpos($url, 'client=') === false) {
                $separator = (strpos($url, '?') !== false) ? '&' : '?';
                $cleanToken = preg_replace('/^(Token|Bearer)\s+/i', '', $this->token);
                $url .= $separator . 'token=' . $cleanToken;
                curl_setopt($ch, CURLOPT_URL, $url);
            }
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Store request for debugging visibility
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
            return [
                'success' => false, 
                'message' => "cURL Error: $error",
                'http_code' => $httpCode
            ];
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

        // Always include raw response and http code in the result
        if (is_array($decoded)) {
            $decoded['raw_response'] = $response;
            $decoded['http_code'] = $httpCode;
            return $decoded;
        }

        return $decoded;
    }
}

