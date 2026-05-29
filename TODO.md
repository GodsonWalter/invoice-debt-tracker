# TODO - SaaS Invoice Numbering

## DB & Constraints
- [ ] Add workspace invoice settings column: `workspaces.invoice_prefix`.
- [ ] Change invoices uniqueness from global `unique(invoice_number)` to composite `unique(workspace_id, invoice_number)`.

## Service Layer
- [ ] Create `app/Services/InvoiceNumberService.php`:
  - [ ] Build number format: `{prefix}-{year}-{sequence4}`.
  - [ ] Query last used sequence for the workspace + prefix + year.
  - [ ] Generate next invoice number.
  - [ ] Prevent duplicates via transaction + retry.

## Controller Refactor
- [ ] Update `app/Http/Controllers/InvoiceController.php`:
  - [ ] In `store()`, use `InvoiceNumberService` to set `invoice_number` (remove unique request validation).
  - [ ] In `update()`, validate `(workspace_id, invoice_number)` uniqueness.

## Views
- [ ] Update `resources/views/invoice/create.blade.php` to stop manual editing and show auto-generated invoice number.
- [ ] Keep `edit.blade.php` consistent with new uniqueness validation.

## Migrations
- [ ] Add new migration(s) for the schema updates.

## Tests / Verification
- [ ] Run migrations.
- [ ] Manual test: create multiple invoices in one workspace -> INV-2026-0001, 0002...
- [ ] Manual test: second workspace can reuse the same invoice_number.

