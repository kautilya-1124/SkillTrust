<?php

require_once "includes/whatsapp.php";

$result = sendWhatsApp("9876543210", "Test message 🚀");

echo "<pre>";
print_r($result);