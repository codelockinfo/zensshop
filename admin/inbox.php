<?php
require_once __DIR__ . '/../classes/Auth.php';

$auth = new Auth();
$auth->requireLogin();

$pageTitle = 'Inbox';
require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold">Inbox</h1>
    <p class="text-gray-600">Dashboard > Inbox</p>
</div>

<div class="admin-card">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-bold">Messages</h2>
        <span class="bg-red-500 text-white text-xs px-3 py-1 rounded-full">27 unread</span>
    </div>
    
    <div class="space-y-4">
        <div class="border-b border-gray-200 pb-4">
            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center text-white font-bold">
                    JD
                </div>
                <div class="flex-1">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold">John Doe</h3>
                        <span class="text-sm text-gray-500">2 hours ago</span>
                    </div>
                    <p class="text-gray-600 mt-1">Order #1234 - Question about delivery</p>
                    <p class="text-sm text-gray-500 mt-1">When will my order be delivered?</p>
                </div>
                <span class="w-3 h-3 bg-blue-500 rounded-full"></span>
            </div>
        </div>
        
        <div class="border-b border-gray-200 pb-4">
            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center text-white font-bold">
                    JS
                </div>
                <div class="flex-1">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold">Jane Smith</h3>
                        <span class="text-sm text-gray-500">5 hours ago</span>
                    </div>
                    <p class="text-gray-600 mt-1">Product inquiry - Ring size</p>
                    <p class="text-sm text-gray-500 mt-1">What size should I order?</p>
                </div>
                <span class="w-3 h-3 bg-blue-500 rounded-full"></span>
            </div>
        </div>
        
        <div class="border-b border-gray-200 pb-4">
            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 bg-purple-500 rounded-full flex items-center justify-center text-white font-bold">
                    MW
                </div>
                <div class="flex-1">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold">Mike Wilson</h3>
                        <span class="text-sm text-gray-500">1 day ago</span>
                    </div>
                    <p class="text-gray-600 mt-1">Return request - Order #5678</p>
                    <p class="text-sm text-gray-500 mt-1">I would like to return this item...</p>
                </div>
            </div>
        </div>
        
        <p class="text-center text-gray-500 py-8">More messages will appear here...</p>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>


