<?php
/**
 * ClientsRepo - Loads client data for document rendering.
 */
class ClientsRepo {
    public static function getById($db, ?int $clientsId): array {
        if (!$clientsId) return [
            'clients_name' => '',
            'clients_address' => '',
            'clients_email' => '',
            'clients_phone' => '',
            'clients_website' => '',
            'clients_vatId' => null,
            'clients_customerNumber' => null,
            'clients_paymentTermDays' => null,
        ];

        $db->where('clients_id', $clientsId);
        $client = $db->getOne('clients');
        return $client ?: [
            'clients_name' => '',
            'clients_address' => '',
            'clients_email' => '',
            'clients_phone' => '',
            'clients_website' => '',
            'clients_vatId' => null,
            'clients_customerNumber' => null,
            'clients_paymentTermDays' => null,
        ];
    }
}
