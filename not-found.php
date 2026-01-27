<?php
require_once 'includes/header.php';
?>

<div class="min-h-[60vh] flex flex-col items-center justify-center py-16 px-4 sm:px-6 lg:px-8">
    <div class="text-center">
        <!-- 404 Image -->
        <img src="<?php echo $baseUrl; ?>/assets/images/404.png" alt="404 Not Found" class="mx-auto h-64 w-auto mb-8 object-contain">
        
        <h1 class="text-4xl font-extrabold text-gray-900 tracking-tight sm:text-5xl mb-4">Page Not Found</h1>
        <p class="text-lg text-gray-500 mb-8 max-w-md mx-auto">It looks like the page you're looking for doesn't exist or has been moved.</p>
        
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="<?php echo $baseUrl; ?>" class="inline-flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-full text-white bg-black hover:bg-gray-800 transition md:text-lg">
                <i class="fas fa-home mr-2"></i> Back to Home
            </a>
            <a href="<?php echo $baseUrl; ?>/shop.php" class="inline-flex items-center justify-center px-8 py-3 border border-gray-300 text-base font-medium rounded-full text-gray-700 bg-white hover:bg-gray-50 transition md:text-lg">
                <i class="fas fa-shopping-bag mr-2"></i> Continue Shopping
            </a>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>
