# Postman API Guide - Add Vehicle

## Endpoint
**POST** `/api/vehicles`

**Authentication Required:** Yes (Bearer Token)

---

## Method 1: Using form-data (Recommended - Supports File Uploads)

### Postman Setup:
1. Set method to **POST**
2. URL: `http://your-domain/api/vehicles`
3. Go to **Body** tab
4. Select **form-data** (NOT raw JSON)
5. Add **Authorization** header: `Bearer YOUR_TOKEN_HERE`

### Form Data Fields:

| Key | Type | Value | Required | Description |
|-----|------|-------|----------|-------------|
| `province_id` | Text | `1` | ✅ Yes | Province ID (1-7 for Nepal provinces) |
| `owner_name` | Text | `Ram Bahadur Shrestha` | ✅ Yes | Bluebook owner name (can be different from logged-in user) |
| `registration_number` | Text | `Ba 1 Pa 1234` | ✅ Yes | Vehicle registration number (unique) |
| `chassis_number` | Text | `CH-123456789` | ✅ Yes | Vehicle chassis number (unique) |
| `vehicle_type` | Text | `2W` | ✅ Yes | Options: `2W`, `4W`, `Commercial`, `Heavy` |
| `fuel_type` | Text | `Petrol` | ✅ Yes | Options: `Petrol`, `Diesel`, `Electric` |
| `engine_capacity` | Text | `150` | ✅ Yes | Engine capacity in CC (or Watts for Electric) |
| `registration_date` | Text | `2080-05-15` | ✅ Yes | BS date format: YYYY-MM-DD |
| `brand` | Text | `Honda` | ❌ No | Vehicle brand |
| `model` | Text | `Activa 6G` | ❌ No | Vehicle model |
| `manufacturing_year` | Text | `2023` | ❌ No | Manufacturing year (AD) |
| `is_commercial` | Text | `false` | ❌ No | Boolean: `true` or `false` |
| `rc_firstpage` | File | [Select File] | ❌ No | RC first page (Image/PDF, max 5MB) |
| `rc_ownerdetails` | File | [Select File] | ❌ No | RC owner details (Image/PDF, max 5MB) |
| `rc_vehicledetails` | File | [Select File] | ❌ No | RC vehicle details (Image/PDF, max 5MB) |
| `lastrenewdate` | File | [Select File] | ❌ No | Last renewal date doc (Image/PDF, max 5MB) |
| `insurance` | File | [Select File] | ❌ No | Insurance document (Image/PDF, max 5MB) |
| `owner_ctznship_front` | File | [Select File] | ❌ No | Citizenship front (Image/PDF, max 5MB) |
| `owner_ctznship_back` | File | [Select File] | ❌ No | Citizenship back (Image/PDF, max 5MB) |

### Example Values:

**For Two-Wheeler (2W):**
```
province_id: 3
owner_name: Ram Bahadur Shrestha
registration_number: Ba 1 Pa 5678
chassis_number: CH-123456789
vehicle_type: 2W
fuel_type: Petrol
engine_capacity: 150
registration_date: 2080-05-15
brand: Honda
model: Activa 6G
manufacturing_year: 2023
is_commercial: false
```

**For Four-Wheeler (4W):**
```
province_id: 3
owner_name: Shyam Kumar Tamang
registration_number: Ba 1 Pa 9012
chassis_number: CH-987654321
vehicle_type: 4W
fuel_type: Petrol
engine_capacity: 1200
registration_date: 2080-08-20
brand: Toyota
model: Corolla
manufacturing_year: 2022
is_commercial: false
```

**For Electric Vehicle:**
```
province_id: 3
owner_name: Gita Devi Karki
registration_number: Ba 1 Pa 3456
chassis_number: CH-456789123
vehicle_type: 2W
fuel_type: Electric
engine_capacity: 1200
registration_date: 2081-01-10
brand: Yatri
model: P1
manufacturing_year: 2024
is_commercial: false
```

---

## Method 2: Using Raw JSON (Without Files)

**Note:** This method cannot upload files. Use this only for testing without documents.

### Postman Setup:
1. Set method to **POST**
2. URL: `http://your-domain/api/vehicles`
3. Go to **Body** tab
4. Select **raw** and choose **JSON**
5. Add **Authorization** header: `Bearer YOUR_TOKEN_HERE`

### JSON Body Example:

```json
{
  "province_id": 3,
  "owner_name": "Ram Bahadur Shrestha",
  "registration_number": "Ba 1 Pa 1234",
  "chassis_number": "CH-123456789",
  "vehicle_type": "2W",
  "fuel_type": "Petrol",
  "engine_capacity": 150,
  "registration_date": "2080-05-15",
  "brand": "Honda",
  "model": "Activa 6G",
  "manufacturing_year": 2023,
  "is_commercial": false
}
```

### Complete JSON Examples:

**Two-Wheeler (Petrol):**
```json
{
  "province_id": 3,
  "owner_name": "Ram Bahadur Shrestha",
  "registration_number": "Ba 1 Pa 5678",
  "chassis_number": "CH-123456789",
  "vehicle_type": "2W",
  "fuel_type": "Petrol",
  "engine_capacity": 150,
  "registration_date": "2080-05-15",
  "brand": "Honda",
  "model": "Activa 6G",
  "manufacturing_year": 2023,
  "is_commercial": false
}
```

**Four-Wheeler (Diesel):**
```json
{
  "province_id": 3,
  "owner_name": "Shyam Kumar Tamang",
  "registration_number": "Ba 1 Pa 9012",
  "chassis_number": "CH-987654321",
  "vehicle_type": "4W",
  "fuel_type": "Diesel",
  "engine_capacity": 1500,
  "registration_date": "2080-08-20",
  "brand": "Toyota",
  "model": "Corolla",
  "manufacturing_year": 2022,
  "is_commercial": false
}
```

**Commercial Vehicle:**
```json
{
  "province_id": 3,
  "owner_name": "Hari Prasad Thapa",
  "registration_number": "Ba 1 Pa 3456",
  "chassis_number": "CH-456789123",
  "vehicle_type": "Commercial",
  "fuel_type": "Diesel",
  "engine_capacity": 2500,
  "registration_date": "2080-03-10",
  "brand": "Tata",
  "model": "Sumo",
  "manufacturing_year": 2021,
  "is_commercial": true
}
```

**Electric Two-Wheeler:**
```json
{
  "province_id": 3,
  "owner_name": "Gita Devi Karki",
  "registration_number": "Ba 1 Pa 7890",
  "chassis_number": "CH-789123456",
  "vehicle_type": "2W",
  "fuel_type": "Electric",
  "engine_capacity": 1200,
  "registration_date": "2081-01-10",
  "brand": "Yatri",
  "model": "P1",
  "manufacturing_year": 2024,
  "is_commercial": false
}
```

---

## Province IDs Reference:

| ID | Province Name | Code |
|----|---------------|------|
| 1 | Koshi | KOSHI |
| 2 | Madhesh | MADHESH |
| 3 | Bagmati | BAGMATI |
| 4 | Gandaki | GANDAKI |
| 5 | Lumbini | LUMBINI |
| 6 | Karnali | KARNALI |
| 7 | Sudurpashchim | SUDURPASHCHIM |

---

## Vehicle Type Options:
- `2W` - Two Wheeler
- `4W` - Four Wheeler
- `Commercial` - Commercial Vehicle
- `Heavy` - Heavy Vehicle

## Fuel Type Options:
- `Petrol`
- `Diesel`
- `Electric`

---

## Expected Success Response:

```json
{
  "success": true,
  "message": "Vehicle added successfully. Please wait for admin verification.",
  "data": {
    "id": 1,
    "user_id": 1,
    "province_id": 3,
    "owner_name": "Ram Bahadur Shrestha",
    "registration_number": "Ba 1 Pa 1234",
    "chassis_number": "CH-123456789",
    "vehicle_type": "2W",
    "fuel_type": "Petrol",
    "brand": "Honda",
    "model": "Activa 6G",
    "engine_capacity": 150,
    "manufacturing_year": 2023,
    "registration_date": "2023-08-31",
    "last_renewed_date": null,
    "verification_status": "pending",
    "rejection_reason": null,
    "verified_by": null,
    "verified_at": null,
    "is_commercial": false,
    "documents": null,
    "registration_date_bs": "2080-05-15",
    "last_renewed_date_bs": null,
    "created_at": "2025-12-25T07:30:00.000000Z",
    "updated_at": "2025-12-25T07:30:00.000000Z",
    "province": {
      "id": 3,
      "name": "Bagmati",
      "code": "BAGMATI",
      "number": 3,
      "is_active": true
    }
  }
}
```

## Expected Error Response:

```json
{
  "success": false,
  "message": "Validation error",
  "errors": {
    "registration_number": [
      "The registration number has already been taken."
    ],
    "chassis_number": [
      "The chassis number has already been taken."
    ],
    "province_id": [
      "The selected province id is invalid."
    ]
  }
}
```

---

## Important Notes:

1. **Authentication:** Always include `Authorization: Bearer YOUR_TOKEN` header
2. **Owner Name:** Enter the bluebook owner name manually (can be different from logged-in user)
3. **Date Format:** Use BS (Bikram Sambat) format: `YYYY-MM-DD` (e.g., `2080-05-15`)
4. **File Uploads:** Use `form-data` in Postman, not JSON
5. **File Types:** Accepted formats: JPEG, PNG, JPG, PDF (max 5MB each)
6. **Unique Fields:** `registration_number` and `chassis_number` must be unique
7. **Engine Capacity:** 
   - For Petrol/Diesel: Enter in CC (e.g., 150, 1200, 2500)
   - For Electric: Enter in Watts (e.g., 1200, 5000)

---

## Testing Steps:

1. **Get Auth Token:**
   - POST `/api/login` with email/password
   - Copy the `token` from response

2. **Get Province List (Optional):**
   - GET `/api/vehicles/provinces`
   - Use this to get valid province IDs

3. **Add Vehicle:**
   - POST `/api/vehicles` with form-data or JSON body
   - Include Authorization header with token
   - **Important:** Provide `owner_name` manually (not auto-filled from logged-in user)

4. **Check Vehicle Status:**
   - GET `/api/vehicles/{id}` to see verification status
   - Status will be `pending` until admin approves

