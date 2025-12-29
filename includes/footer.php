    <!-- Footer -->
    <footer class="bg-gray-900 text-white mt-20">
        <div class="container mx-auto px-4 py-12">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-8">
                <!-- About Us -->
                <div>
                    <h3 class="text-xl font-heading font-bold mb-4">About us</h3>
                    <p class="text-gray-400 mb-4">We create ethical designs that celebrate the beauty of nature and craftsmanship.</p>
                    <div class="space-y-2 text-gray-400">
                        <p><i class="fas fa-map-marker-alt mr-2"></i>123 Jewelry Street, New York, NY 10001</p>
                        <p><i class="fas fa-phone mr-2"></i>+1 (555) 123-4567</p>
                        <p><i class="fas fa-envelope mr-2"></i>info@milano.com</p>
                    </div>
                </div>
                
                <!-- Our Company -->
                <div>
                    <h3 class="text-xl font-heading font-bold mb-4">Our Company</h3>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="/oecom/about.php" class="hover:text-white transition">About Us</a></li>
                        <li><a href="/oecom/contact.php" class="hover:text-white transition">Contact Us</a></li>
                        <li><a href="/oecom/store.php" class="hover:text-white transition">Our Store</a></li>
                        <li><a href="/oecom/location.php" class="hover:text-white transition">Store Location</a></li>
                        <li><a href="/oecom/faq.php" class="hover:text-white transition">FAQ</a></li>
                    </ul>
                </div>
                
                <!-- Quick Links -->
                <div>
                    <h3 class="text-xl font-heading font-bold mb-4">Quick links</h3>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="/oecom/privacy.php" class="hover:text-white transition">Privacy Policy</a></li>
                        <li><a href="/oecom/terms.php" class="hover:text-white transition">Terms & Conditions</a></li>
                        <li><a href="/oecom/sale.php" class="hover:text-white transition">Sale</a></li>
                        <li><a href="/oecom/size-guide.php" class="hover:text-white transition">Size guide</a></li>
                        <li><a href="/oecom/wishlist.php" class="hover:text-white transition">Wishlist</a></li>
                        <li><a href="/oecom/compare.php" class="hover:text-white transition">Compare</a></li>
                    </ul>
                </div>
                
                <!-- Shop Categories -->
                <div>
                    <h3 class="text-xl font-heading font-bold mb-4">Shop Categories</h3>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="/oecom/category.php?cat=bracelets" class="hover:text-white transition">Bracelets</a></li>
                        <li><a href="/oecom/category.php?cat=earrings" class="hover:text-white transition">Earrings</a></li>
                        <li><a href="/oecom/category.php?cat=rings" class="hover:text-white transition">Rings</a></li>
                        <li><a href="/oecom/category.php?cat=necklaces" class="hover:text-white transition">Necklaces</a></li>
                        <li><a href="/oecom/category.php?cat=jewelry-sets" class="hover:text-white transition">Jewelry Sets</a></li>
                    </ul>
                </div>
                
                <!-- Follow Us -->
                <div>
                    <h3 class="text-xl font-heading font-bold mb-4">Follow Us</h3>
                    <div class="flex space-x-4 mb-4">
                        <a href="#" class="text-gray-400 hover:text-white transition"><i class="fab fa-facebook text-2xl"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white transition"><i class="fab fa-instagram text-2xl"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white transition"><i class="fab fa-twitter text-2xl"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white transition"><i class="fab fa-youtube text-2xl"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white transition"><i class="fab fa-tiktok text-2xl"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white transition"><i class="fab fa-pinterest text-2xl"></i></a>
                    </div>
                </div>
            </div>
            
            <!-- Bottom Bar -->
            <div class="border-t border-gray-800 mt-8 pt-8 flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <select class="bg-transparent text-gray-400 border-none cursor-pointer">
                        <option>United States (USD $)</option>
                    </select>
                </div>
                <div class="text-gray-400 text-center md:text-left">
                    © <?php echo date('Y'); ?> Milano store. All rights reserved.
                </div>
                <div class="flex space-x-4 mt-4 md:mt-0">
                    <img src="https://via.placeholder.com/40x25/1a5d3a/ffffff?text=VISA" alt="Visa" class="h-6">
                    <img src="https://via.placeholder.com/40x25/1a5d3a/ffffff?text=MC" alt="Mastercard" class="h-6">
                    <img src="https://via.placeholder.com/40x25/1a5d3a/ffffff?text=AMEX" alt="American Express" class="h-6">
                    <img src="https://via.placeholder.com/40x25/1a5d3a/ffffff?text=PP" alt="PayPal" class="h-6">
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Side Cart -->
    <div class="fixed right-0 top-0 h-full w-full md:w-96 bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300" id="sideCart">
        <div class="flex flex-col h-full">
            <!-- Cart Header -->
            <div class="flex items-center justify-between p-6 border-b">
                <h2 class="text-2xl font-heading font-bold">Shopping Cart</h2>
                <button class="text-gray-500 hover:text-gray-800" id="closeCart">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <!-- Cart Items -->
            <div class="flex-1 overflow-y-auto p-6" id="cartItems">
                <div class="text-center text-gray-500 py-8">
                    <i class="fas fa-shopping-cart text-4xl mb-4"></i>
                    <p>Your cart is empty</p>
                </div>
            </div>
            
            <!-- Cart Footer -->
            <div class="border-t p-6">
                <div class="flex justify-between items-center mb-4">
                    <span class="text-lg font-semibold">Total:</span>
                    <span class="text-xl font-bold" id="cartTotal">$0.00</span>
                </div>
                <a href="/oecom/cart.php" class="block w-full bg-primary text-white text-center py-3 rounded-lg hover:bg-primary-dark transition mb-2">
                    View Cart
                </a>
                <a href="/oecom/checkout.php" class="block w-full bg-black text-white text-center py-3 rounded-lg hover:bg-gray-800 transition">
                    Checkout
                </a>
            </div>
        </div>
    </div>
    
    <!-- Cart Overlay -->
    <div class="hidden fixed inset-0 bg-black bg-opacity-50 z-40" id="cartOverlay"></div>
    
    <!-- Scripts -->
    <script src="/oecom/assets/js/main.js"></script>
    <script src="/oecom/assets/js/cart.js"></script>
    <script src="/oecom/assets/js/product-cards.js"></script>
</body>
</html>

