<?php
require_once('crest.php');


$json = file_get_contents('php://input');
$data = json_decode($json, true);


if ($data === null) {
    file_put_contents('error_log.txt', date('Y-m-d H:i:s') . " - Invalid JSON: " . $json . "\n", FILE_APPEND);
    http_response_code(400);
    exit('Invalid JSON');
}


//file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - Received data: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);

try {
    
    $leadFields = [
        'TITLE' => '🚘 Новая заявка!',
        'STATUS_ID' => 'NEW',
        'OPENED' => 'Y',
        'ASSIGNED_BY_ID' => 1, 
        'SOURCE_ID' => 'WEB',
        'COMMENTS' => generateComments($data),
    ];

    
    if (!empty($data['name'])) {
        $leadFields['NAME'] = $data['name'];
    } elseif (!empty($data['first_name']) || !empty($data['last_name'])) {
        $leadFields['NAME'] = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
    }

    
    if (!empty($data['phone'])) {
        $leadFields['PHONE'] = [
            [
                'VALUE' => $data['phone'],
                'VALUE_TYPE' => 'WORK',
            ]
        ];
    }

    
    $result = CRest::call(
        'crm.lead.add',
        [
            'fields' => $leadFields,
            'params' => [
                'REGISTER_SONET_EVENT' => 'Y',
            ]
        ]
    );

    
    if (!empty($result['result'])) {
        $leadId = $result['result'];
        
        
        //file_put_contents('success_log.txt', date('Y-m-d H:i:s') . " - Lead created successfully. ID: " . $leadId . " Name: " . ($leadFields['NAME'] ?? 'не указано') . " Phone: " . ($data['phone'] ?? 'не указан') . "\n", FILE_APPEND);
        
        http_response_code(200);
        echo 'OK';
        
    } else {
        
        file_put_contents('error_log.txt', date('Y-m-d H:i:s') . " - Bitrix error: " . json_encode($result) . "\n", FILE_APPEND);
        
        http_response_code(500);
        echo 'Error creating lead';
    }

} catch (Exception $e) {
    
    file_put_contents('error_log.txt', date('Y-m-d H:i:s') . " - Exception: " . $e->getMessage() . "\n", FILE_APPEND);
    
    http_response_code(500);
    echo 'Internal server error';
}

/**
 * Генерирует комментарий для лида со всеми остальными данными
 */
function generateComments($data) {
    $comments = [];
    
    
    $excludeFields = [
        'name', 'first_name', 'last_name', 'phone', 'email',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
        'bothelp_user_id', 'created_at', 'profile_link', 'ref', 
        'conversations_count', 'first_contact_at', 'last_contact_at', 
        'user_id', 'messenger_username', 'created_at_show', 'cuid'
    ];
    
    foreach ($data as $key => $value) {
        
        if (in_array($key, $excludeFields)) {
            continue;
        }
        
        
        $displayValue = ($value === null) ? 'null' : (string)$value;
        $comments[] = $key . ": " . $displayValue;
    }
    
    return implode("\n", $comments);
}
?>