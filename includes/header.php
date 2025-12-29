<?php
require_once __DIR__ . '/../classes/Cart.php';
$cart = new Cart();
$cartCount = $cart->getCount();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>Milano - Elegant Jewelry Store</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/oecom/assets/css/main.css">
    
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="font-body">
    <!-- Top Bar -->
    <div class="bg-black text-white text-sm py-2">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <div>
                <span>100% secure online payment</span>
            </div>
            <div>
                <a href="/oecom/contact.php" class="hover:text-gray-300">Contact Us</a>
            </div>
        </div>
    </div>
    
    <!-- Main Navigation -->
    <nav class="bg-white sticky top-0 z-50 header-shadow">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between h-20">
                <!-- Logo -->
                <div class="flex-shrink-0">
                    <a href="/oecom/" class="text-3xl font-heading font-bold text-black">milano</a>
                </div>
                
                <!-- Desktop Navigation -->
                <div class="hidden md:flex items-center space-x-8">
                    <a href="/oecom/" class="text-gray-800 hover:text-primary transition relative group">
                        Home
                        <i class="fas fa-chevron-down text-xs ml-1"></i>
                    </a>
                    <!-- Shop with Dropdown -->
                    <div class="relative shop-menu-parent">
                        <a href="/oecom/collections.php" class="text-gray-800 hover:text-primary transition relative group flex items-center">
                            Shop
                            <i class="fas fa-chevron-down text-xs ml-1"></i>
                        </a>
                        <!-- Shop Dropdown Menu -->
                        <div class="shop-dropdown absolute top-full left-0 mt-2 bg-white shadow-lg rounded-lg py-2 min-w-[200px] hidden z-50 border border-gray-200">
                            <a href="/oecom/collections.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 hover:text-primary transition">
                                Collections
                            </a>
                            <a href="/oecom/shop.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 hover:text-primary transition">
                                All Products
                            </a>
                        </div>
                    </div>
                    <!-- Products with Mega Menu -->
                    <div class="relative mega-menu-parent">
                        <a href="/oecom/products.php" class="text-gray-800 hover:text-primary transition flex items-center">
                            Products
                            <i class="fas fa-chevron-down text-xs ml-1"></i>
                        </a>
                        <!-- Mega Menu Dropdown -->
                        <div class="mega-menu mega-menu-products">
                            <div class="grid grid-cols-3 gap-8">
                                <!-- Column 1: Shop Layouts -->
                                <div>
                                    <h3 class="font-bold text-gray-900 mb-4 text-lg">Shop Layouts</h3>
                                    <ul class="space-y-3">
                                        <li><a href="/oecom/shop.php?layout=filter-left" class="text-gray-600 hover:text-primary transition">Filter left sidebar</a></li>
                                        <li><a href="/oecom/shop.php?layout=filter-right" class="text-gray-600 hover:text-primary transition">Filter right sidebar</a></li>
                                        <li>
                                            <a href="/oecom/shop.php?layout=horizontal" class="text-gray-600 hover:text-primary transition flex items-center">
                                                Horizontal filter
                                                <span class="ml-2 bg-red-500 text-white text-xs px-2 py-0.5 rounded-full">HOT</span>
                                            </a>
                                        </li>
                                        <li><a href="/oecom/shop.php?layout=drawer" class="text-gray-600 hover:text-primary transition">Filter drawer</a></li>
                                        <li><a href="/oecom/shop.php?layout=grid-3" class="text-gray-600 hover:text-primary transition">Grid 3 columns</a></li>
                                        <li><a href="/oecom/shop.php?layout=grid-4" class="text-gray-600 hover:text-primary transition">Grid 4 columns</a></li>
                                        <li><a href="/oecom/shop.php" class="text-gray-600 hover:text-primary transition">All collections</a></li>
                                    </ul>
                                </div>
                                
                                <!-- Column 2: Shop Pages -->
                                <div>
                                    <h3 class="font-bold text-gray-900 mb-4 text-lg">Shop Pages</h3>
                                    <ul class="space-y-3">
                                        <li><a href="/oecom/collection-v1.php" class="text-gray-600 hover:text-primary transition">Collection list v1</a></li>
                                        <li>
                                            <a href="/oecom/collection-v2.php" class="text-gray-600 hover:text-primary transition flex items-center">
                                                Collection list v2
                                                <span class="ml-2 bg-blue-500 text-white text-xs px-2 py-0.5 rounded-full">NEW</span>
                                            </a>
                                        </li>
                                        <li><a href="/oecom/shop.php?scroll=infinity" class="text-gray-600 hover:text-primary transition">Infinity scroll</a></li>
                                        <li><a href="/oecom/shop.php?load=more" class="text-gray-600 hover:text-primary transition">Load more button</a></li>
                                        <li><a href="/oecom/shop.php?pagination=1" class="text-gray-600 hover:text-primary transition">Pagination page</a></li>
                                        <li><a href="/oecom/banner-collection.php" class="text-gray-600 hover:text-primary transition">Banner collection</a></li>
                                    </ul>
                                </div>
                                
                                <!-- Column 3: Featured Categories -->
                                <div class="space-y-4">
                                    <!-- Bracelets Card -->
                                    <a href="/oecom/category.php?slug=bracelets" class="block category-card">
                                        <div class="relative overflow-hidden rounded-lg mb-2">
                                            <img src="https://images.unsplash.com/photo-1611591437281-460bfbe1220a?w=300&h=400&fit=crop" 
                                                 alt="Bracelets" 
                                                 class="w-full h-64 object-cover transition-transform duration-300">
                                        </div>
                                        <div class="w-full bg-white border-2 border-gray-200 text-gray-800 py-2 px-4 rounded-lg hover:border-primary hover:text-primary transition font-medium text-center">
                                            Bracelets
                                        </div>
                                    </a>
                                    
                                    <!-- Rings Card -->
                                    <a href="/oecom/category.php?slug=rings" class="block category-card">
                                        <div class="relative overflow-hidden rounded-lg mb-2">
                                            <img src="https://images.unsplash.com/photo-1603561591411-07134e71a2a9?w=300&h=400&fit=crop" 
                                                 alt="Rings" 
                                                 class="w-full h-64 object-cover transition-transform duration-300">
                                        </div>
                                        <div class="w-full bg-white border-2 border-gray-200 text-gray-800 py-2 px-4 rounded-lg hover:border-primary hover:text-primary transition font-medium text-center">
                                            Rings
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Pages with Dropdown Menu -->
                    <div class="relative pages-menu-parent">
                        <a href="/oecom/pages.php" class="text-gray-800 hover:text-primary transition flex items-center">
                            Pages
                            <i class="fas fa-chevron-down text-xs ml-1"></i>
                        </a>
                        <!-- Pages Dropdown Menu -->
                        <div class="pages-dropdown">
                            <ul class="space-y-2">
                                <li>
                                    <a href="/oecom/about.php" class="text-gray-600 hover:text-primary transition block py-2 px-4">About us</a>
                                </li>
                                <li>
                                    <a href="/oecom/contact.php" class="text-gray-600 hover:text-primary transition block py-2 px-4">Contact us</a>
                                </li>
                                <li>
                                    <a href="/oecom/sale.php" class="text-gray-600 hover:text-primary transition flex items-center py-2 px-4">
                                        Sale
                                        <span class="ml-2 bg-red-500 text-white text-xs px-2 py-0.5 rounded-full">HOT</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="/oecom/store.php" class="text-gray-600 hover:text-primary transition block py-2 px-4">Our store</a>
                                </li>
                                <li>
                                    <a href="/oecom/faq.php" class="text-gray-600 hover:text-primary transition block py-2 px-4">FAQ</a>
                                </li>
                                <li>
                                    <a href="/oecom/wishlist.php" class="text-gray-600 hover:text-primary transition block py-2 px-4">Wishlist</a>
                                </li>
                                <li>
                                    <a href="/oecom/compare.php" class="text-gray-600 hover:text-primary transition block py-2 px-4">Compare</a>
                                </li>
                                <li>
                                    <a href="/oecom/location.php" class="text-gray-600 hover:text-primary transition block py-2 px-4">Store location</a>
                                </li>
                                <li>
                                    <a href="/oecom/recently-viewed.php" class="text-gray-600 hover:text-primary transition block py-2 px-4">Recently viewed products</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <a href="/oecom/blog.php" class="text-gray-800 hover:text-primary transition relative group">
                        Blog
                        <i class="fas fa-chevron-down text-xs ml-1"></i>
                    </a>
                    <a href="#" class="text-gray-800 hover:text-primary transition">Buy Theme!</a>
                </div>
                
                <!-- Right Icons -->
                <div class="flex items-center space-x-4">
                    <!-- Search -->
                    <button class="text-gray-800 hover:text-primary transition" id="searchBtn">
                        <i class="fas fa-search text-xl"></i>
                    </button>
                    
                    <!-- User Account -->
                    <a href="/oecom/account.php" class="text-gray-800 hover:text-primary transition">
                        <i class="fas fa-user text-xl"></i>
                    </a>
                    
                    <!-- Wishlist -->
                    <a href="/oecom/wishlist.php" class="text-gray-800 hover:text-primary transition relative">
                        <i class="fas fa-heart text-xl"></i>
                        <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">0</span>
                    </a>
                    
                    <!-- Cart -->
                    <button class="text-gray-800 hover:text-primary transition relative" id="cartBtn">
                        <i class="fas fa-shopping-cart text-xl"></i>
                        <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center cart-count"><?php echo $cartCount; ?></span>
                    </button>
                    
                    <!-- Language/Currency -->
                    <select class="text-gray-800 border-none bg-transparent cursor-pointer focus:outline-none">
                        <option>United States (USD $)</option>
                        <option>United Kingdom (GBP £)</option>
                        <option>Europe (EUR €)</option>
                    </select>
                </div>
                
                <!-- Mobile Menu Button -->
                <button class="md:hidden text-gray-800" id="mobileMenuBtn">
                    <i class="fas fa-bars text-xl"></i>
                </button>
            </div>
            
            <!-- Mobile Navigation -->
            <div class="hidden md:hidden pb-4" id="mobileMenu">
                <div class="flex flex-col space-y-4">
                    <a href="/oecom/" class="text-gray-800 hover:text-primary transition">Home</a>
                    <a href="/oecom/shop.php" class="text-gray-800 hover:text-primary transition">Shop</a>
                    <a href="/oecom/products.php" class="text-gray-800 hover:text-primary transition">Products</a>
                    <a href="/oecom/pages.php" class="text-gray-800 hover:text-primary transition">Pages</a>
                    <a href="/oecom/blog.php" class="text-gray-800 hover:text-primary transition">Blog</a>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Search Overlay -->
    <div class="hidden fixed inset-0 bg-black bg-opacity-50 z-50" id="searchOverlay">
        <div class="container mx-auto px-4 pt-20">
            <div class="max-w-2xl mx-auto">
                <input type="text" placeholder="Search products..." class="w-full px-6 py-4 text-lg rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                <button class="absolute right-8 top-24 text-gray-500 hover:text-white" id="closeSearch">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>
    </div>

