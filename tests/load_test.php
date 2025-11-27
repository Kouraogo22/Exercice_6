<?php

$baseUrl = 'http://localhost:8000/soap/client';

// Créer 100 clients
for ($i = 1; $i <= 100; $i++) {
    $data = [
        'nom' => 'Client' . $i,
        'prenom' => 'Test' . $i,
        'email' => 'client' . $i . '@test.bf',
        'telephone' => '+226 70 ' . rand(10, 99) . ' ' . rand(10, 99) . ' ' . rand(10, 99),
    ];

    $ch = curl_init($baseUrl . '/create');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    echo "Client $i créé - Code: $httpCode\n";
}

echo "\nTest de charge terminé!\n";
