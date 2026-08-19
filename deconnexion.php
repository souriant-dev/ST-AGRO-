<?php
require_once __DIR__ . '/includes/auth.php';
deconnecterUtilisateur();
header('Location: /st-agro/index.php');
exit;
