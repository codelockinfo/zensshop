<?php
require_once __DIR__ . '/../classes/Auth.php';

$auth = new Auth();
$auth->requireLogin();

$pageTitle = 'Taskboard';
require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold">Taskboard</h1>
    <p class="text-gray-600">Dashboard > Taskboard</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <!-- To Do -->
    <div class="admin-card">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold">To Do</h2>
            <span class="bg-gray-200 text-gray-700 text-xs px-2 py-1 rounded">3</span>
        </div>
        <div class="space-y-3">
            <div class="bg-gray-50 p-3 rounded border border-gray-200">
                <p class="font-semibold text-sm">Review new product submissions</p>
                <p class="text-xs text-gray-500 mt-1">Due: Today</p>
            </div>
            <div class="bg-gray-50 p-3 rounded border border-gray-200">
                <p class="font-semibold text-sm">Update product descriptions</p>
                <p class="text-xs text-gray-500 mt-1">Due: Tomorrow</p>
            </div>
            <div class="bg-gray-50 p-3 rounded border border-gray-200">
                <p class="font-semibold text-sm">Process pending orders</p>
                <p class="text-xs text-gray-500 mt-1">Due: This week</p>
            </div>
        </div>
    </div>
    
    <!-- In Progress -->
    <div class="admin-card">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold">In Progress</h2>
            <span class="bg-blue-200 text-blue-700 text-xs px-2 py-1 rounded">2</span>
        </div>
        <div class="space-y-3">
            <div class="bg-blue-50 p-3 rounded border border-blue-200">
                <p class="font-semibold text-sm">Add new product images</p>
                <p class="text-xs text-gray-500 mt-1">In progress</p>
            </div>
            <div class="bg-blue-50 p-3 rounded border border-blue-200">
                <p class="font-semibold text-sm">Update inventory levels</p>
                <p class="text-xs text-gray-500 mt-1">In progress</p>
            </div>
        </div>
    </div>
    
    <!-- Done -->
    <div class="admin-card">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold">Done</h2>
            <span class="bg-green-200 text-green-700 text-xs px-2 py-1 rounded">5</span>
        </div>
        <div class="space-y-3">
            <div class="bg-green-50 p-3 rounded border border-green-200">
                <p class="font-semibold text-sm line-through">Complete monthly report</p>
                <p class="text-xs text-gray-500 mt-1">Completed yesterday</p>
            </div>
            <div class="bg-green-50 p-3 rounded border border-green-200">
                <p class="font-semibold text-sm line-through">Review customer feedback</p>
                <p class="text-xs text-gray-500 mt-1">Completed 2 days ago</p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>


