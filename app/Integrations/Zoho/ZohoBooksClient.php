<?php

namespace App\Integrations\Zoho;

class ZohoBooksClient extends ZohoClient
{
    private const BASE = '/books/v3';

    private function organizationId(): string
    {
        return config('zoho.books_organization_id');
    }

    // -------------------------------------------------------------------------
    // Contacts (Customers in Zoho Books)
    // -------------------------------------------------------------------------

    /**
     * Create a customer in Zoho Books.
     * A customer must exist before creating invoices or payments for them.
     */
    public function createCustomer(array $data): array
    {
        return $this->call('books.create_customer', $data, function ($http) use ($data) {
            return $http->post(self::BASE . '/contacts', array_merge($data, [
                'organization_id' => $this->organizationId(),
            ]))->throw()->json();
        });
    }

    /**
     * Search for a customer in Zoho Books by email.
     * Returns the first match or null if not found.
     */
    public function findCustomerByEmail(string $email): ?array
    {
        $result = $this->call('books.find_customer_by_email', ['email' => $email], function ($http) use ($email) {
            return $http->get(self::BASE . '/contacts', [
                'organization_id' => $this->organizationId(),
                'email'           => $email,
                'contact_type'    => 'customer',
            ])->throw()->json();
        });

        return data_get($result, 'contacts.0');
    }

    // -------------------------------------------------------------------------
    // Items (Products / Inventory)
    // -------------------------------------------------------------------------

    /**
     * Create a product/service item in Zoho Books.
     */
    public function createItem(array $data): array
    {
        return $this->call('books.create_item', $data, function ($http) use ($data) {
            return $http->post(self::BASE . '/items', array_merge($data, [
                'organization_id' => $this->organizationId(),
            ]))->throw()->json();
        });
    }

    /**
     * Update an existing item in Zoho Books.
     */
    public function updateItem(string $itemId, array $data): array
    {
        return $this->call('books.update_item', ['id' => $itemId, ...$data], function ($http) use ($itemId, $data) {
            return $http->put(self::BASE . "/items/{$itemId}", array_merge($data, [
                'organization_id' => $this->organizationId(),
            ]))->throw()->json();
        });
    }

    // -------------------------------------------------------------------------
    // Invoices (Orders)
    // -------------------------------------------------------------------------

    /**
     * Create an invoice in Zoho Books for a given order.
     *
     * @param  array  $data  Should include: customer_id, line_items, date, etc.
     */
    public function createInvoice(array $data): array
    {
        return $this->call('books.create_invoice', $data, function ($http) use ($data) {
            return $http->post(self::BASE . '/invoices', array_merge($data, [
                'organization_id' => $this->organizationId(),
            ]))->throw()->json();
        });
    }

    /**
     * Update an existing invoice in Zoho Books.
     */
    public function updateInvoice(string $invoiceId, array $data): array
    {
        return $this->call('books.update_invoice', ['id' => $invoiceId, ...$data], function ($http) use ($invoiceId, $data) {
            return $http->put(self::BASE . "/invoices/{$invoiceId}", array_merge($data, [
                'organization_id' => $this->organizationId(),
            ]))->throw()->json();
        });
    }

    // -------------------------------------------------------------------------
    // Payments
    // -------------------------------------------------------------------------

    /**
     * Record a payment against an invoice in Zoho Books.
     *
     * @param  array  $data  Should include: customer_id, invoices (array with invoice_id + amount_applied), amount, date, payment_mode
     */
    public function createPayment(array $data): array
    {
        return $this->call('books.create_payment', $data, function ($http) use ($data) {
            return $http->post(self::BASE . '/customerpayments', array_merge($data, [
                'organization_id' => $this->organizationId(),
            ]))->throw()->json();
        });
    }
}
