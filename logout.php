<?php
include 'config.php';

session_destroy();
jsonResponse(['success' => 'Logged out']);
