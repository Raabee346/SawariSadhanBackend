# Testing Khalti Payment Integration with Postman

Complete guide to test Khalti payment gateway integration using Postman.

---

## Prerequisites

Before testing payments, make sure you have:

1. ✅ Khalti test keys configured in `.env`
2. ✅ Laravel server running (`php artisan serve`)
3. ✅ Database migrated and seeded
4. ✅ A verified vehicle in the database
5. ✅ At least one fiscal year in the database
6. ✅ Postman installed

---

## Step 1: Get Authentication Token

All payment endpoints require authentication. First, login to get a Bearer token.

### Request: Login

**Method:** `POST`  
**URL:** `http://localhost:8000/api/login`  
**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Body (JSON):**
```json
{
    "type": "user",
    "email": "your-email@example.com",
    "password": "your-password"
}
```

**Expected Response:**
```json
{
    "message": "Login successful",
    "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "type": "user",
    "unique_id": "USER123"
}
```

**📌 Important:** Copy the `token` value - you'll need it for all subsequent requests!

---

## Step 2: Get Your Vehicles

You need a vehicle ID to create a payment. Get your vehicles first.

### Request: Get Vehicles

**Method:** `GET`  
**URL:** `http://localhost:8000/api/vehicles`  
**Headers:**
```
Authorization: Bearer {your-token-from-step-1}
Accept: application/json
```

**Expected Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "registration_number": "BA-01-1234",
            "owner_name": "Ram Bahadur Shrestha",
            "verification_status": "approved",
            ...
        }
    ]
}
```

**📌 Note:** 
- Make sure the vehicle has `verification_status: "approved"`
- Copy the `vehicle_id` you want to use for payment

---

## Step 3: Get Fiscal Years

You need a fiscal year ID for the payment.

### Request: Get Fiscal Years

**Method:** `GET`  
**URL:** `http://localhost:8000/api/fiscal-years`  
**Headers:**
```
Accept: application/json
```

**Expected Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "year": "2081/2082",
            "start_date": "2024-07-17",
            "end_date": "2025-07-16",
            ...
        }
    ]
}
```

**📌 Note:** Copy the `fiscal_year_id` you want to use

---

## Step 4: Create Khalti Payment (Initialize Payment)

This creates a payment record and initializes Khalti payment.

### Request: Create Payment with Khalti

**Method:** `POST`  
**URL:** `http://localhost:8000/api/payments`  
**Headers:**
```
Authorization: Bearer {your-token-from-step-1}
Content-Type: application/json
Accept: application/json
```

**Body (JSON):**
```json
{
    "vehicle_id": 1,
    "fiscal_year_id": 1,
    "payment_method": "khalti"
}
```

**Expected Response (Success):**
```json
{
    "success": true,
    "message": "Payment initialized. Please complete the payment.",
    "data": {
        "id": 1,
        "user_id": 1,
        "vehicle_id": 1,
        "fiscal_year_id": 1,
        "tax_amount": "5000.00",
        "renewal_fee": "500.00",
        "penalty_amount": "0.00",
        "insurance_amount": "2000.00",
        "total_amount": "7500.00",
        "payment_status": "pending",
        "payment_method": "khalti",
        "transaction_id": "TXN-XXXXXXXXXXXX",
        "payment_details": {
            "pidx": "xxxxxxxxxxxxxxxxxxxxxxxxxxxx",
            "khalti_transaction_id": "xxxxxxxxxxxxxxxxxxxxxxxxxxxx"
        },
        ...
    },
    "khalti": {
        "pidx": "xxxxxxxxxxxxxxxxxxxxxxxxxxxx",
        "payment_url": "https://a.khalti.com/api/v2/epayment/initiate/?pidx=xxxxxxxxxxxx"
    }
}
```

**📌 Important Notes:**
- Copy the `pidx` from the response - you'll need it for verification
- Copy the `payment.id` - needed for verification endpoint
- The `payment_url` can be used to open payment in browser (for testing)

---

## Step 5: Test Payment in Browser (Optional)

You can open the `payment_url` from Step 4 in your browser to simulate the payment flow:

1. Copy the `payment_url` from the response
2. Open it in a browser
3. Use test credentials:
   - Test Card: `4242 4242 4242 4242`
   - Or use test mobile wallet credentials from Khalti dashboard
4. Complete the payment
5. You'll be redirected to the callback URL
6. After payment, proceed to Step 6 to verify

---

## Step 6: Verify Khalti Payment

After payment is completed (in browser or mobile app), verify it using the `pidx`.

### Request: Verify Payment

**Method:** `POST`  
**URL:** `http://localhost:8000/api/payments/{payment_id}/verify-khalti`  
**Headers:**
```
Authorization: Bearer {your-token-from-step-1}
Content-Type: application/json
Accept: application/json
```

**Replace `{payment_id}`** with the payment ID from Step 4.

**Body (JSON):**
```json
{
    "pidx": "xxxxxxxxxxxxxxxxxxxxxxxxxxxx"
}
```

**Expected Response (Success):**
```json
{
    "success": true,
    "message": "Payment verified successfully",
    "data": {
        "id": 1,
        "payment_status": "completed",
        "transaction_id": "xxxxxxxxxxxx",
        "payment_date": "2024-01-15",
        "payment_details": {
            "pidx": "xxxxxxxxxxxxxxxxxxxxxxxxxxxx",
            "khalti_transaction_id": "xxxxxxxxxxxx",
            "verified_at": "2024-01-15 10:30:00",
            "verification_response": { ... }
        },
        ...
    }
}
```

**Expected Response (Failed):**
```json
{
    "success": false,
    "message": "Payment verification failed",
    "data": {
        "payment_status": "failed",
        ...
    }
}
```

---

## Step 7: Get Payment Details

Verify the payment status was updated correctly.

### Request: Get Payment

**Method:** `GET`  
**URL:** `http://localhost:8000/api/payments/{payment_id}`  
**Headers:**
```
Authorization: Bearer {your-token-from-step-1}
Accept: application/json
```

**Expected Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "payment_status": "completed",
        "total_amount": "7500.00",
        "payment_method": "khalti",
        "payment_date": "2024-01-15",
        ...
    }
}
```

---

## Step 8: Test Khalti Callback (Webhook)

This endpoint is called by Khalti server after payment completion. You can simulate it for testing.

### Request: Khalti Callback

**Method:** `POST`  
**URL:** `http://localhost:8000/api/payments/khalti/callback`  
**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Body (JSON):**
```json
{
    "pidx": "xxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "transaction_id": "xxxxxxxxxxxx",
    "amount": 750000,
    "status": "Completed"
}
```

**Expected Response:**
```json
{
    "success": true,
    "message": "Payment callback processed"
}
```

---

## Complete Postman Collection JSON

You can import this into Postman:

```json
{
    "info": {
        "name": "Khalti Payment Testing",
        "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
    },
    "variable": [
        {
            "key": "base_url",
            "value": "http://localhost:8000/api",
            "type": "string"
        },
        {
            "key": "token",
            "value": "",
            "type": "string"
        },
        {
            "key": "payment_id",
            "value": "",
            "type": "string"
        },
        {
            "key": "pidx",
            "value": "",
            "type": "string"
        }
    ],
    "item": [
        {
            "name": "1. Login",
            "request": {
                "method": "POST",
                "header": [
                    {
                        "key": "Content-Type",
                        "value": "application/json"
                    }
                ],
                "body": {
                    "mode": "raw",
                    "raw": "{\n    \"type\": \"user\",\n    \"email\": \"your-email@example.com\",\n    \"password\": \"your-password\"\n}"
                },
                "url": {
                    "raw": "{{base_url}}/login",
                    "host": ["{{base_url}}"],
                    "path": ["login"]
                }
            },
            "event": [
                {
                    "listen": "test",
                    "script": {
                        "exec": [
                            "if (pm.response.code === 200) {",
                            "    var jsonData = pm.response.json();",
                            "    pm.collectionVariables.set('token', jsonData.token);",
                            "}"
                        ]
                    }
                }
            ]
        },
        {
            "name": "2. Get Vehicles",
            "request": {
                "method": "GET",
                "header": [
                    {
                        "key": "Authorization",
                        "value": "Bearer {{token}}"
                    }
                ],
                "url": {
                    "raw": "{{base_url}}/vehicles",
                    "host": ["{{base_url}}"],
                    "path": ["vehicles"]
                }
            }
        },
        {
            "name": "3. Get Fiscal Years",
            "request": {
                "method": "GET",
                "header": [],
                "url": {
                    "raw": "{{base_url}}/fiscal-years",
                    "host": ["{{base_url}}"],
                    "path": ["fiscal-years"]
                }
            }
        },
        {
            "name": "4. Create Khalti Payment",
            "request": {
                "method": "POST",
                "header": [
                    {
                        "key": "Authorization",
                        "value": "Bearer {{token}}"
                    },
                    {
                        "key": "Content-Type",
                        "value": "application/json"
                    }
                ],
                "body": {
                    "mode": "raw",
                    "raw": "{\n    \"vehicle_id\": 1,\n    \"fiscal_year_id\": 1,\n    \"payment_method\": \"khalti\"\n}"
                },
                "url": {
                    "raw": "{{base_url}}/payments",
                    "host": ["{{base_url}}"],
                    "path": ["payments"]
                }
            },
            "event": [
                {
                    "listen": "test",
                    "script": {
                        "exec": [
                            "if (pm.response.code === 201) {",
                            "    var jsonData = pm.response.json();",
                            "    pm.collectionVariables.set('payment_id', jsonData.data.id);",
                            "    if (jsonData.khalti) {",
                            "        pm.collectionVariables.set('pidx', jsonData.khalti.pidx);",
                            "    }",
                            "}"
                        ]
                    }
                }
            ]
        },
        {
            "name": "5. Verify Khalti Payment",
            "request": {
                "method": "POST",
                "header": [
                    {
                        "key": "Authorization",
                        "value": "Bearer {{token}}"
                    },
                    {
                        "key": "Content-Type",
                        "value": "application/json"
                    }
                ],
                "body": {
                    "mode": "raw",
                    "raw": "{\n    \"pidx\": \"{{pidx}}\"\n}"
                },
                "url": {
                    "raw": "{{base_url}}/payments/{{payment_id}}/verify-khalti",
                    "host": ["{{base_url}}"],
                    "path": ["payments", "{{payment_id}}", "verify-khalti"]
                }
            }
        },
        {
            "name": "6. Get Payment Details",
            "request": {
                "method": "GET",
                "header": [
                    {
                        "key": "Authorization",
                        "value": "Bearer {{token}}"
                    }
                ],
                "url": {
                    "raw": "{{base_url}}/payments/{{payment_id}}",
                    "host": ["{{base_url}}"],
                    "path": ["payments", "{{payment_id}}"]
                }
            }
        },
        {
            "name": "7. Khalti Callback (Webhook)",
            "request": {
                "method": "POST",
                "header": [
                    {
                        "key": "Content-Type",
                        "value": "application/json"
                    }
                ],
                "body": {
                    "mode": "raw",
                    "raw": "{\n    \"pidx\": \"{{pidx}}\",\n    \"transaction_id\": \"xxxxxxxxxxxx\",\n    \"amount\": 750000,\n    \"status\": \"Completed\"\n}"
                },
                "url": {
                    "raw": "{{base_url}}/payments/khalti/callback",
                    "host": ["{{base_url}}"],
                    "path": ["payments", "khalti", "callback"]
                }
            }
        }
    ]
}
```

---

## Testing Workflow Summary

1. **Login** → Get token
2. **Get Vehicles** → Find verified vehicle ID
3. **Get Fiscal Years** → Find fiscal year ID
4. **Create Payment** → Initialize Khalti payment, get `pidx`
5. **Complete Payment** → Use payment_url in browser (optional)
6. **Verify Payment** → Verify using `pidx`
7. **Check Payment** → Verify status is updated
8. **Test Callback** → Simulate webhook (optional)

---

## Common Issues & Solutions

### Issue: "Unauthenticated" Error
**Solution:** Make sure you're including the Bearer token in the Authorization header:
```
Authorization: Bearer your-token-here
```

### Issue: "Vehicle must be verified before payment"
**Solution:** Make sure the vehicle's `verification_status` is `"approved"`

### Issue: "Invalid API Key" from Khalti
**Solution:** 
- Check your `.env` file has correct Khalti keys
- Make sure `KHALTI_SANDBOX=true` for test keys
- Run `php artisan config:clear`

### Issue: "Payment verification failed"
**Solution:**
- Make sure you're using the correct `pidx` from payment initialization
- The `pidx` must be from a completed payment
- Check Khalti dashboard for payment status

### Issue: Payment URL not working
**Solution:**
- Make sure you're using test credentials
- Check if `KHALTI_RETURN_URL` is properly configured
- Use the `payment_url` from the response immediately (they expire)

---

## Testing Tips

1. **Use Collection Variables:** Set up variables in Postman for `token`, `payment_id`, `pidx` to make testing easier

2. **Save Responses:** Save the `pidx` and `payment_id` from responses for verification

3. **Test Error Cases:**
   - Try verifying with invalid `pidx`
   - Try creating payment with unverified vehicle
   - Try verifying payment twice

4. **Check Database:** After verification, check the `payments` table to see if status updated correctly

5. **Use Test Credentials:** Make sure you're using Khalti test keys, not live keys

---

## Expected Database Changes

After successful payment verification:

- `payments` table:
  - `payment_status` → `"completed"`
  - `payment_date` → Current date
  - `transaction_id` → Khalti transaction ID
  - `payment_details` → Contains `pidx`, verification data

- `vehicles` table:
  - `last_renewed_date` → Updated to fiscal year end date

---

## Next Steps

Once testing is successful in Postman:

1. ✅ Test with mobile app using Khalti SDK
2. ✅ Test webhook with actual Khalti server
3. ✅ Test with different payment amounts
4. ✅ Test error handling
5. ✅ Test with production keys when ready

Happy Testing! 🚀


