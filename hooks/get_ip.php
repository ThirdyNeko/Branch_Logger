<?php
header('Content-Type: application/json');
echo json_encode(['ip' => QA_CLIENT_IP]);
