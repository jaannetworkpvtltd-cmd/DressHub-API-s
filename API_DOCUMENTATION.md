# DressHub E-Commerce APIs Documentation

**Base URL:** `http://localhost/DressHub APIs/`  
**Version:** 1.0  
**Last Updated:** February 11, 2026

---

## Table of Contents
1. [Authentication APIs](#authentication-apis)
2. [Profile APIs](#profile-apis)
3. [Product APIs](#product-apis)
4. [Product Variants APIs](#product-variants-apis)
5. [Product Images APIs](#product-images-apis)
6. [Password Reset API](#password-reset-api)
7. [Bulk Price API](#bulk-price-api)
8. [Advanced Search API](#advanced-search-api)

---

## Authentication APIs

### 1. Register User

**Endpoint:** `POST /register.php`

**Format Type:** `application/json`

**Description:** Create a new user account

**Request Headers:**
```json
{
  "Content-Type": "application/json"
}
```

**Request Body:**
```json
{
  "username": "johndoe",
  "password": "password123"
}
```

**Success Response (201):**
```json
{
  "message": "User registered successfully",
  "user_id": 1
}
```

---

### 2. Login User

**Endpoint:** `POST /login.php`

**Format Type:** `application/json`

**Description:** Authenticate user and get JWT token (24-hour expiry)

**Request Headers:**
```json
{
  "Content-Type": "application/json"
}
```

**Request Body:**
```json
{
  "username": "johndoe",
  "password": "password123"
}
```

**Success Response (200):**
```json
{
  "message": "Login successful",
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "user": {
    "id": 1,
    "username": "johndoe"
  }
}
```

---

## Profile APIs

### 1. Get User Profile

**Endpoint:** `GET /api_profile.php`

**Format Type:** `application/json`

**Description:** Retrieve the authenticated user's profile

**Request Headers:**
```json
{
  "Authorization": "Bearer {jwt_token}"
}
```

**Success Response (200):**
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "user_id": 1,
    "full_name": "John Doe",
    "phone": "1234567890",
    "avatar_url": null,
    "is_active": 1,
    "created_at": "2026-02-11 10:30:00"
  }
}
```

---

### 2. Create or Update Profile

**Endpoint:** `POST /api_profile.php`

**Format Type:** `application/json`

**Description:** Create or update user profile information

**Request Headers:**
```json
{
  "Authorization": "Bearer {jwt_token}",
  "Content-Type": "application/json"
}
```

**Request Body (IMPORTANT - Must have opening { and closing }):**
```json
{
  "full_name": "John Doe",
  "phone": "1234567890",
  "is_active": 1
}
```

**Success Response (200):**
```json
{
  "status": "success",
  "message": "Profile updated successfully"
}
```

**⚠️ Common Error - NULL Values:**
**Problem:** If full_name and phone are saved as NULL

**Cause:** JSON format is missing opening `{` or closing `}`
- ❌ Wrong: `"full_name": "John", ...}`
- ✅ Correct: `{"full_name": "John", ...}`

**Solution:** Always include full JSON structure with opening and closing braces

---

### 3. Update Profile (PUT)

**Endpoint:** `PUT /api_profile.php`

**Format Type:** `application/json`

**Request Headers:**
```json
{
  "Authorization": "Bearer {jwt_token}",
  "Content-Type": "application/json"
}
```

**Request Body:**
```json
{
  "full_name": "Jane Doe",
  "phone": "0987654321",
  "is_active": 1
}
```

---

## Product APIs

### 1. Get All Products

**Endpoint:** `GET /api_products.php`

**Format Type:** `application/json`

**Description:** Retrieve all products

**Success Response (200):**
```json
{
  "status": "success",
  "message": "Products retrieved successfully",
  "data": [
    {
      "id": 1,
      "name": "T-Shirt",
      "price": 29.99,
      "stock_quantity": 100
    }
  ],
  "count": 1
}
```

---

### 2. Get Product by ID

**Endpoint:** `GET /api_products.php/{id}`

**Format Type:** `application/json`

**Example:** `GET /api_products.php/1`

---

### 3. Create Product

**Endpoint:** `POST /api_products.php`

**Format Type:** `application/json`

**Request Body:**
```json
{
  "name": "T-Shirt",
  "description": "Cotton T-Shirt",
  "price": 29.99,
  "stock_quantity": 100,
  "category_id": 1
}
```

---

### 4. Update Product

**Endpoint:** `PUT /api_products.php/{id}`

**Format Type:** `application/json`

---

### 5. Delete Product

**Endpoint:** `DELETE /api_products.php/{id}`

**Format Type:** `application/json`

---

## Product Variants APIs

### 1. Get All Variants

**Endpoint:** `GET /api_variants.php`

**Format Type:** `application/json`

---

### 2. Get Variant by ID

**Endpoint:** `GET /api_variants.php/{id}`

**Format Type:** `application/json`

---

### 3. Create Variant

**Endpoint:** `POST /api_variants.php`

**Format Type:** `application/json`

---

### 4. Update Variant

**Endpoint:** `PUT /api_variants.php/{id}`

**Format Type:** `application/json`

---

### 5. Delete Variant

**Endpoint:** `DELETE /api_variants.php/{id}`

**Format Type:** `application/json`

---

## Product Images APIs

### 1. Get All Images

**Endpoint:** `GET /api_images.php`

**Format Type:** `application/json`

---

### 2. Get Images by Product ID

**Endpoint:** `GET /api_images.php?product_id={id}`

**Format Type:** `application/json`

---

### 3. Upload Product Image

**Endpoint:** `POST /api_images.php`

**Format Type:** `multipart/form-data`

---

## Password Reset API

### 1. Request Password Reset

**Endpoint:** `POST /api_password_reset.php`

**Format Type:** `application/json`

---

## Bulk Price API

### 1. Update Multiple Product Prices

**Endpoint:** `POST /api_bulk_prices.php`

**Format Type:** `application/json`

---

## Advanced Search API

### 1. Advanced Product Search

**Endpoint:** `GET /api_advanced.php`

**Format Type:** `application/json`

---

## HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success - OK |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthorized |
| 404 | Not Found |
| 500 | Server Error |

---

## Authentication Notes

- **Required for:** Profile endpoints
- **Token Format:** `Authorization: Bearer {token}`
- **Token Expiry:** 24 hours
- **Optional for:** Products, variants, images, search

---

## Postman Setup Guide

### Step 1: Login (Get Token)
1. Method: POST
2. URL: `http://localhost/DressHub%20APIs/login.php`
3. Headers: `Content-Type: application/json`
4. Body (raw JSON):
```json
{
  "username": "testuser",
  "password": "yourpassword"
}
```
5. Copy the token from response

### Step 2: Create Profile
1. Method: POST
2. URL: `http://localhost/DressHub%20APIs/api_profile.php`
3. Headers:
   - `Authorization: Bearer {paste_token_here}`
   - `Content-Type: application/json`
4. Body (raw JSON):
```json
{
  "full_name": "John Doe",
  "phone": "1234567890",
  "is_active": 1
}
```

### Step 3: Get Profile
1. Method: GET
2. URL: `http://localhost/DressHub%20APIs/api_profile.php`
3. Headers: `Authorization: Bearer {paste_token_here}`

---

## Common Issues

### Issue: full_name and phone are NULL
**Solution:** Check JSON format has `{` at start and `}` at end

### Issue: Unauthorized - Invalid token
**Solution:** 
1. Login first to get token
2. Use correct Authorization header format
3. Token expires in 24 hours - get new one if needed

### Issue: Error parsing JSON
**Solution:** Use valid JSON format with proper quotes and commas

---

## File Limits

- Avatar: Max 5MB (JPEG, PNG, GIF, WebP)
- Product Image: Max 5MB (JPEG, PNG, GIF, WebP)

---

## Version
**Current Version:** 1.0  
**Last Updated:** February 11, 2026
