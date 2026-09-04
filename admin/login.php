<?php
// Admin sign-in now goes through the main student login form — logging in
// with the fixed admin email + password there routes to the admin panel.
header('Location: ../login.html');
