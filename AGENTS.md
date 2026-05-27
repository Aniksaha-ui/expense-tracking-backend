# AGENTS.md

You are building a Laravel 11 backend for an Expense Tracking System.

## Tech Stack
- Laravel 11
- PHP 8.2+
- MySQL
- JWT authentication
- Eloquent ORM
- REST API

## Main Goal
Build a backend where users can manage:
- Bank/card accounts
- Cash balance
- Expenses by category
- Withdrawals from card/bank to cash
- Recurring bills
- Reports

## First Task
Start by implementing authentication using JWT.

Create:
- Register API
- Login API
- Logout API
- Profile API
- JWT middleware protection

## Required Modules
After authentication, implement:

1. Accounts
2. Categories
3. Transactions
4. Transfers
5. Recurring Expenses
6. Reports

## Important Rules
- Every table must have `user_id`
- Users can only access their own data
- Use `decimal(12,2)` for money
- Never use float for balance
- Use `DB::transaction()` for balance changes
- Do not delete transaction history
- Check balance before expense or withdrawal
- Account current balance must update after every transaction

## Account Types
Use constants or enum:
- CARD
- BANK
- CASH
- MOBILE_BANKING

## Transaction Types
- OPENING_BALANCE
- INCOME
- EXPENSE
- WITHDRAW
- DEPOSIT
- TRANSFER
- RECURRING

## Coding Style
- Keep controllers thin
- Put business logic in Services
- Use Form Requests for validation
- Use API Resources for responses
- Use proper error messages
- Follow Laravel naming conventions

## First Implementation Order
1. Install and configure JWT authentication
2. Create AuthController
3. Create Account model, migration, controller, service
4. Create Category model and default seeders
5. Create Expense transaction system
6. Create withdrawal system
7. Create recurring bill system
8. Create reports

## Do not skip
- Migration files
- Models
- Relationships
- API routes
- Request validation
- Service classes
- Error handling


## API Response Format

All API responses must follow this structure:

Success response:

```json
{
  "isExecute": "success",
  "data": [],
  "msg": "Fetch information successfully"
}
```

Error response:

```json
{
  "isExecute": "failed",
  "data": [],
  "msg": "Invalid account id"
}
```

Validation response:

```json
{
  "isExecute": "failed",
  "data": [],
  "msg": "Validation failed",
  "errors": {
    "amount": [
      "The amount field is required."
    ]
  }
}
```

## Database Query Style

Use Laravel DB Query Builder instead of Eloquent ORM in most modules.

Use:
- DB::table()
- DB::select()
- DB::insert()
- DB::update()
- DB::delete()

Example:

```php
$accounts = DB::table('accounts')
    ->where('user_id', auth()->id())
    ->get();

return response()->json([
    'isExecute' => 'success',
    'data' => $accounts,
    'msg' => 'Fetch information successfully'
]);
```

## Transaction Rule

For balance updates always use:

```php
DB::transaction(function () {
   // balance update
   // insert transaction
});
```

## Controller Response Rule

Never return raw Laravel response like:

```php
return $data;
```

Always return:

```php
return response()->json([
    'isExecute' => 'success',
    'data' => $data,
    'msg' => 'Fetch information successfully'
]);
```

## Error Handling Rule

Use try-catch in service/controller.

Example:

```php
try {

   // logic

} catch (\Exception $e) {

   return response()->json([
      'isExecute' => 'failed',
      'data' => [],
      'msg' => $e->getMessage()
   ], 500);
}