<?php
/**
 * Dynamic Footer
 * Displays footer with system settings from database
 * includes/footer.php
 */
// Fetch system settings from database
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings_data = $stmt->fetchAll();
    $settings = [];
    foreach ($settings_data as $setting) {
        $settings[$setting['setting_key']] = $setting['setting_value'];
    }
    
    // Set default values if not found in database
    $system_name = $settings['system_name'] ?? 'MediCare';
    $contact_email = $settings['contact_email'] ?? 'support@medicare.com';
    $contact_phone = $settings['contact_phone'] ?? '+1 (555) 123-4567';
    $contact_address = $settings['contact_address'] ?? '123 Medical Center Drive<br>City, State 12345';
    
} catch (PDOException $e) {
    // Fallback to default values if database query fails
    $system_name = 'MediCare';
    $contact_email = 'support@medicare.com';
    $contact_phone = '+1 (555) 123-4567';
    $contact_address = '123 Medical Center Drive<br>City, State 12345';
    error_log("Footer Settings Error: " . $e->getMessage());
}
?>
<!-- Footer -->
<footer class="bg-[#164A41] border-t-4 border-[#9DC88D] ml-0 md:ml-64 mt-8">
    <div class="px-4 sm:px-6 py-6 sm:py-8">
        <div class="max-w-7xl mx-auto">
            <!-- Footer Grid - 2x2 on Mobile, 4 columns on Desktop -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 lg:gap-8 mb-6 sm:mb-8">
                <!-- About Section -->
                <div class="col-span-2 lg:col-span-1">
                    <div class="flex items-center space-x-2 mb-3 sm:mb-4">
                        <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-gradient-to-br from-[#9DC88D] to-[#4D774E] flex items-center justify-center shadow-md">
                            <i class="fas fa-heartbeat text-white text-sm sm:text-lg"></i>
                        </div>
                        <h3 class="font-bold text-white text-sm sm:text-base">
                            <?= htmlspecialchars($system_name) ?>
                        </h3>
                    </div>
                    <p class="text-xs sm:text-sm text-[#9DC88D] mb-3 sm:mb-4 leading-relaxed">
                        Your trusted healthcare appointment system. Book appointments with ease and manage your health records efficiently.
                    </p>
                    <div class="flex space-x-2">
                        <a href="#" class="w-8 h-8 sm:w-9 sm:h-9 bg-[#4D774E] hover:bg-[#F1824A] rounded-lg flex items-center justify-center text-white transition-all duration-200 hover:shadow-md hover:-translate-y-1">
                            <i class="fab fa-facebook-f text-xs sm:text-sm"></i>
                        </a>
                        <a href="#" class="w-8 h-8 sm:w-9 sm:h-9 bg-[#4D774E] hover:bg-[#F1824A] rounded-lg flex items-center justify-center text-white transition-all duration-200 hover:shadow-md hover:-translate-y-1">
                            <i class="fab fa-twitter text-xs sm:text-sm"></i>
                        </a>
                        <a href="#" class="w-8 h-8 sm:w-9 sm:h-9 bg-[#4D774E] hover:bg-[#F1824A] rounded-lg flex items-center justify-center text-white transition-all duration-200 hover:shadow-md hover:-translate-y-1">
                            <i class="fab fa-instagram text-xs sm:text-sm"></i>
                        </a>
                        <a href="#" class="w-8 h-8 sm:w-9 sm:h-9 bg-[#4D774E] hover:bg-[#F1824A] rounded-lg flex items-center justify-center text-white transition-all duration-200 hover:shadow-md hover:-translate-y-1">
                            <i class="fab fa-linkedin-in text-xs sm:text-sm"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div>
                    <h3 class="font-bold text-white mb-3 sm:mb-4 text-xs sm:text-sm flex items-center">
                        <i class="fas fa-link text-[#9DC88D] mr-2 text-xs"></i>
                        Quick Links
                    </h3>
                    <ul class="space-y-2 text-xs sm:text-sm">
                        <li><a href="dashboard.php" class="text-[#9DC88D] hover:text-[#F1824A] transition-colors duration-200 flex items-center group">
                            <i class="fas fa-chevron-right text-[8px] mr-2 text-[#9DC88D] group-hover:text-[#F1824A] transition-colors"></i>Dashboard
                        </a></li>
                        <li><a href="appointments.php" class="text-[#9DC88D] hover:text-[#F1824A] transition-colors duration-200 flex items-center group">
                            <i class="fas fa-chevron-right text-[8px] mr-2 text-[#9DC88D] group-hover:text-[#F1824A] transition-colors"></i>My Appointments
                        </a></li>
                        <li><a href="book_appointment.php" class="text-[#9DC88D] hover:text-[#F1824A] transition-colors duration-200 flex items-center group">
                            <i class="fas fa-chevron-right text-[8px] mr-2 text-[#9DC88D] group-hover:text-[#F1824A] transition-colors"></i>Book Appointment
                        </a></li>
                        <li><a href="messages.php" class="text-[#9DC88D] hover:text-[#F1824A] transition-colors duration-200 flex items-center group">
                            <i class="fas fa-chevron-right text-[8px] mr-2 text-[#9DC88D] group-hover:text-[#F1824A] transition-colors"></i>Messages
                        </a></li>
                    </ul>
                </div>
                
                <!-- Support -->
                <div>
                    <h3 class="font-bold text-white mb-3 sm:mb-4 text-xs sm:text-sm flex items-center">
                        <i class="fas fa-life-ring text-[#9DC88D] mr-2 text-xs"></i>
                        Support
                    </h3>
                    <ul class="space-y-2 text-xs sm:text-sm">
                        <li><a href="#" class="text-[#9DC88D] hover:text-[#F1824A] transition-colors duration-200 flex items-center group">
                            <i class="fas fa-chevron-right text-[8px] mr-2 text-[#9DC88D] group-hover:text-[#F1824A] transition-colors"></i>Help Center
                        </a></li>
                        <li><a href="#" class="text-[#9DC88D] hover:text-[#F1824A] transition-colors duration-200 flex items-center group">
                            <i class="fas fa-chevron-right text-[8px] mr-2 text-[#9DC88D] group-hover:text-[#F1824A] transition-colors"></i>FAQs
                        </a></li>
                        <li><a href="#" class="text-[#9DC88D] hover:text-[#F1824A] transition-colors duration-200 flex items-center group">
                            <i class="fas fa-chevron-right text-[8px] mr-2 text-[#9DC88D] group-hover:text-[#F1824A] transition-colors"></i>Privacy Policy
                        </a></li>
                        <li><a href="#" class="text-[#9DC88D] hover:text-[#F1824A] transition-colors duration-200 flex items-center group">
                            <i class="fas fa-chevron-right text-[8px] mr-2 text-[#9DC88D] group-hover:text-[#F1824A] transition-colors"></i>Terms of Service
                        </a></li>
                    </ul>
                </div>
                
                <!-- Contact -->
                <div>
                    <h3 class="font-bold text-white mb-3 sm:mb-4 text-xs sm:text-sm flex items-center">
                        <i class="fas fa-phone-alt text-[#9DC88D] mr-2 text-xs"></i>
                        Contact Us
                    </h3>
                    <ul class="space-y-2 sm:space-y-3 text-xs sm:text-sm">
                        <li class="flex items-start">
                            <i class="fas fa-map-marker-alt text-[#9DC88D] mt-1 mr-2 flex-shrink-0 text-xs"></i>
                            <span class="text-[#9DC88D] leading-relaxed"><?= $contact_address ?></span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-phone text-[#9DC88D] mr-2 flex-shrink-0 text-xs"></i>
                            <span class="text-[#9DC88D]"><?= htmlspecialchars($contact_phone) ?></span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-envelope text-[#9DC88D] mr-2 flex-shrink-0 text-xs"></i>
                            <span class="text-[#9DC88D] break-all"><?= htmlspecialchars($contact_email) ?></span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Bottom Bar - Single Line on Mobile -->
            <div class="border-t border-[#4D774E] pt-4 sm:pt-6">
                <div class="flex flex-col space-y-3 sm:space-y-0 sm:flex-row sm:justify-between sm:items-center">
                    <!-- Copyright - Full Width on Mobile -->
                    <p class="text-[10px] sm:text-xs text-[#9DC88D] text-center sm:text-left">
                        © <?= date('Y') ?> <?= htmlspecialchars($system_name) ?> Healthcare System. All rights reserved.
                    </p>
                    
                    <!-- Badges - Full Width on Mobile -->
                    <div class="flex justify-center sm:justify-end items-center space-x-3 sm:space-x-4 text-[10px] sm:text-xs">
                        <span class="flex items-center text-white bg-[#4D774E] px-2 sm:px-3 py-1 rounded-full border border-[#9DC88D]/30">
                            <i class="fas fa-shield-alt text-[#9DC88D] mr-1 text-xs"></i>
                            <span class="font-medium">Secure</span>
                        </span>
                        <span class="flex items-center text-white bg-[#4D774E] px-2 sm:px-3 py-1 rounded-full border border-[#9DC88D]/30">
                            <i class="fas fa-clock text-[#9DC88D] mr-1 text-xs"></i>
                            <span class="font-medium">24/7 Support</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>
</body>
</html>