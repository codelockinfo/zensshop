<?php
/**
 * Email/SMTP Configuration
 * Now loaded from database settings table
 */

require_once __DIR__ . '/../classes/Settings.php';

// Load email configuration from database
Settings::loadEmailConfig();
