<?php

function jsonResponse($data, $httpCode = 200) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($httpCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}